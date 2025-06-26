import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
import json
import pickle
from fastapi import APIRouter, HTTPException, Query, Depends, Body, Path
from pydantic import BaseModel, Field
import inspect

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

# Model imports
from src.models.alt_fecf import FeatureEnhancedCF
from src.models.ncf import NCFRecommender
from src.models.hybrid import HybridRecommender
from config import MODELS_DIR, PROCESSED_DIR

# Setup router
router = APIRouter(
    prefix="/recommend",
    tags=["recommendations"],
    responses={404: {"description": "Not found"}},
)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Global model dan cache
_models = {
    "fecf": None,
    "ncf": None,
    "hybrid": None
}

# ⚡ PERBAIKAN: TTL cache dipendekkan sesuai permintaan user (1-5 menit)
_cache_ttl = {
    "cold_start": 180,     # 3 menit untuk cold-start user (dipendekkan dari 30 menit)
    "low_activity": 240,   # 4 menit untuk pengguna dengan aktivitas rendah  
    "normal": 300,         # 5 menit untuk pengguna normal
    "active": 300          # 5 menit untuk pengguna sangat aktif
}

# ⚡ PERBAIKAN: Cache dengan persistent storage untuk mengatasi restart
_cache_dir = os.path.join(os.path.dirname(__file__), '..', '..', 'cache')
os.makedirs(_cache_dir, exist_ok=True)

# Cache in-memory
_user_recommendations_cache = {
    "fecf": {},
    "ncf": {},
    "hybrid": {}
}

# ⚡ TAMBAHAN: Tracking untuk cold-start users yang baru dapat interaksi
_cold_start_tracking = {}

def _get_cache_file_path(cache_type: str) -> str:
    """Generate path file cache persistent"""
    return os.path.join(_cache_dir, f"{cache_type}_cache.pkl")

def _load_persistent_cache():
    """⚡ TAMBAHAN: Load cache dari file persistent"""
    global _user_recommendations_cache, _cold_start_tracking
    
    try:
        # Load recommendation cache
        for cache_type in ["fecf", "ncf", "hybrid"]:
            cache_file = _get_cache_file_path(f"recommendations_{cache_type}")
            if os.path.exists(cache_file):
                with open(cache_file, 'rb') as f:
                    cached_data = pickle.load(f)
                    # Filter expired entries
                    current_time = datetime.now()
                    valid_cache = {}
                    for key, value in cached_data.items():
                        if current_time < value['expires']:
                            valid_cache[key] = value
                    _user_recommendations_cache[cache_type] = valid_cache
                    logger.info(f"Loaded {len(valid_cache)} valid {cache_type} cache entries")
        
        # Load cold-start tracking
        tracking_file = _get_cache_file_path("cold_start_tracking")
        if os.path.exists(tracking_file):
            with open(tracking_file, 'rb') as f:
                _cold_start_tracking = pickle.load(f)
                logger.info(f"Loaded cold-start tracking for {len(_cold_start_tracking)} users")
                
    except Exception as e:
        logger.warning(f"Error loading persistent cache: {e}")

def _save_persistent_cache():
    """⚡ TAMBAHAN: Save cache ke file persistent"""
    try:
        # Save recommendation cache
        for cache_type, cache_data in _user_recommendations_cache.items():
            cache_file = _get_cache_file_path(f"recommendations_{cache_type}")
            with open(cache_file, 'wb') as f:
                pickle.dump(cache_data, f)
        
        # Save cold-start tracking
        tracking_file = _get_cache_file_path("cold_start_tracking")
        with open(tracking_file, 'wb') as f:
            pickle.dump(_cold_start_tracking, f)
            
    except Exception as e:
        logger.warning(f"Error saving persistent cache: {e}")

def _check_recent_interactions(user_id: str) -> bool:
    """⚡ PERBAIKAN: Check apakah user memiliki interaksi baru dalam 5 menit terakhir"""
    try:
        interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        if not os.path.exists(interactions_path):
            return False
            
        # Baca file interactions
        interactions_df = pd.read_csv(interactions_path)
        
        # Filter untuk user ini
        user_interactions = interactions_df[interactions_df['user_id'] == user_id].copy()  # ⚡ FIX: Tambah .copy()
        
        if user_interactions.empty:
            return False
        
        # Check timestamp kolom
        if 'timestamp' in user_interactions.columns:
            # Parse timestamp dan check 5 menit terakhir
            try:
                user_interactions.loc[:, 'timestamp'] = pd.to_datetime(user_interactions['timestamp'])  # ⚡ FIX: Gunakan .loc
                recent_threshold = datetime.now() - timedelta(minutes=5)
                recent_interactions = user_interactions[user_interactions['timestamp'] > recent_threshold]
                
                if not recent_interactions.empty:
                    logger.info(f"Found {len(recent_interactions)} recent interactions for user {user_id}")
                    return True
            except Exception as e:
                logger.warning(f"Error parsing timestamps: {e}")
                
        # Fallback: check jika user ada di file (tanpa timestamp check)
        return len(user_interactions) > 0
        
    except Exception as e:
        logger.warning(f"Error checking recent interactions: {e}")
        return False

