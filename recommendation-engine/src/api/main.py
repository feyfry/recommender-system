import os
import logging
import sys
from fastapi import FastAPI, Request, HTTPException, Query, Depends, Body
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import time
from typing import List, Optional, Dict, Any
from pydantic import BaseModel, Field
import asyncio
from concurrent.futures import ThreadPoolExecutor
import threading

# ⚡ ENHANCED: Fix Unicode logging error with proper encoding
import locale
import codecs

# Set proper encoding for Windows
if sys.platform.startswith('win'):
    # Force UTF-8 encoding for Windows console
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')
    if hasattr(sys.stderr, 'reconfigure'):
        sys.stderr.reconfigure(encoding='utf-8')
        
    # Set locale to handle Unicode properly
    try:
        locale.setlocale(locale.LC_ALL, 'en_US.UTF-8')
    except locale.Error:
        try:
            locale.setlocale(locale.LC_ALL, 'C.UTF-8')
        except locale.Error:
            pass  # Use default locale

# Path handling
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import API_HOST, API_PORT

# Import untuk fungsi training model dan processing
from main import train_models, process_data

# Import routers
from src.api.recommend import router as recommend_router
from src.api.analysis import router as analysis_router
from src.api.blockchain import router as blockchain_router

# ⚡ ENHANCED: Setup logging dengan Unicode support dan filter
class UnicodeLoggingFilter(logging.Filter):
    """Filter untuk menangani Unicode characters dalam logging"""
    
    def filter(self, record):
        # Sanitize record message untuk menghindari Unicode encoding errors
        if hasattr(record, 'msg') and isinstance(record.msg, str):
            try:
                # Try to encode/decode to catch problematic characters
                record.msg.encode('ascii', 'ignore').decode('ascii')
            except (UnicodeEncodeError, UnicodeDecodeError):
                # Replace problematic characters dengan safe alternatives
                record.msg = record.msg.encode('ascii', 'ignore').decode('ascii')
                record.msg += ' [Unicode chars filtered]'
        
        # Sanitize args juga
        if hasattr(record, 'args') and record.args:
            safe_args = []
            for arg in record.args:
                if isinstance(arg, str):
                    try:
                        safe_arg = arg.encode('ascii', 'ignore').decode('ascii')
                        safe_args.append(safe_arg)
                    except (UnicodeEncodeError, UnicodeDecodeError):
                        safe_args.append('[Unicode filtered]')
                else:
                    safe_args.append(arg)
            record.args = tuple(safe_args)
        
        return True

