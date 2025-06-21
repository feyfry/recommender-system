import os
import logging
from fastapi import FastAPI, Request, HTTPException, Query, Depends, Body
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import time
from typing import List, Optional, Dict, Any
from pydantic import BaseModel, Field

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import API_HOST, API_PORT

# Import untuk fungsi training model dan processing
from main import train_models, process_data

# Import routers
from src.api.recommend import router as recommend_router
from src.api.analysis import router as analysis_router
from src.api.blockchain import router as blockchain_router

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
    description="API for Web3 project recommendations, technical analysis, and blockchain data",
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

# ⚡ ENHANCED: Add request timing middleware dengan smart filtering
@app.middleware("http")
async def add_process_time_header(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    response.headers["X-Process-Time"] = str(process_time)
    
    # ⚡ SMART: Log query lambat dengan threshold yang disesuaikan berdasarkan endpoint
    path = request.url.path
    
    # Blockchain endpoints memiliki threshold lebih tinggi karena melibatkan external API calls
    if path.startswith('/blockchain/'):
        slow_threshold = 15.0  # 15 detik untuk blockchain endpoints
        warning_threshold = 25.0  # 25 detik untuk warning
    elif path.startswith('/analysis/'):
        slow_threshold = 8.0   # 8 detik untuk analysis endpoints
        warning_threshold = 15.0
    else:
        slow_threshold = 2.0   # 2 detik untuk endpoints lainnya
        warning_threshold = 5.0
    
    # Log berdasarkan threshold yang sesuai
    if process_time > warning_threshold:
        logger.warning(f"Very slow request: {path} took {process_time:.2f}s")
    elif process_time > slow_threshold:
        logger.info(f"Slow request: {path} took {process_time:.2f}s")
    
    # ⚡ BARU: Log performance info untuk blockchain endpoints
    if path.startswith('/blockchain/') and process_time > 3.0:
        logger.info(f"Blockchain API call completed: {path} ({process_time:.2f}s) - External API latency included")
        
    return response

# Exception handler
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.error(f"Unhandled exception on {request.url.path}: {str(exc)}")
    return JSONResponse(
        status_code=500,
        content={"error": f"Internal server error: {str(exc)}"}
    )

# Include routers
app.include_router(recommend_router)
app.include_router(analysis_router)
app.include_router(blockchain_router)

# Model pengisian data interaksi
class InteractionRecord(BaseModel):
    user_id: str
    project_id: str
    interaction_type: str = Field(..., description="Type of interaction: view, favorite, portfolio_add")
    weight: int = Field(1, ge=1, le=10, description="Weight/strength of the interaction")
    context: Optional[Dict[str, Any]] = None
    timestamp: Optional[str] = None
    test_only: bool = Field(False, description="Flag to mark request as testing only")

# Model untuk pelatihan model
class TrainModelsRequest(BaseModel):
    models: List[str] = Field(["fecf", "ncf", "hybrid"], description="Models to train")
    save_model: bool = Field(True, description="Whether to save the trained models")
    force: bool = Field(False, description="Whether to force training despite data quality issues")
    test_only: bool = Field(False, description="Flag to mark request as testing only")
    fecf_params: Optional[Dict[str, Any]] = Field(None, description="Parameters specific to FECF model")

# Router untuk interaksi pengguna
@app.post("/interactions/record", tags=["interactions"])
async def record_interaction(interaction: InteractionRecord):
    # Cek apakah ini hanya request testing
    if interaction.test_only:
        return {"status": "success", "message": "Test mode - Interaction not recorded"}
        
    try:
        # Buat path ke csv file
        interactions_path = os.path.join("data", "processed", "interactions.csv")
        
        # PERBAIKAN: Pastikan direktori ada
        os.makedirs(os.path.dirname(interactions_path), exist_ok=True)
        
        # Append interaction to CSV
        import pandas as pd
        from datetime import datetime
        
        # Buat DataFrame untuk satu baris
        new_interaction = pd.DataFrame([{
            'user_id': interaction.user_id,
            'project_id': interaction.project_id,
            'interaction_type': interaction.interaction_type,
            'weight': interaction.weight,
            'timestamp': interaction.timestamp or datetime.now().isoformat()
        }])
        
        # Log untuk debugging
        logger.info(f"Recording interaction: {interaction.user_id} -> {interaction.project_id} ({interaction.interaction_type})")
        
        # Jika file sudah ada, append tanpa header
        if os.path.exists(interactions_path):
            new_interaction.to_csv(interactions_path, mode='a', header=False, index=False)
        else:
            # Jika file belum ada, buat dengan header
            new_interaction.to_csv(interactions_path, index=False)
        
        logger.info(f"Recorded interaction for user {interaction.user_id} with project {interaction.project_id}")
        
        return {"status": "success", "message": "Interaction recorded successfully"}
    
    except Exception as e:
        logger.error(f"Error recording interaction: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error recording interaction: {str(e)}")

# Router untuk admin endpoints
@app.post("/admin/train-models", tags=["admin"])
async def admin_train_models(request: TrainModelsRequest = Body(...)):
    try:
        # Jika mode test_only, kembalikan response sukses tanpa melatih model
        if request.test_only:
            return {
                "status": "success",
                "message": "Test mode - Model training simulation successful"
            }
            
        # Log request
        logger.info(f"Training models: {request.models}, force: {request.force}")
        
        # Buat objek args untuk diteruskan ke train_models
        class Args:
            def __init__(self):
                self.fecf = False
                self.ncf = False
                self.hybrid = False
                self.include_all = False
                self.force = request.force
        
        args = Args()
        args.fecf = "fecf" in request.models
        args.ncf = "ncf" in request.models
        args.hybrid = "hybrid" in request.models
        
        # Jika FECF dilatih dan ada parameter FECF spesifik
        if args.fecf and request.fecf_params:
            # Tambahkan parameter ke enviroment sehingga bisa diakses oleh model FECF
            for key, value in request.fecf_params.items():
                os.environ[f"FECF_{key.upper()}"] = str(value)
                logger.info(f"Setting FECF parameter {key} = {value}")
        
        # Panggil fungsi train_models dari main.py
        result = train_models(args)
        
        if result:
            return {
                "status": "success", 
                "message": f"Models trained successfully: {request.models}"
            }
        else:
            return {
                "status": "error", 
                "message": "Failed to train models. Check logs for details."
            }
    
    except Exception as e:
        logger.error(f"Error training models: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error training models: {str(e)}")

# Model untuk sinkronisasi data
class SyncDataRequest(BaseModel):
    projects_updated: bool = Field(False, description="Whether projects data has been updated")
    users_count: Optional[int] = Field(None, description="Number of synthetic users to generate if needed")
    test_only: bool = Field(False, description="Flag to mark request as testing only")

@app.post("/admin/sync-data", tags=["admin"])
async def admin_sync_data(request: SyncDataRequest = Body(...)):
    # Jika hanya test mode, kembalikan respons sukses tanpa melakukan perubahan
    if request.test_only:
        return {
            "status": "success",
            "message": "Test mode - Data sync simulation successful"
        }
        
    try:
        logger.info("Syncing data from PostgreSQL to recommendation engine CSV files")
        
        # Jika projects data telah diperbarui, proses ulang data
        if request.projects_updated:
            # Buat objek args untuk diteruskan ke process_data
            class Args:
                pass
            
            args = Args()
            args.users = request.users_count or 5000  # Default 5000 jika tidak disediakan
            
            # Panggil fungsi process_data dari main.py
            result = process_data(args)
            
            if result:
                return {
                    "status": "success", 
                    "message": "Data processed successfully"
                }
            else:
                return {
                    "status": "error", 
                    "message": "Failed to process data. Check logs for details."
                }
        
        # Jika tidak perlu memproses ulang data proyek
        return {
            "status": "success", 
            "message": "No data processing needed"
        }
    
    except Exception as e:
        logger.error(f"Error syncing data: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error syncing data: {str(e)}")

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
            "price_prediction": "/analysis/price-prediction/{project_id}",
            "blockchain_portfolio": "/blockchain/portfolio/{wallet_address}",
            "blockchain_transactions": "/blockchain/transactions/{wallet_address}",
            "blockchain_analytics": "/blockchain/analytics/{wallet_address}",
            "interactions": "/interactions/record",
            "admin": {
                "train_models": "/admin/train-models",
                "sync_data": "/admin/sync-data"
            }
        }
    }

