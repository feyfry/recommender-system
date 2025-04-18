"""
API endpoints untuk rekomendasi proyek Web3
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

# Global models container
_models = {
    "fecf": None,
    "ncf": None,
    "hybrid": None
}

# Cache for recommendations
_recommendations_cache = {}
_cache_ttl = 3600  # 1 hour in seconds

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
    image_url: Optional[str] = None
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

# Helper functions
def get_model(model_type: str) -> Any:
    """
    Get or initialize model based on type
    
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
    
    # Initialize model
    try:
        if model_type == "fecf":
            model = FeatureEnhancedCF()
        elif model_type == "ncf":
            model = NCFRecommender()
        else:  # hybrid
            model = HybridRecommender()
        
        # Load data
        model.load_data()
        
        # Try to load pre-trained model
        if model_type == "fecf":
            model_path = os.path.join("data", "models", "fecf_model.pkl")
            if os.path.exists(model_path):
                model.load_model(model_path)
        elif model_type == "ncf":
            model_path = os.path.join("data", "models", "ncf_model.pkl")
            if os.path.exists(model_path):
                model.load_model(model_path)
        elif model_type == "hybrid":
            fecf_path = os.path.join("data", "models", "fecf_model.pkl")
            ncf_path = os.path.join("data", "models", "ncf_model.pkl")
            if os.path.exists(fecf_path) or os.path.exists(ncf_path):
                model.load_model(
                    fecf_filepath=fecf_path if os.path.exists(fecf_path) else None,
                    ncf_filepath=ncf_path if os.path.exists(ncf_path) else None
                )
        
        # Store model for later use
        _models[model_type] = model
        
        return model
    
    except Exception as e:
        logger.error(f"Error initializing {model_type} model: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Model initialization error: {str(e)}")

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
    Clean project data to handle NaN values
    
    Args:
        project_dict: Project data dictionary
        
    Returns:
        dict: Cleaned project data
    """
    result = {}
    for key, value in project_dict.items():
        if pd.isna(value):
            result[key] = None
        else:
            result[key] = value
    return result

# Routes
@router.post("/projects", response_model=RecommendationResponse)
async def recommend_projects(request: RecommendationRequest):
    """
    Get project recommendations for a user
    """
    start_time = datetime.now()
    logger.info(f"Recommendation request for user {request.user_id} using {request.model_type} model")
    
    # Check cache first
    cache_key = get_cache_key(request)
    if cache_key in _recommendations_cache:
        cache_entry = _recommendations_cache[cache_key]
        
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
        
        # Check if this is a cold-start user
        is_cold_start = is_cold_start_user(request.user_id, model)
        
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
                    image_url=clean_rec.get('image_url'),
                    price_usd=clean_rec.get('price_usd'),
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_7d'),
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h'),
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
        
        # Store in cache
        _recommendations_cache[cache_key] = {
            'response': response,
            'expires': datetime.now() + timedelta(seconds=_cache_ttl)
        }
        
        return response
        
    except Exception as e:
        logger.error(f"Error generating recommendations: {str(e)}")
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
                    image_url=clean_rec.get('image_url'),
                    price_usd=clean_rec.get('price_usd'),
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_7d'),
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h'),
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
                    image_url=clean_rec.get('image_url'),
                    price_usd=clean_rec.get('price_usd'),
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_7d'),
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h'),
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
                    image_url=clean_rec.get('image_url'),
                    price_usd=clean_rec.get('price_usd'),
                    price_change_24h=clean_rec.get('price_change_24h'),
                    price_change_7d=clean_rec.get('price_change_7d'),
                    market_cap=clean_rec.get('market_cap'),
                    volume_24h=clean_rec.get('volume_24h'),
                    popularity_score=clean_rec.get('popularity_score'),
                    trend_score=clean_rec.get('trend_score'),
                    category=clean_rec.get('primary_category', clean_rec.get('category')),
                    chain=clean_rec.get('chain'),
                    recommendation_score=clean_rec.get('similarity_score', 0.5)
                )
            )
            
        return project_responses
    
    except Exception as e:
        logger.error(f"Error getting similar projects: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

# Clear cache endpoint (admin only)
@router.post("/cache/clear")
async def clear_cache():
    """
    Clear recommendation cache (admin only)
    """
    global _recommendations_cache
    
    try:
        cache_size = len(_recommendations_cache)
        _recommendations_cache = {}
        
        return {"message": f"Cache cleared ({cache_size} entries)"}
    
    except Exception as e:
        logger.error(f"Error clearing cache: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")