# Setup logging dengan enhanced configuration
def setup_logging():
    """Setup logging configuration dengan Unicode support"""
    
    # Create logs directory if not exists
    os.makedirs("logs", exist_ok=True)
    
    # Create formatter
    formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # Setup file handler dengan UTF-8 encoding
    file_handler = logging.FileHandler("logs/api.log", encoding='utf-8')
    file_handler.setFormatter(formatter)
    file_handler.addFilter(UnicodeLoggingFilter())
    
    # Setup console handler dengan safe encoding
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    console_handler.addFilter(UnicodeLoggingFilter())
    
    # Configure root logger
    logging.basicConfig(
        level=logging.INFO,
        handlers=[file_handler, console_handler],
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    return logging.getLogger(__name__)

# Initialize logging
logger = setup_logging()

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

# ⚡ ENHANCED: Add request timing middleware dengan smart filtering dan better thresholds
@app.middleware("http")
async def add_process_time_header(request: Request, call_next):
    start_time = time.time()
    response = await call_next(request)
    process_time = time.time() - start_time
    response.headers["X-Process-Time"] = str(process_time)
    
    # ⚡ SMART: Log query lambat dengan threshold yang disesuaikan berdasarkan endpoint
    path = request.url.path
    
    # ⚡ ENHANCED: More realistic thresholds
    if path.startswith('/blockchain/'):
        slow_threshold = 30.0     # 30 detik untuk blockchain endpoints (lebih realistis)
        warning_threshold = 60.0  # 1 menit untuk warning
        critical_threshold = 120.0 # 2 menit untuk critical
    elif path.startswith('/analysis/'):
        slow_threshold = 10.0     # 10 detik untuk analysis endpoints
        warning_threshold = 20.0  # 20 detik untuk warning
        critical_threshold = 30.0 # 30 detik untuk critical
    else:
        slow_threshold = 3.0      # 3 detik untuk endpoints lainnya
        warning_threshold = 5.0   # 5 detik untuk warning
        critical_threshold = 10.0 # 10 detik untuk critical
    
    # Log berdasarkan threshold yang sesuai dengan safe string handling
    try:
        safe_path = path.encode('ascii', 'ignore').decode('ascii')
        
        if process_time > critical_threshold:
            logger.error(f"Critical slow request: {safe_path} took {process_time:.2f}s")
        elif process_time > warning_threshold:
            logger.warning(f"Very slow request: {safe_path} took {process_time:.2f}s")
        elif process_time > slow_threshold:
            logger.info(f"Slow request: {safe_path} took {process_time:.2f}s")
        
        # ⚡ ENHANCED: Log performance info untuk blockchain endpoints
        if path.startswith('/blockchain/') and process_time > 10.0:
            logger.info(f"Blockchain API call completed: {safe_path} ({process_time:.2f}s) - External API latency included")
            
    except Exception as e:
        # Fallback logging jika ada masalah dengan string handling
        logger.info(f"Request completed in {process_time:.2f}s [path filtering error: {str(e)}]")
        
    return response

# ⚡ ENHANCED: Exception handler dengan better error categorization
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    # Safe string handling untuk exception
    try:
        safe_path = request.url.path.encode('ascii', 'ignore').decode('ascii')
        safe_error = str(exc).encode('ascii', 'ignore').decode('ascii')
        logger.error(f"Unhandled exception on {safe_path}: {safe_error}")
    except Exception:
        logger.error(f"Unhandled exception occurred [path/error filtering failed]")
    
    # ⚡ ENHANCED: Better error categorization
    error_message = "Internal server error"
    status_code = 500
    
    if "timeout" in str(exc).lower():
        error_message = "Request timeout - please try again"
        status_code = 504
    elif "connection" in str(exc).lower():
        error_message = "Connection error - service may be temporarily unavailable"
        status_code = 503
    elif "rate limit" in str(exc).lower():
        error_message = "Rate limit exceeded - please wait before retrying"
        status_code = 429
    
    return JSONResponse(
        status_code=status_code,
        content={"error": error_message, "details": str(exc)[:200]}  # Limit error details
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

# NEW: Model untuk production pipeline request
class ProductionPipelineRequest(BaseModel):
    evaluate: bool = Field(True, description="Whether to run evaluation after pipeline")
    force: bool = Field(False, description="Whether to force execution despite warnings")
    test_only: bool = Field(False, description="Flag to mark request as testing only")
    async_mode: bool = Field(True, description="Run pipeline asynchronously")

# NEW: Global variable untuk track status pipeline
pipeline_status = {
    "running": False,
    "last_run": None,
    "status": "idle",
    "message": "",
    "start_time": None,
    "end_time": None,
    "output": "",
    "error": ""
}

def run_pipeline_sync(evaluate=True, force=False):
    """
    Fungsi untuk menjalankan pipeline secara synchronous di thread terpisah
    """
    global pipeline_status
    
    try:
        import subprocess
        from pathlib import Path
        import time
        
        pipeline_status["running"] = True
        pipeline_status["status"] = "running"
        pipeline_status["start_time"] = time.time()
        pipeline_status["message"] = "Pipeline sedang berjalan..."
        pipeline_status["output"] = ""
        pipeline_status["error"] = ""
        
        logger.info("Starting production pipeline in background thread...")
        
        # Dapatkan path ke main.py
        current_dir = Path(__file__).parent.parent.parent
        main_py_path = current_dir / "main.py"
        
        if not main_py_path.exists():
            raise FileNotFoundError(f"main.py tidak ditemukan di {main_py_path}")
        
        # Bangun command
        cmd = ["python", str(main_py_path), "run", "--production"]
        
        if evaluate:
            cmd.append("--evaluate")
        if force:
            cmd.append("--force")
        
        logger.info(f"Executing command: {' '.join(cmd)}")
        
        # UPDATED: Timeout 2 jam (7200 detik) untuk pipeline yang sangat panjang
        process = subprocess.run(
            cmd,
            cwd=str(current_dir),
            capture_output=True,
            text=True,
            timeout=7200,  # 2 jam timeout
            encoding='utf-8'
        )
        
        # Update status berdasarkan hasil
        pipeline_status["end_time"] = time.time()
        pipeline_status["running"] = False
        
        if process.returncode == 0:
            pipeline_status["status"] = "completed"
            pipeline_status["message"] = "Pipeline berhasil diselesaikan"
            pipeline_status["output"] = process.stdout[-3000:] if process.stdout else ""  # Increase output size
            logger.info("Production pipeline completed successfully in background")
        else:
            pipeline_status["status"] = "failed"
            pipeline_status["message"] = f"Pipeline gagal dengan return code: {process.returncode}"
            pipeline_status["output"] = process.stdout[-2000:] if process.stdout else ""
            pipeline_status["error"] = process.stderr[-2000:] if process.stderr else ""
            logger.error(f"Production pipeline failed with return code: {process.returncode}")
            
        pipeline_status["last_run"] = time.time()
        
    except subprocess.TimeoutExpired:
        pipeline_status["running"] = False
        pipeline_status["status"] = "timeout"
        pipeline_status["message"] = "Pipeline timeout setelah 2 jam"
        pipeline_status["error"] = "Timeout exceeded (2 hours)"
        pipeline_status["end_time"] = time.time()
        logger.error("Production pipeline timed out (> 2 hours)")
        
    except Exception as e:
        pipeline_status["running"] = False
        pipeline_status["status"] = "error"
        pipeline_status["message"] = f"Error: {str(e)}"
        pipeline_status["error"] = str(e)
        pipeline_status["end_time"] = time.time()
        logger.error(f"Error in background pipeline: {str(e)}")

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
        
        # Log untuk debugging dengan safe string handling
        safe_user_id = interaction.user_id.encode('ascii', 'ignore').decode('ascii')
        safe_project_id = interaction.project_id.encode('ascii', 'ignore').decode('ascii')
        logger.info(f"Recording interaction: {safe_user_id} -> {safe_project_id} ({interaction.interaction_type})")
        
        # Jika file sudah ada, append tanpa header
        if os.path.exists(interactions_path):
            new_interaction.to_csv(interactions_path, mode='a', header=False, index=False)
        else:
            # Jika file belum ada, buat dengan header
            new_interaction.to_csv(interactions_path, index=False)
        
        logger.info(f"Recorded interaction for user {safe_user_id} with project {safe_project_id}")
        
        return {"status": "success", "message": "Interaction recorded successfully"}
    
    except Exception as e:
        safe_error = str(e).encode('ascii', 'ignore').decode('ascii')
        logger.error(f"Error recording interaction: {safe_error}")
        raise HTTPException(status_code=500, detail=f"Error recording interaction: {safe_error}")

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
            
        # Log request dengan safe string handling
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
        safe_error = str(e).encode('ascii', 'ignore').decode('ascii')
        logger.error(f"Error training models: {safe_error}")
        raise HTTPException(status_code=500, detail=f"Error training models: {safe_error}")

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
        safe_error = str(e).encode('ascii', 'ignore').decode('ascii')
        logger.error(f"Error syncing data: {safe_error}")
        raise HTTPException(status_code=500, detail=f"Error syncing data: {safe_error}")

# NEW: Production Pipeline Endpoints
@app.post("/admin/production-pipeline", tags=["admin"])
async def admin_production_pipeline(request: ProductionPipelineRequest = Body(...)):
    """
    Jalankan production pipeline lengkap - mode asynchronous
    """
    global pipeline_status
    
    if request.test_only:
        return {
            "status": "success",
            "message": "Test mode - Production pipeline simulation successful"
        }
    
    # Cek apakah pipeline sudah berjalan
    if pipeline_status["running"]:
        elapsed = time.time() - pipeline_status["start_time"] if pipeline_status["start_time"] else 0
        elapsed_minutes = elapsed / 60
        return {
            "status": "already_running",
            "message": f"Pipeline sudah berjalan selama {elapsed_minutes:.1f} menit",
            "elapsed_time": elapsed,
            "elapsed_minutes": elapsed_minutes,
            "async_mode": True
        }
    
    try:
        if request.async_mode:
            # Jalankan pipeline di background thread
            thread = threading.Thread(
                target=run_pipeline_sync,
                args=(request.evaluate, request.force),
                daemon=True
            )
            thread.start()
            
            return {
                "status": "started",
                "message": "Production pipeline dimulai secara asynchronous",
                "async_mode": True,
                "estimated_time": "30 menit - 2 jam",  # UPDATED estimate
                "timeout": "2 jam maksimal",
                "check_status_endpoint": "/admin/production-pipeline/status"
            }
        else:
            # Mode synchronous dengan timeout lebih besar (hanya untuk testing)
            return await admin_production_pipeline_sync(request)
            
    except Exception as e:
        safe_error = str(e).encode('ascii', 'ignore').decode('ascii')
        logger.error(f"Error starting production pipeline: {safe_error}")
        return {
            "status": "error",
            "message": f"Error starting production pipeline: {safe_error}",
            "error": safe_error
        }

@app.get("/admin/production-pipeline/status", tags=["admin"])
async def admin_production_pipeline_status():
    """
    Cek status production pipeline yang sedang berjalan
    """
    global pipeline_status
    
    status_copy = pipeline_status.copy()
    
    # Hitung elapsed time jika sedang berjalan
    if status_copy["running"] and status_copy["start_time"]:
        elapsed_seconds = time.time() - status_copy["start_time"]
        status_copy["elapsed_time"] = elapsed_seconds
        status_copy["elapsed_minutes"] = elapsed_seconds / 60
        status_copy["elapsed_hours"] = elapsed_seconds / 3600
        
        # Progress estimation berdasarkan waktu
        if elapsed_seconds < 600:  # < 10 menit
            status_copy["estimated_progress"] = "Collecting data..."
        elif elapsed_seconds < 1200:  # < 20 menit
            status_copy["estimated_progress"] = "Processing data..."
        elif elapsed_seconds < 2400:  # < 40 menit
            status_copy["estimated_progress"] = "Training FECF model..."
        elif elapsed_seconds < 3600:  # < 1 jam
            status_copy["estimated_progress"] = "Training NCF model..."
        elif elapsed_seconds < 4800:  # < 1.3 jam
            status_copy["estimated_progress"] = "Training Hybrid model..."
        elif elapsed_seconds < 6000:  # < 1.7 jam
            status_copy["estimated_progress"] = "Running evaluation..."
        else:
            status_copy["estimated_progress"] = "Finalizing pipeline..."
            
    elif status_copy["start_time"] and status_copy["end_time"]:
        total_seconds = status_copy["end_time"] - status_copy["start_time"]
        status_copy["total_time"] = total_seconds
        status_copy["total_minutes"] = total_seconds / 60
        status_copy["total_hours"] = total_seconds / 3600
    
    return {
        "pipeline_status": status_copy,
        "timestamp": time.time()
    }

@app.post("/admin/production-pipeline/stop", tags=["admin"])
async def admin_production_pipeline_stop():
    """
    Stop production pipeline yang sedang berjalan (jika memungkinkan)
    """
    global pipeline_status
    
    if not pipeline_status["running"]:
        return {
            "status": "not_running",
            "message": "Pipeline tidak sedang berjalan"
        }
    
    # Catatan: Menghentikan subprocess yang sudah berjalan cukup sulit
    # Untuk saat ini, hanya update status
    pipeline_status["status"] = "stop_requested"
    pipeline_status["message"] = "Stop request diterima (pipeline mungkin masih berjalan di background)"
    
    return {
        "status": "stop_requested",
        "message": "Stop request diterima",
        "note": "Pipeline mungkin masih membutuhkan waktu untuk berhenti"
    }

# Keep original sync version untuk backward compatibility - UPDATED timeout
async def admin_production_pipeline_sync(request: ProductionPipelineRequest):
    """
    Jalankan production pipeline secara synchronous (untuk testing)
    """
    try:
        import subprocess
        from pathlib import Path
        
        logger.info("Starting production pipeline synchronously...")
        
        current_dir = Path(__file__).parent.parent.parent
        main_py_path = current_dir / "main.py"
        
        if not main_py_path.exists():
            raise FileNotFoundError(f"main.py tidak ditemukan di {main_py_path}")
        
        cmd = ["python", str(main_py_path), "run", "--production"]
        
        if request.evaluate:
            cmd.append("--evaluate")
        if request.force:
            cmd.append("--force")
        
        logger.info(f"Executing command: {' '.join(cmd)}")
        
        # UPDATED: Timeout 1 jam untuk mode sync (lebih singkat dari async)
        process = subprocess.run(
            cmd,
            cwd=str(current_dir),
            capture_output=True,
            text=True,
            timeout=3600,  # 1 jam untuk sync mode
            encoding='utf-8'
        )
        
        if process.returncode == 0:
            return {
                "status": "success",
                "message": "Production pipeline completed successfully",
                "returncode": process.returncode,
                "output": process.stdout[-2000:] if process.stdout else "",
                "sync_mode": True
            }
        else:
            return {
                "status": "error",
                "message": f"Production pipeline failed with return code: {process.returncode}",
                "returncode": process.returncode,
                "error": process.stderr[-1500:] if process.stderr else "",
                "output": process.stdout[-1500:] if process.stdout else "",
                "sync_mode": True
            }
            
    except subprocess.TimeoutExpired:
        return {
            "status": "error",
            "message": "Production pipeline timed out (exceeded 1 hour in sync mode)",
            "error": "Timeout exceeded",
            "sync_mode": True
        }
    except Exception as e:
        safe_error = str(e).encode('ascii', 'ignore').decode('ascii')
        return {
            "status": "error", 
            "message": f"Error: {safe_error}",
            "error": safe_error,
            "sync_mode": True
        }

# Endpoint untuk test ketersediaan pipeline
@app.get("/admin/pipeline-status", tags=["admin"])
async def admin_pipeline_status():
    """
    Cek status dan ketersediaan production pipeline
    """
    try:
        import subprocess
        from pathlib import Path
        
        # Cek ketersediaan main.py
        current_dir = Path(__file__).parent.parent.parent
        main_py_path = current_dir / "main.py"
        
        status = {
            "pipeline_available": main_py_path.exists(),
            "main_py_path": str(main_py_path),
            "current_dir": str(current_dir),
            "python_executable": "python",
            "timestamp": time.time(),
        }
        
        # Test basic python command
        try:
            result = subprocess.run(
                ["python", "--version"], 
                capture_output=True, 
                text=True, 
                timeout=5
            )
            status["python_version"] = result.stdout.strip() if result.returncode == 0 else "Unknown"
            status["python_available"] = result.returncode == 0
        except Exception as e:
            status["python_available"] = False
            status["python_error"] = str(e)
        
        return {
            "status": "healthy",
            "pipeline_status": status
        }
        
    except Exception as e:
        logger.error(f"Error checking pipeline status: {str(e)}")
        return {
            "status": "error",
            "message": str(e)
        }

# Root endpoint
@app.get("/")
async def root():
    return {
        "name": "Web3 Recommendation System API",
        "version": "1.0.0",
        "status": "healthy",
        "unicode_support": "enabled",
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
                "sync_data": "/admin/sync-data",
                "production_pipeline": "/admin/production-pipeline",
                "production_pipeline_status": "/admin/production-pipeline/status"
            }
        }
    }

