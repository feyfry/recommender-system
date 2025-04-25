"""
API endpoints untuk rekomendasi proyek Web3 (dengan perbaikan nama field image)
"""

import os
import logging
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import pandas as pd
from fastapi import APIRouter, HTTPException, Query, Depends, Body, Path
from pydantic import BaseModel, Field

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

# Model imports
from src.models.alt_fecf import FeatureEnhancedCF
from src.models.ncf import NCFRecommender
from src.models.hybrid import HybridRecommender
from config import MODELS_DIR

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

# Ditambahkan: cache per-model untuk menyimpan rekomendasi untuk setiap user
_user_recommendations_cache = {
    "fecf": {},
    "ncf": {},
    "hybrid": {}
}

# Ditambahkan: cache waktu kedaluwarsa yang lebih lama untuk pengguna yang jarang berubah
_cache_ttl = {
    "cold_start": 86400,  # 24 jam untuk cold-start user
    "low_activity": 43200,  # 12 jam untuk pengguna dengan aktivitas rendah (<10 interaksi)
    "normal": 7200,     # 2 jam untuk pengguna normal (10-50 interaksi)
    "active": 3600      # 1 jam untuk pengguna sangat aktif (>50 interaksi)
}

# Pydantic models
class RecommendationRequest(BaseModel):
    user_id: str
    model_type: str = "hybrid"
    num_recommendations: int = Field(10, ge=1, le=100)
    exclude_known: bool = True
    category: Optional[str] = None
    chain: Optional[str] = None
    user_interests: Optional[List[str]] = None
    risk_tolerance: Optional[str] = "medium"

class ProjectResponse(BaseModel):
    id: str
    name: Optional[str] = None
    symbol: Optional[str] = None
    image: Optional[str] = None  # Menggunakan image, bukan image_url
    price_usd: Optional[float] = None
    price_change_24h: Optional[float] = None
    price_change_7d: Optional[float] = None
    market_cap: Optional[float] = None
    volume_24h: Optional[float] = None
    popularity_score: Optional[float] = None
    trend_score: Optional[float] = None
    category: Optional[str] = None
    chain: Optional[str] = None  # Must be optional to handle NaN
    recommendation_score: float

class RecommendationResponse(BaseModel):
    user_id: str
    model_type: str
    recommendations: List[ProjectResponse]
    timestamp: datetime
    is_cold_start: bool = False
    category_filter: Optional[str] = None
    chain_filter: Optional[str] = None
    execution_time: float

def load_models_on_startup():
    """
    Load recommendation models on API startup
    """
    logger.info("Loading recommendation models on startup...")
    
    # Find model files
    try:
        # Check for FECF model files
        fecf_files = [f for f in os.listdir(MODELS_DIR) 
                     if f.startswith("fecf_model_") and f.endswith(".pkl")]
        
        if fecf_files:
            latest_fecf = sorted(fecf_files)[-1]
            fecf_path = os.path.join(MODELS_DIR, latest_fecf)
            
            # Initialize and load FECF model
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
            # Initialize and load NCF model
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
            
            # Initialize and load Hybrid model
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

# Add this line right after the router declaration
load_models_on_startup()

# Helper functions
def get_model(model_type: str) -> Any:
    """
    Get or initialize model based on type with aggressive caching
    
    Args:
        model_type: Type of model ('fecf', 'ncf', 'hybrid')
        
    Returns:
        Model instance
    """
    global _models
    
    if model_type not in ["fecf", "ncf", "hybrid"]:
        raise ValueError(f"Invalid model type: {model_type}")
    
    # Return existing model if available
    if _models[model_type] is not None:
        return _models[model_type]
    
    # Initialize model jika belum ada
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
        
        # Load data - dilakukan sekali saja saat startup
        logger.info(f"Loading data for {model_type} model")
        data_loaded = model.load_data()
        if not data_loaded:
            logger.error(f"Failed to load data for {model_type} model")
            return None
            
        # Find and load model file
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
            # Store model for later use
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
    """
    Check if user is a cold-start user
    
    Args:
        user_id: User ID
        model: Recommender model
        
    Returns:
        bool: True if user is cold-start
    """
    if hasattr(model, 'user_item_matrix') and model.user_item_matrix is not None:
        return user_id not in model.user_item_matrix.index
    
    return True

