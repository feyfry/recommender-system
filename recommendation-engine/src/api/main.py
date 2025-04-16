"""
Main API entrypoint untuk sistem rekomendasi Web3
"""

import os
import logging
from fastapi import FastAPI, Request, HTTPException
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import time

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import API_HOST, API_PORT

# Import routers
from src.api.recommend import router as recommend_router
from src.api.analysis import router as analysis_router

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("logs/api.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title="Web3 Recommendation System API",
    description="API for Web3 project recommendations and technical analysis",
    version="1.0.0"
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace with specific origins
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Add request timing middleware
@app.middleware("http")
async def add_process_time_header(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    response.headers["X-Process-Time"] = str(process_time)
    
    # Log requests that take longer than 1 second
    if process_time > 1.0:
        logger.warning(f"Slow request: {request.url.path} took {process_time:.2f}s")
        
    return response

# Exception handler
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.error(f"Unhandled exception: {str(exc)}")
    return JSONResponse(
        status_code=500,
        content={"error": f"Internal server error: {str(exc)}"}
    )

# Include routers
app.include_router(recommend_router)
app.include_router(analysis_router)

# Root endpoint
@app.get("/")
async def root():
    return {
        "name": "Web3 Recommendation System API",
        "version": "1.0.0",
        "endpoints": {
            "recommendations": "/recommend/projects",
            "trending": "/recommend/trending",
            "popular": "/recommend/popular",
            "similar": "/recommend/similar/{project_id}",
            "trading_signals": "/analysis/trading-signals",
            "technical_indicators": "/analysis/indicators",
            "market_events": "/analysis/market-events/{project_id}",
            "alerts": "/analysis/alerts/{project_id}",
            "price_prediction": "/analysis/price-prediction/{project_id}"
        }
    }

# Health check endpoint
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "timestamp": time.time()
    }

# Run the API server
if __name__ == "__main__":
    import uvicorn
    
    # Create logs directory if it doesn't exist
    os.makedirs("logs", exist_ok=True)
    
    # Log startup
    logger.info(f"Starting API server on {API_HOST}:{API_PORT}")
    
    # Run server
    uvicorn.run(
        "src.api.main:app", 
        host=API_HOST, 
        port=API_PORT,
        reload=True,  # Enable auto-reload during development
        log_level="info"
    )