def _invalidate_cold_start_cache(user_id: str):
    """⚡ TAMBAHAN: Invalidate cache untuk user yang tidak lagi cold-start"""
    global _user_recommendations_cache, _cold_start_tracking
    
    try:
        # Mark user sebagai tidak cold-start lagi
        _cold_start_tracking[user_id] = {
            'last_interaction': datetime.now(),
            'was_cold_start': True,
            'cache_invalidated': True
        }
        
        # Hapus cache untuk user ini dari semua model
        for cache_type in _user_recommendations_cache:
            cache = _user_recommendations_cache[cache_type]
            keys_to_remove = [key for key in cache.keys() if key.startswith(f"{user_id}_")]
            
            for key in keys_to_remove:
                del cache[key]
                logger.info(f"Invalidated cache key: {key}")
        
        # Save persistent cache
        _save_persistent_cache()
        
        logger.info(f"Invalidated cold-start cache for user {user_id}")
        
    except Exception as e:
        logger.error(f"Error invalidating cold-start cache: {e}")

# Load persistent cache saat startup
_load_persistent_cache()

# Pydantic models (tetap sama)
class RecommendationRequest(BaseModel):
    user_id: str
    model_type: str = "hybrid"
    num_recommendations: int = Field(10, ge=1, le=100)
    exclude_known: bool = True
    category: Optional[str] = None
    chain: Optional[str] = None
    user_interests: Optional[List[str]] = None
    risk_tolerance: Optional[str] = "medium"
    strict_filter: bool = False

class ProjectResponse(BaseModel):
    id: str
    name: Optional[str] = None
    symbol: Optional[str] = None
    image: Optional[str] = None
    current_price: Optional[float] = None
    price_change_24h: Optional[float] = None
    price_change_percentage_7d_in_currency: Optional[float] = None
    market_cap: Optional[float] = None
    total_volume: Optional[float] = None
    popularity_score: Optional[float] = None
    trend_score: Optional[float] = None
    category: Optional[str] = None
    chain: Optional[str] = None
    recommendation_score: float
    filter_match: Optional[str] = None 

class RecommendationResponse(BaseModel):
    user_id: str
    model_type: str
    recommendations: List[ProjectResponse]
    timestamp: datetime
    is_cold_start: bool = False
    category_filter: Optional[str] = None
    chain_filter: Optional[str] = None
    execution_time: float
    exact_match_count: int = 0
    cache_hit: bool = False  # ⚡ TAMBAHAN: Info apakah dari cache
    cold_start_invalidated: bool = False  # ⚡ TAMBAHAN: Info apakah cache cold-start di-invalidate