# ⚡ ENHANCED: Health check endpoint dengan better system info
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "timestamp": time.time(),
        "system_info": {
            "platform": sys.platform,
            "python_version": sys.version_info[:3],
            "encoding": {
                "stdout": getattr(sys.stdout, 'encoding', 'unknown'),
                "stderr": getattr(sys.stderr, 'encoding', 'unknown'),
                "locale": locale.getlocale()
            }
        },
        "blockchain_apis": {
            "moralis": "configured" if os.environ.get("MORALIS_API_KEY") else "missing",
            "etherscan": "configured" if os.environ.get("ETHERSCAN_API_KEY") else "missing",
            "bscscan": "configured" if os.environ.get("BSCSCAN_API_KEY") else "missing",
            "polygonscan": "configured" if os.environ.get("POLYGONSCAN_API_KEY") else "missing",
            "coingecko": "configured" if os.environ.get("COINGECKO_API_KEY") else "missing"
        },
        "performance_thresholds": {
            "blockchain_endpoints": "30s (normal for multi-chain data)",
            "analysis_endpoints": "10s",
            "default_endpoints": "3s"
        },
        "features": {
            "unicode_logging": "enabled",
            "spam_filtering": "enabled", 
            "smart_batching": "enabled",
            "retry_logic": "enabled",
            "production_pipeline": "enabled"
        }
    }