# ⚡ ENHANCED: Health check endpoint dengan blockchain API status
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "timestamp": time.time(),
        "blockchain_apis": {
            "moralis": "configured" if os.environ.get("MORALIS_API_KEY") else "missing",
            "etherscan": "configured" if os.environ.get("ETHERSCAN_API_KEY") else "missing",
            "bscscan": "configured" if os.environ.get("BSCSCAN_API_KEY") else "missing",
            "polygonscan": "configured" if os.environ.get("POLYGONSCAN_API_KEY") else "missing",
            "coingecko": "configured" if os.environ.get("COINGECKO_API_KEY") else "missing"
        },
        "performance_info": {
            "blockchain_threshold": "15s (normal for external API calls)",
            "analysis_threshold": "8s",
            "default_threshold": "2s"
        }
    }

# Run the API server
if __name__ == "__main__":
    import uvicorn
    
    # Create logs directory if it doesn't exist
    os.makedirs("logs", exist_ok=True)
    
    # Log startup
    logger.info(f"Starting API server on {API_HOST}:{API_PORT}")
    logger.info(f"Blockchain APIs configured:")
    logger.info(f"  - Moralis: {'✓' if os.environ.get('MORALIS_API_KEY') else '✗'}")
    logger.info(f"  - CoinGecko: {'✓' if os.environ.get('COINGECKO_API_KEY') else '✗'}")
    logger.info(f"  - Etherscan: {'✓' if os.environ.get('ETHERSCAN_API_KEY') else '✗'}")
    
    # Run server
    uvicorn.run(
        "src.api.main:app", 
        host=API_HOST, 
        port=API_PORT,
        reload=True,  # Enable auto-reload during development
        log_level="info"
    )