# Load models function (tetap sama)
def load_models_on_startup():
    """Load recommendation models on API startup"""
    logger.info("Loading recommendation models on startup...")
    
    try:
        # Check for FECF model files
        fecf_files = [f for f in os.listdir(MODELS_DIR) 
                     if f.startswith("fecf_model_") and f.endswith(".pkl")]
        
        if fecf_files:
            latest_fecf = sorted(fecf_files)[-1]
            fecf_path = os.path.join(MODELS_DIR, latest_fecf)
            
            logger.info(f"Loading FECF model from {fecf_path}")
            model = FeatureEnhancedCF()
            if model.load_data():
                if model.load_model(fecf_path):
                    _models["fecf"] = model
                    logger.info("FECF model loaded successfully")
                else:
                    logger.error(f"Failed to load FECF model from {fecf_path}")
            else:
                logger.error("Failed to load data for FECF model")
        else:
            logger.warning("No FECF model files found")
            
        # Check for NCF model file
        ncf_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
        if os.path.exists(ncf_path):
            logger.info(f"Loading NCF model from {ncf_path}")
            model = NCFRecommender()
            if model.load_data():
                if model.load_model(ncf_path):
                    _models["ncf"] = model
                    logger.info("NCF model loaded successfully")
                else:
                    logger.error(f"Failed to load NCF model from {ncf_path}")
            else:
                logger.error("Failed to load data for NCF model")
        else:
            logger.warning(f"NCF model file not found at {ncf_path}")
            
        # Check for Hybrid model files
        hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                      if f.startswith("hybrid_model_") and f.endswith(".pkl")]
        
        if hybrid_files:
            latest_hybrid = sorted(hybrid_files)[-1]
            hybrid_path = os.path.join(MODELS_DIR, latest_hybrid)
            
            logger.info(f"Loading Hybrid model from {hybrid_path}")
            model = HybridRecommender()
            if model.load_data():
                if model.load_model(hybrid_path):
                    _models["hybrid"] = model
                    logger.info("Hybrid model loaded successfully")
                else:
                    logger.error(f"Failed to load Hybrid model from {hybrid_path}")
            else:
                logger.error("Failed to load data for Hybrid model")
        else:
            logger.warning("No Hybrid model files found")
            
        logger.info(f"Models loaded on startup: {list(_models.keys())}")
        
    except Exception as e:
        logger.error(f"Error loading models on startup: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())

load_models_on_startup()

# Helper functions (get_model tetap sama)
def get_model(model_type: str) -> Any:
    global _models
    
    if model_type not in ["fecf", "ncf", "hybrid"]:
        raise ValueError(f"Invalid model type: {model_type}")
    
    if _models[model_type] is not None:
        return _models[model_type]
    
    try:
        if model_type == "fecf":
            from src.models.alt_fecf import FeatureEnhancedCF
            model = FeatureEnhancedCF()
        elif model_type == "ncf":
            from src.models.ncf import NCFRecommender
            model = NCFRecommender()
        else:  # hybrid
            from src.models.hybrid import HybridRecommender
            model = HybridRecommender()
        
        logger.info(f"Loading data for {model_type} model")
        data_loaded = model.load_data()
        if not data_loaded:
            logger.error(f"Failed to load data for {model_type} model")
            return None
            
        model_loaded = False
        if model_type == "fecf":
            fecf_files = [f for f in os.listdir(MODELS_DIR) 
                        if f.startswith("fecf_model_") and f.endswith(".pkl")]
            if fecf_files:
                latest_fecf = sorted(fecf_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_fecf)
                logger.info(f"Loading {model_type} model from {model_path}")
                model_loaded = model.load_model(model_path)
        elif model_type == "ncf":
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            if os.path.exists(model_path):
                logger.info(f"Loading {model_type} model from {model_path}")
                model_loaded = model.load_model(model_path)
        else:  # hybrid
            hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                         if f.startswith("hybrid_model_") and f.endswith(".pkl")]
            if hybrid_files:
                latest_hybrid = sorted(hybrid_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_hybrid)
                logger.info(f"Loading {model_type} model from {model_path}")
                model_loaded = model.load_model(model_path)
        
        if model_loaded:
            logger.info(f"{model_type} model loaded successfully")
            _models[model_type] = model
            return model
        else:
            logger.error(f"Failed to load {model_type} model file")
            return None
            
    except Exception as e:
        logger.error(f"Error initializing {model_type} model: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        return None

def is_cold_start_user(user_id: str, model: Any) -> bool:
    """⚡ PERBAIKAN: Enhanced cold-start detection dengan proper matrix check"""
    
    # Check basic condition - apakah user ada di trained matrix
    user_in_matrix = False
    if hasattr(model, 'user_item_matrix') and model.user_item_matrix is not None:
        user_in_matrix = user_id in model.user_item_matrix.index
    
    # ⚡ PERBAIKAN: Jika user TIDAK ada di matrix, SELALU cold-start
    # Meskipun ada recent interactions, matrix belum ter-update
    if not user_in_matrix:
        logger.info(f"User {user_id} not in trained matrix, treating as cold-start (matrix needs retrain)")
        return True
    
    # Jika user ada di matrix, tidak cold-start
    return False

# Main recommendation endpoint
@router.post("/projects", response_model=RecommendationResponse)
async def recommend_projects(request: RecommendationRequest):
    start_time = datetime.now()
    logger.info(f"Recommendation request for user {request.user_id} using {request.model_type} model")
    
    # Cache checking (tetap sama)
    cache = _user_recommendations_cache.get(request.model_type, {})
    cache_key = f"{request.user_id}_{request.num_recommendations}_{request.category}_{request.chain}"
    
    cache_hit = False
    cold_start_invalidated = False
    
    if cache_key in cache:
        cache_entry = cache[cache_key]
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached recommendations for {cache_key}")
            
            cached_response = cache_entry['response']
            cached_response.timestamp = datetime.now()
            cached_response.execution_time = (datetime.now() - start_time).total_seconds()
            cached_response.cache_hit = True
            
            return cached_response

    try:
        # Get appropriate model
        model = get_model(request.model_type)
        if not model:
            raise HTTPException(status_code=500, detail=f"Failed to load {request.model_type} model")
        
        # ⚡ PERBAIKAN: Simplified cold-start detection
        is_cold_start = is_cold_start_user(request.user_id, model)
        user_interaction_count = 0
        
        # ⚡ PERBAIKAN: Hanya hitung interactions jika user ada di matrix
        if not is_cold_start and hasattr(model, 'user_item_matrix') and model.user_item_matrix is not None:
            try:
                user_interactions = model.user_item_matrix.loc[request.user_id]
                user_interaction_count = (user_interactions > 0).sum()
            except KeyError:
                # ⚡ SAFETY: Jika user tidak ada di matrix, fallback ke cold-start
                logger.warning(f"User {request.user_id} missing from matrix, fallback to cold-start")
                is_cold_start = True
                user_interaction_count = 0
        
        # ⚡ TAMBAHAN: Check recent interactions untuk informasi saja (tidak affect logic)
        has_recent_interactions = _check_recent_interactions(request.user_id)
        if has_recent_interactions and is_cold_start:
            logger.info(f"User {request.user_id} has recent interactions but matrix not updated - recommend model retrain")
            # Bisa add flag untuk monitoring
            cold_start_invalidated = True
        
        # Determine cache TTL
        if is_cold_start:
            cache_ttl = _cache_ttl["cold_start"]  # 3 menit
        elif user_interaction_count < 10:
            cache_ttl = _cache_ttl["low_activity"]  # 4 menit
        elif user_interaction_count < 50:
            cache_ttl = _cache_ttl["normal"]  # 5 menit
        else:
            cache_ttl = _cache_ttl["active"]  # 5 menit

        # Generate recommendations
        if is_cold_start:
            logger.info(f"Cold-start user detected: {request.user_id}")
            
            if hasattr(model, 'get_cold_start_recommendations'):
                recommendations = model.get_cold_start_recommendations(
                    user_interests=request.user_interests,
                    n=request.num_recommendations
                )
            else:
                logger.warning(f"Model {request.model_type} tidak memiliki method get_cold_start_recommendations, fallback ke popular_projects")
                recommendations = model.get_popular_projects(n=request.num_recommendations)
        else:
            # ⚡ PERBAIKAN: Tambah safety check sebelum akses matrix
            try:
                # Regular recommendations logic (sama seperti sebelumnya)
                if request.category and request.chain:
                    logger.info(f"Filtering by both category '{request.category}' and chain '{request.chain}'")
                    
                    if hasattr(model, 'get_recommendations_by_category_and_chain'):
                        recommendations = model.get_recommendations_by_category_and_chain(
                            request.user_id, 
                            request.category,
                            request.chain,
                            n=request.num_recommendations,
                            strict=request.strict_filter
                        )
                    elif hasattr(model, 'get_recommendations_by_category'):
                        if 'chain' in inspect.signature(model.get_recommendations_by_category).parameters:
                            recommendations = model.get_recommendations_by_category(
                                request.user_id, 
                                request.category,
                                n=request.num_recommendations,
                                chain=request.chain,
                                strict=request.strict_filter
                            )
                        else:
                            category_recs = model.get_recommendations_by_category(
                                request.user_id, 
                                request.category,
                                n=request.num_recommendations * 3
                            )
                            
                            chain_filtered = []
                            for rec in category_recs:
                                if 'chain' in rec and rec['chain'] and request.chain.lower() in str(rec['chain']).lower():
                                    rec['filter_match'] = 'exact'
                                    chain_filtered.append(rec)
                            
                            recommendations = chain_filtered[:request.num_recommendations]
                            
                            if len(recommendations) < request.num_recommendations // 2:
                                logger.warning(f"Too few results after chain filtering ({len(recommendations)}). Adding some category-only results.")
                                remaining = request.num_recommendations - len(recommendations)
                                existing_ids = [rec['id'] for rec in recommendations]
                                additional = []
                                for rec in category_recs:
                                    if rec['id'] not in existing_ids:
                                        rec['filter_match'] = 'category_only'
                                        additional.append(rec)
                                        if len(additional) >= remaining:
                                            break
                                recommendations.extend(additional)
                    else:
                        logger.warning(f"Model {request.model_type} doesn't support category and chain filtering. Using standard recommendations.")
                        recommendations = model.recommend_projects(request.user_id, n=request.num_recommendations)
                elif request.category:
                    if hasattr(model, 'get_recommendations_by_category'):
                        recommendations = model.get_recommendations_by_category(
                            request.user_id, 
                            request.category, 
                            n=request.num_recommendations,
                            strict=request.strict_filter
                        )
                    else:
                        logger.warning(f"Model {request.model_type} doesn't support category filtering. Using standard recommendations.")
                        recommendations = model.recommend_projects(request.user_id, n=request.num_recommendations)
                elif request.chain:
                    if hasattr(model, 'get_recommendations_by_chain'):
                        recommendations = model.get_recommendations_by_chain(
                            request.user_id, 
                            request.chain, 
                            n=request.num_recommendations,
                            strict=request.strict_filter
                        )
                    else:
                        logger.warning(f"Model {request.model_type} doesn't support chain filtering. Using standard recommendations.")
                        recommendations = model.recommend_projects(request.user_id, n=request.num_recommendations)
                else:
                    recommendations = model.recommend_projects(
                        request.user_id, 
                        n=request.num_recommendations
                    )
            except KeyError as e:
                # ⚡ SAFETY: Jika ada KeyError saat generate recommendations, fallback ke cold-start
                logger.error(f"KeyError during recommendation generation for user {request.user_id}: {e}")
                logger.info(f"Falling back to cold-start recommendations")
                
                is_cold_start = True  # Update status
                if hasattr(model, 'get_cold_start_recommendations'):
                    recommendations = model.get_cold_start_recommendations(
                        user_interests=request.user_interests,
                        n=request.num_recommendations
                    )
                else:
                    recommendations = model.get_popular_projects(n=request.num_recommendations)
        
        # Process recommendations (tetap sama seperti sebelumnya)
        project_responses = []
        exact_match_count = 0
        
        for rec in recommendations:
            try:
                clean_rec = sanitize_project_data(rec)

                if 'filter_match' in clean_rec and clean_rec['filter_match'] == 'exact':
                    exact_match_count += 1
                
                if 'recommendation_score' in clean_rec:
                    clean_rec['recommendation_score'] = float(clean_rec['recommendation_score'])
                
                project_responses.append(
                    ProjectResponse(
                        id=clean_rec.get('id'),
                        name=clean_rec.get('name'),
                        symbol=clean_rec.get('symbol'),
                        image=clean_rec.get('image'),
                        current_price=clean_rec.get('current_price'),
                        price_change_24h=clean_rec.get('price_change_24h'),
                        price_change_percentage_7d_in_currency=clean_rec.get('price_change_percentage_7d_in_currency'),
                        market_cap=clean_rec.get('market_cap'),
                        total_volume=clean_rec.get('total_volume'),
                        popularity_score=clean_rec.get('popularity_score'),
                        trend_score=clean_rec.get('trend_score'),
                        category=clean_rec.get('primary_category', clean_rec.get('category')),
                        chain=clean_rec.get('chain'),
                        recommendation_score=clean_rec.get('recommendation_score', 0.5),
                        filter_match=clean_rec.get('filter_match')
                    )
                )
            except Exception as e:
                logger.warning(f"Error processing recommendation item: {e}. Skipping item.")
                continue
        
        logger.info(f"Found {exact_match_count} exact matches out of {len(project_responses)} recommendations")
        
        response = RecommendationResponse(
            user_id=request.user_id,
            model_type=request.model_type,
            recommendations=project_responses,
            timestamp=datetime.now(),
            is_cold_start=is_cold_start,
            category_filter=request.category,
            chain_filter=request.chain,
            execution_time=(datetime.now() - start_time).total_seconds(),
            exact_match_count=exact_match_count,
            cache_hit=cache_hit,
            cold_start_invalidated=cold_start_invalidated
        )
        
        # Store in cache
        if request.user_id not in _user_recommendations_cache:
            _user_recommendations_cache[request.model_type] = {}
            
        _user_recommendations_cache[request.model_type][cache_key] = {
            'response': response,
            'expires': datetime.now() + timedelta(seconds=cache_ttl)
        }
        
        # Save persistent cache
        try:
            _save_persistent_cache()
        except Exception as e:
            logger.warning(f"Failed to save persistent cache: {e}")
        
        logger.info(f"Cached response for {cache_key} with TTL {cache_ttl} seconds")
        
        return response
        
    except Exception as e:
        logger.error(f"Error generating recommendations: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Recommendation error: {str(e)}")

# Sanitize function (tetap sama)
def sanitize_project_data(project_dict: Dict[str, Any]) -> Dict[str, Any]:
    """Sanitize project data dengan validasi score yang ketat"""
    result = {}
    
    if not isinstance(project_dict, dict):
        logger.warning(f"Input bukan dictionary: {type(project_dict)}")
        return {"id": "unknown", "recommendation_score": 0.5}
    
    for key, value in project_dict.items():
        if value is None:
            result[key] = None
            continue
        
        try:
            if isinstance(value, np.ndarray):
                if value.size == 0:
                    result[key] = None
                elif value.size == 1:
                    if np.issubdtype(value.dtype, np.floating):
                        result[key] = float(value.item())
                    elif np.issubdtype(value.dtype, np.integer):
                        result[key] = int(value.item())
                    else:
                        result[key] = value.item()
                else:
                    if value.dtype.kind == 'f' and np.isnan(value).all():
                        result[key] = None
                    else:
                        result[key] = value.tolist()
            
            elif isinstance(value, np.number):
                if np.issubdtype(type(value), np.floating):
                    result[key] = float(value)
                else:
                    result[key] = int(value)
            
            elif pd.api.types.is_scalar(value) and pd.isna(value):
                result[key] = None
            
            elif isinstance(value, str) and key in ['category', 'primary_category'] and (value.startswith('[') or value.startswith('"[')):
                try:
                    cleaned_value = value
                    if cleaned_value.startswith('"[') and cleaned_value.endswith(']"'):
                        cleaned_value = cleaned_value[1:-1]
                    
                    parsed = json.loads(cleaned_value)
                    if isinstance(parsed, list) and parsed:
                        result[key] = parsed[0]
                    else:
                        result[key] = cleaned_value
                except:
                    result[key] = value
            
            elif isinstance(value, (list, tuple)) and key in ['category', 'primary_category']:
                if value:
                    result[key] = value[0]
                else:
                    result[key] = 'unknown'
            
            else:
                result[key] = value
                
        except Exception as e:
            logger.warning(f"Error processing field {key}: {str(e)}")
            if key == 'id':
                result[key] = 'unknown'
            elif key == 'recommendation_score':
                result[key] = 0.5
            else:
                result[key] = None
    
    # Validasi score fields
    score_fields = ['trend_score', 'popularity_score', 'developer_activity_score', 
                   'social_engagement_score', 'maturity_score']
    
    for field in score_fields:
        if field in result and result[field] is not None:
            try:
                score_value = float(result[field])
                result[field] = float(np.clip(score_value, 0.0, 100.0))
                
                if score_value > 100.0 or score_value < 0.0:
                    logger.warning(f"Score {field} di-clip: {score_value} -> {result[field]}")
                    
            except (ValueError, TypeError):
                logger.warning(f"Invalid {field} value: {result[field]}, setting to 0")
                result[field] = 0.0
    
    # Final safety check
    if 'primary_category' in result and isinstance(result['primary_category'], (list, tuple)):
        if result['primary_category']:
            result['primary_category'] = result['primary_category'][0]
        else:
            result['primary_category'] = 'unknown'
    
    if 'category' in result and isinstance(result['category'], (list, tuple)):
        if result['category']:
            result['category'] = result['category'][0]
        else:
            result['category'] = 'unknown'
    
    # Safety check for recommendation_score
    if 'recommendation_score' in result:
        if isinstance(result['recommendation_score'], (np.ndarray, list, tuple)):
            if len(result['recommendation_score']) > 0:
                value = result['recommendation_score'][0]
                try:
                    result['recommendation_score'] = float(np.clip(value, 0.0, 1.0))
                except:
                    result['recommendation_score'] = 0.5
            else:
                result['recommendation_score'] = 0.5
        elif result['recommendation_score'] is None:
            result['recommendation_score'] = 0.5
        else:
            try:
                rec_score = float(result['recommendation_score'])
                result['recommendation_score'] = float(np.clip(rec_score, 0.0, 1.0))
                
                if rec_score > 1.0 or rec_score < 0.0:
                    logger.warning(f"Recommendation score di-clip: {rec_score} -> {result['recommendation_score']}")
                    
            except:
                result['recommendation_score'] = 0.5
    
    return result

# Endpoints lainnya (trending, popular, similar) tetap sama...
@router.get("/trending", response_model=List[ProjectResponse])
async def get_trending_projects(
    limit: int = Query(10, ge=1, le=100),
    model_type: str = Query("fecf", enum=["fecf", "ncf", "hybrid"])
):
    try:
        model = get_model(model_type)
        trending = model.get_trending_projects(n=limit)
        
        project_responses = []
        for rec in trending:
            try:
                clean_rec = sanitize_project_data(rec)
                
                if 'trend_score' in clean_rec and clean_rec['trend_score'] is not None:
                    trend_score = float(clean_rec['trend_score'])
                    clean_rec['trend_score'] = float(np.clip(trend_score, 0.0, 100.0))
                    
                    if trend_score > 100.0:
                        logger.warning(f"Trending project {clean_rec.get('id', 'unknown')} had trend_score {trend_score} > 100, clipped to 100")
                
                for field in ['current_price', 'market_cap', 'total_volume', 'price_change_24h', 
                            'price_change_percentage_7d_in_currency', 'popularity_score', 
                            'trend_score', 'recommendation_score']:
                    if field in clean_rec and clean_rec[field] is not None:
                        if isinstance(clean_rec[field], np.number):
                            clean_rec[field] = float(clean_rec[field])
                
                project_responses.append(
                    ProjectResponse(
                        id=clean_rec.get('id'),
                        name=clean_rec.get('name'),
                        symbol=clean_rec.get('symbol'),
                        image=clean_rec.get('image'),
                        current_price=clean_rec.get('current_price'),
                        price_change_24h=clean_rec.get('price_change_24h'),
                        price_change_percentage_7d_in_currency=clean_rec.get('price_change_percentage_7d_in_currency'),
                        market_cap=clean_rec.get('market_cap'),
                        total_volume=clean_rec.get('total_volume'),
                        popularity_score=clean_rec.get('popularity_score'),
                        trend_score=clean_rec.get('trend_score'),
                        category=clean_rec.get('primary_category', clean_rec.get('category')),
                        chain=clean_rec.get('chain'),
                        recommendation_score=clean_rec.get('recommendation_score', 0.5)
                    )
                )
            except Exception as e:
                logger.warning(f"Error processing trending item: {e}. Skipping item.")
                continue
                
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting trending projects: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/popular", response_model=List[ProjectResponse])
async def get_popular_projects(
    limit: int = Query(10, ge=1, le=100),
    model_type: str = Query("fecf", enum=["fecf", "ncf", "hybrid"]),
    sort: str = Query("popularity_score", enum=["popularity_score", "market_cap", "trend_score"]),
    order: str = Query("desc", enum=["desc", "asc"])
):
    try:
        model = get_model(model_type)
        popular = model.get_popular_projects(n=limit)
        
        if sort == "popularity_score":
            popular = sorted(popular, key=lambda x: x.get('popularity_score', 0), reverse=(order == "desc"))
        elif sort == "market_cap":
            popular = sorted(popular, key=lambda x: x.get('market_cap', 0), reverse=(order == "desc"))
        elif sort == "trend_score":
            popular = sorted(popular, key=lambda x: x.get('trend_score', 0), reverse=(order == "desc"))
        
        project_responses = []
        for rec in popular:
            try:
                clean_rec = sanitize_project_data(rec)
                
                if 'popularity_score' in clean_rec and clean_rec['popularity_score'] is not None:
                    pop_score = float(clean_rec['popularity_score'])
                    clean_rec['popularity_score'] = float(np.clip(pop_score, 0.0, 100.0))
                    
                    if pop_score > 100.0:
                        logger.warning(f"Popular project {clean_rec.get('id', 'unknown')} had popularity_score {pop_score} > 100, clipped to 100")
                
                for field in ['current_price', 'market_cap', 'total_volume', 'price_change_24h', 
                            'price_change_percentage_7d_in_currency', 'popularity_score', 
                            'trend_score', 'recommendation_score']:
                    if field in clean_rec and clean_rec[field] is not None:
                        if isinstance(clean_rec[field], np.number):
                            clean_rec[field] = float(clean_rec[field])
                
                project_responses.append(
                    ProjectResponse(
                        id=clean_rec.get('id'),
                        name=clean_rec.get('name'),
                        symbol=clean_rec.get('symbol'),
                        image=clean_rec.get('image'),
                        current_price=clean_rec.get('current_price'),
                        price_change_24h=clean_rec.get('price_change_24h'),
                        price_change_percentage_7d_in_currency=clean_rec.get('price_change_percentage_7d_in_currency'),
                        market_cap=clean_rec.get('market_cap'),
                        total_volume=clean_rec.get('total_volume'),
                        popularity_score=clean_rec.get('popularity_score'),
                        trend_score=clean_rec.get('trend_score'),
                        category=clean_rec.get('primary_category', clean_rec.get('category')),
                        chain=clean_rec.get('chain'),
                        recommendation_score=clean_rec.get('recommendation_score', 0.5)
                    )
                )
            except Exception as e:
                logger.warning(f"Error processing popular item: {e}. Skipping item.")
                continue
                
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting popular projects: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/similar/{project_id}", response_model=List[ProjectResponse])
async def get_similar_projects(
    project_id: str = Path(..., description="Project ID"),
    limit: int = Query(10, ge=1, le=100),
    model_type: str = Query("fecf", enum=["fecf", "ncf", "hybrid"])
):
    try:
        model = get_model(model_type)
        
        if not hasattr(model, 'get_similar_projects'):
            model = get_model("fecf")
        
        similar = model.get_similar_projects(project_id, n=limit)
        
        project_responses = []
        for rec in similar:
            try:
                clean_rec = sanitize_project_data(rec)
                
                for field in ['current_price', 'market_cap', 'total_volume', 'price_change_24h', 
                            'price_change_percentage_7d_in_currency', 'popularity_score', 
                            'trend_score', 'recommendation_score', 'similarity_score']:
                    if field in clean_rec and clean_rec[field] is not None:
                        if isinstance(clean_rec[field], np.number):
                            clean_rec[field] = float(clean_rec[field])
                
                project_responses.append(
                    ProjectResponse(
                        id=clean_rec.get('id'),
                        name=clean_rec.get('name'),
                        symbol=clean_rec.get('symbol'),
                        image=clean_rec.get('image'),
                        current_price=clean_rec.get('current_price'),
                        price_change_24h=clean_rec.get('price_change_24h'),
                        price_change_percentage_7d_in_currency=clean_rec.get('price_change_percentage_7d_in_currency'),
                        market_cap=clean_rec.get('market_cap'),
                        total_volume=clean_rec.get('total_volume'),
                        popularity_score=clean_rec.get('popularity_score'),
                        trend_score=clean_rec.get('trend_score'),
                        category=clean_rec.get('primary_category', clean_rec.get('category')),
                        chain=clean_rec.get('chain'),
                        recommendation_score=clean_rec.get('similarity_score', clean_rec.get('recommendation_score', 0.5))
                    )
                )
            except Exception as e:
                logger.warning(f"Error processing similar item: {e}. Skipping item.")
                continue
            
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting similar projects: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# ⚡ PERBAIKAN: Enhanced cache clear dengan persistent storage
@router.post("/cache/clear")
async def clear_cache(full_clear: bool = False):
    global _user_recommendations_cache, _models, _cold_start_tracking
    
    try:
        # Count items before clearing
        total_items = sum(len(cache) for cache in _user_recommendations_cache.values())
        tracking_items = len(_cold_start_tracking)
        
        # Clear recommendation cache
        for model_type in _user_recommendations_cache:
            _user_recommendations_cache[model_type] = {}
        
        # Clear cold-start tracking
        _cold_start_tracking = {}
        
        # ⚡ TAMBAHAN: Clear persistent cache files
        try:
            for cache_type in ["fecf", "ncf", "hybrid"]:
                cache_file = _get_cache_file_path(f"recommendations_{cache_type}")
                if os.path.exists(cache_file):
                    os.remove(cache_file)
            
            tracking_file = _get_cache_file_path("cold_start_tracking")
            if os.path.exists(tracking_file):
                os.remove(tracking_file)
                
            logger.info("Persistent cache files cleared")
        except Exception as e:
            logger.warning(f"Error clearing persistent cache files: {e}")
        
        # Optionally clear loaded models
        if full_clear:
            for model_type in _models:
                _models[model_type] = None
            return {"message": f"All caches cleared ({total_items} recommendations, {tracking_items} tracking entries, and all loaded models)"}
        
        return {"message": f"All caches cleared ({total_items} recommendations, {tracking_items} tracking entries)"}
    
    except Exception as e:
        logger.error(f"Error clearing cache: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")