# Run the API server
if __name__ == "__main__":
    import uvicorn
    
    # Create logs directory if it doesn't exist
    os.makedirs("logs", exist_ok=True)
    
    # Log startup dengan safe string handling
    logger.info(f"Starting API server on {API_HOST}:{API_PORT}")
    logger.info("Blockchain APIs configured:")
    logger.info(f"  - Moralis: {'✓' if os.environ.get('MORALIS_API_KEY') else '✗'}")
    logger.info(f"  - CoinGecko: {'✓' if os.environ.get('COINGECKO_API_KEY') else '✗'}")
    logger.info(f"  - Etherscan: {'✓' if os.environ.get('ETHERSCAN_API_KEY') else '✗'}")
    logger.info("Unicode logging support: enabled")
    logger.info("Spam filtering: enabled")
    logger.info("Production pipeline: enabled")
    
    # ⚡ ENHANCED: Uvicorn configuration
    uvicorn_config = {
        "app": "src.api.main:app",
        "host": API_HOST,
        "port": API_PORT,
        "reload": True,  # Enable auto-reload during development
        "log_level": "info",
        "access_log": False,  # Disable access log to reduce noise
        "use_colors": False,  # Disable colors untuk better file logging
    }
    
    # Run server
    uvicorn.run(**uvicorn_config)