def get_cache_key(request: RecommendationRequest) -> str:
    """Generate cache key from request parameters"""
    return f"{request.user_id}:{request.model_type}:{request.num_recommendations}:{request.category}:{request.chain}"

def sanitize_project_data(project_dict: Dict[str, Any]) -> Dict[str, Any]:
    """
    Clean project data dan memetakan field untuk kompatibilitas API
    
    Args:
        project_dict: Project data dictionary
        
    Returns:
        dict: Cleaned project data
    """
    result = {}
    
    for key, value in project_dict.items():
        # Handle NaN values
        if pd.isna(value):
            result[key] = None
        else:
            result[key] = value
    
    # Mapping kolom untuk kompatibilitas API
    # current_price -> price_usd, total_volume -> volume_24h
    if 'current_price' in result and 'price_usd' not in result:
        result['price_usd'] = result['current_price']
        
    if 'total_volume' in result and 'volume_24h' not in result:
        result['volume_24h'] = result['total_volume']
    
    return result

# Routes
@router.post("/projects", response_model=RecommendationResponse)
async def recommend_projects(request: RecommendationRequest):
    """
    Get project recommendations for a user with aggressive caching
    """
    start_time = datetime.now()
    logger.info(f"Recommendation request for user {request.user_id} using {request.model_type} model")
    
    # OPTIMIZATION: Check user cache first
    cache = _user_recommendations_cache.get(request.model_type, {})
    cache_key = f"{request.user_id}_{request.num_recommendations}_{request.category}_{request.chain}"
    
    if cache_key in cache:
        cache_entry = cache[cache_key]
        # Check if cache is still valid
        if datetime.now() < cache_entry['expires']:
            logger.info(f"Returning cached recommendations for {cache_key}")
            
            # Update timestamp and execution time
            cache_entry['response'].timestamp = datetime.now()
            cache_entry['response'].execution_time = (datetime.now() - start_time).total_seconds()
            
            return cache_entry['response']
    
    try:
        # Get appropriate model
        model = get_model(request.model_type)
        if not model:
            raise HTTPException(status_code=500, detail=f"Failed to load {request.model_type} model")
        
        # Check if this is a cold-start user
        is_cold_start = False
        user_interaction_count = 0
        
        if hasattr(model, 'user_item_matrix') and model.user_item_matrix is not None:
            is_cold_start = request.user_id not in model.user_item_matrix.index
            
            if not is_cold_start:
                # Count user interactions for TTL decisions
                user_interactions = model.user_item_matrix.loc[request.user_id]
                user_interaction_count = (user_interactions > 0).sum()
        
        # Determine cache TTL based on user activity
        if is_cold_start:
            cache_ttl = _cache_ttl["cold_start"]
        elif user_interaction_count < 10:
            cache_ttl = _cache_ttl["low_activity"]
        elif user_interaction_count < 50:
            cache_ttl = _cache_ttl["normal"]
        else:
            cache_ttl = _cache_ttl["active"]
        
        # Get recommendations
        if is_cold_start:
            logger.info(f"Cold-start user detected: {request.user_id}")
            
            if request.model_type == "fecf" or request.model_type == "hybrid":
                recommendations = model.get_cold_start_recommendations(
                    user_interests=request.user_interests,
                    n=request.num_recommendations
                )
            else:  # NCF doesn't have specialized cold-start handling
                # Fallback to popular projects
                recommendations = get_model("fecf").get_popular_projects(n=request.num_recommendations)
        else:
            # Regular recommendations
            if request.category:
                # Category-filtered recommendations
                if hasattr(model, 'get_recommendations_by_category'):
                    recommendations = model.get_recommendations_by_category(
                        request.user_id, 
                        request.category, 
                        n=request.num_recommendations
                    )
                else:
                    # Fallback
                    recommendations = model.recommend_projects(request.user_id, n=request.num_recommendations)
            elif request.chain:
                # Chain-filtered recommendations
                if hasattr(model, 'get_recommendations_by_chain'):
                    recommendations = model.get_recommendations_by_chain(
                        request.user_id, 
                        request.chain, 
                        n=request.num_recommendations
                    )
                else:
                    # Fallback
                    recommendations = model.recommend_projects(request.user_id, n=request.num_recommendations)
            else:
                # Standard recommendations
                recommendations = model.recommend_projects(
                    request.user_id, 
                    n=request.num_recommendations
                )
        
        # Create response with sanitized data
        project_responses = []
        
        for rec in recommendations:
            # Sanitize data (handle NaN values)
            clean_rec = sanitize_project_data(rec)
            
            project_responses.append(
                ProjectResponse(
                    id=clean_rec.get('id'),
                    name=clean_rec.get('name'),
                    symbol=clean_rec.get('symbol'),
                    image=clean_rec.get('image'),
                    price_usd=clean_rec.get('price_usd', clean_rec.get('current_price')),
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_percentage_7d_in_currency'),
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h', clean_rec.get('total_volume')),
                    popularity_score=clean_rec.get('popularity_score'),
                    trend_score=clean_rec.get('trend_score'),
                    category=clean_rec.get('primary_category', clean_rec.get('category')),
                    chain=clean_rec.get('chain'),
                    recommendation_score=clean_rec.get('recommendation_score', 0.5)
                )
            )
        
        response = RecommendationResponse(
            user_id=request.user_id,
            model_type=request.model_type,
            recommendations=project_responses,
            timestamp=datetime.now(),
            is_cold_start=is_cold_start,
            category_filter=request.category,
            chain_filter=request.chain,
            execution_time=(datetime.now() - start_time).total_seconds()
        )
        
        # OPTIMIZATION: Store in user-specific cache with appropriate TTL
        if not request.user_id in _user_recommendations_cache:
            _user_recommendations_cache[request.model_type] = {}
            
        _user_recommendations_cache[request.model_type][cache_key] = {
            'response': response,
            'expires': datetime.now() + timedelta(seconds=cache_ttl)
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Error generating recommendations: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Recommendation error: {str(e)}")

@router.get("/trending", response_model=List[ProjectResponse])
async def get_trending_projects(
    limit: int = Query(10, ge=1, le=100),
    model_type: str = Query("fecf", enum=["fecf", "ncf", "hybrid"])
):
    """
    Get trending projects based on trend score
    """
    try:
        model = get_model(model_type)
        trending = model.get_trending_projects(n=limit)
        
        # Create response with sanitized data
        project_responses = []
        for rec in trending:
            # Sanitize data (handle NaN values)
            clean_rec = sanitize_project_data(rec)
            
            project_responses.append(
                ProjectResponse(
                    id=clean_rec.get('id'),
                    name=clean_rec.get('name'),
                    symbol=clean_rec.get('symbol'),
                    image=clean_rec.get('image'),  # Tetap gunakan field image
                    price_usd=clean_rec.get('price_usd', clean_rec.get('current_price')),  # Ambil dari current_price jika price_usd tidak ada
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_percentage_7d_in_currency'),  # Gunakan field asli
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h', clean_rec.get('total_volume')),  # Ambil dari total_volume jika volume_24h tidak ada
                    popularity_score=clean_rec.get('popularity_score'),
                    trend_score=clean_rec.get('trend_score'),
                    category=clean_rec.get('primary_category', clean_rec.get('category')),
                    chain=clean_rec.get('chain'),
                    recommendation_score=clean_rec.get('recommendation_score', 0.5)
                )
            )
            
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting trending projects: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

@router.get("/popular", response_model=List[ProjectResponse])
async def get_popular_projects(
    limit: int = Query(10, ge=1, le=100),
    model_type: str = Query("fecf", enum=["fecf", "ncf", "hybrid"])
):
    """
    Get popular projects based on popularity score
    """
    try:
        model = get_model(model_type)
        popular = model.get_popular_projects(n=limit)
        
        # Create response with sanitized data
        project_responses = []
        for rec in popular:
            # Sanitize data (handle NaN values)
            clean_rec = sanitize_project_data(rec)
            
            project_responses.append(
                ProjectResponse(
                    id=clean_rec.get('id'),
                    name=clean_rec.get('name'),
                    symbol=clean_rec.get('symbol'),
                    image=clean_rec.get('image'),  # Tetap gunakan field image
                    price_usd=clean_rec.get('price_usd', clean_rec.get('current_price')),  # Ambil dari current_price jika price_usd tidak ada
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_percentage_7d_in_currency'),  # Gunakan field asli
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h', clean_rec.get('total_volume')),  # Ambil dari total_volume jika volume_24h tidak ada
                    popularity_score=clean_rec.get('popularity_score'),
                    trend_score=clean_rec.get('trend_score'),
                    category=clean_rec.get('primary_category', clean_rec.get('category')),
                    chain=clean_rec.get('chain'),
                    recommendation_score=clean_rec.get('recommendation_score', 0.5)
                )
            )
            
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
    """
    Get similar projects based on feature similarity
    """
    try:
        model = get_model(model_type)
        
        if not hasattr(model, 'get_similar_projects'):
            # Fallback to FECF model if current model doesn't support similarity
            model = get_model("fecf")
        
        similar = model.get_similar_projects(project_id, n=limit)
        
        # Create response with sanitized data
        project_responses = []
        for rec in similar:
            # Sanitize data (handle NaN values)
            clean_rec = sanitize_project_data(rec)
            
            project_responses.append(
                ProjectResponse(
                    id=clean_rec.get('id'),
                    name=clean_rec.get('name'),
                    symbol=clean_rec.get('symbol'),
                    image=clean_rec.get('image'),  # Tetap gunakan field image
                    price_usd=clean_rec.get('price_usd', clean_rec.get('current_price')),  # Ambil dari current_price jika price_usd tidak ada
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_percentage_7d_in_currency'),  # Gunakan field asli
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h', clean_rec.get('total_volume')),  # Ambil dari total_volume jika volume_24h tidak ada
                    popularity_score=clean_rec.get('popularity_score'),
                    trend_score=clean_rec.get('trend_score'),
                    category=clean_rec.get('primary_category', clean_rec.get('category')),
                    chain=clean_rec.get('chain'),
                    recommendation_score=clean_rec.get('recommendation_score', 0.5)
                )
            )
            
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting similar projects: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# Clear cache endpoint (admin only)
# Fungsi untuk membersihkan cache secara lebih agresif
@router.post("/cache/clear")
async def clear_cache(full_clear: bool = False):
    """
    Clear recommendation cache with more options
    
    Args:
        full_clear: Whether to clear all caches, including models
    """
    global _user_recommendations_cache
    global _models
    
    try:
        # Count items before clearing
        total_items = sum(len(cache) for cache in _user_recommendations_cache.values())
        
        # Clear recommendation cache
        for model_type in _user_recommendations_cache:
            _user_recommendations_cache[model_type] = {}
        
        # Optionally clear loaded models
        if full_clear:
            for model_type in _models:
                _models[model_type] = None
            return {"message": f"All caches cleared ({total_items} recommendations and all loaded models)"}
        
        return {"message": f"Recommendation cache cleared ({total_items} entries)"}
    
    except Exception as e:
        logger.error(f"Error clearing cache: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")