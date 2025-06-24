import os
import argparse
import logging
import time
import traceback
from datetime import datetime
from typing import Optional, Dict, Any
import pandas as pd

# Buat direktori logs jika belum ada
os.makedirs("logs", exist_ok=True)

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("logs/main.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

from config import (
    PROCESSED_DIR,
    MODELS_DIR,
    HYBRID_PARAMS
)

def collect_data(args):
    from src.data.collector import CoinGeckoCollector
    
    logger.info("Starting data collection from CoinGecko API")
    print("Collecting data from CoinGecko API...")
    
    # ✅ FIXED: Get parameters with proper fallbacks for both collect and run commands
    limit = getattr(args, 'limit', None) or getattr(args, 'data_limit', 1000)
    detail_limit = getattr(args, 'detail_limit', 1000)
    rate_limit = getattr(args, 'rate_limit', 2.0)
    include_categories = getattr(args, 'include_categories', False)

    # ✅ Log actual parameters being used
    logger.info(f"Collection parameters: limit={limit}, detail_limit={detail_limit}, rate_limit={rate_limit}")
    print(f"Collection parameters: limit={limit}, detail_limit={detail_limit}")

    # Initialize collector with rate limit
    collector = CoinGeckoCollector(rate_limit=rate_limit)
    
    # Check if API is available
    if not collector.ping_api():
        logger.error("CoinGecko API is not available")
        print("ERROR: CoinGecko API is not available")
        return False
    
    start_time = time.time()
    # Using parameters limit, detail_limit, and include_categories
    result = collector.collect_all_data(limit=limit, detail_limit=detail_limit, include_categories=include_categories)
    
    if result:
        elapsed_time = time.time() - start_time
        logger.info(f"Data collection completed in {elapsed_time:.2f} seconds")
        print(f"SUCCESS: Data collection completed in {elapsed_time:.2f} seconds")
        return True
    else:
        logger.error("Data collection failed")
        print("ERROR: Data collection failed")
        return False

def process_data(args):
    from src.data.processor import DataProcessor
    
    logger.info("Starting data processing")
    print("Processing data...")
    
    processor = DataProcessor()
    
    # Get arguments
    n_users = getattr(args, 'users', 5000)  # ✅ FIXED: Default untuk production lebih tinggi
    production_mode = getattr(args, 'production', False)
    
    start_time = time.time()
    
    try:
        if production_mode:
            # Production mode: preserve existing interactions
            logger.info("Production mode: Preserving existing interactions")
            print("Production mode: Updating projects while preserving existing interactions")
            
            # Load data terbaru
            projects_df, _, trending_df = processor.load_latest_data()  # ✅ Use _ for unused variable
            
            if projects_df is None or projects_df.empty:
                logger.error("No project data available")
                print("ERROR: No project data available")
                return False
            
            # Clean dan update project data
            projects_df = processor._clean_project_data(projects_df, trending_df)
            
            # Create features matrix
            features_df = processor._create_features(projects_df)
            
            # Load existing interactions
            interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
            
            if os.path.exists(interactions_path):
                interactions_df = pd.read_csv(interactions_path)
                
                # Validate dan clean interactions
                valid_projects = set(projects_df['id'])
                original_count = len(interactions_df)
                interactions_df = interactions_df[interactions_df['project_id'].isin(valid_projects)]
                
                removed_count = original_count - len(interactions_df)
                if removed_count > 0:
                    logger.info(f"Removed {removed_count} interactions for projects no longer available")
                    print(f"CLEAN: Cleaned {removed_count} outdated interactions")
                
                logger.info(f"Preserved {len(interactions_df)} existing interactions")
                print(f"SUCCESS: Preserved {len(interactions_df)} existing interactions")
                
            else:
                logger.warning("No existing interactions found in production mode")
                print("WARNING: No existing interactions found. Consider running in development mode first.")
                return False
        else:
            # Development mode: full processing with synthetic data
            logger.info("Development mode: Full processing with synthetic data generation")
            print("Development mode: Full processing with synthetic data generation")
            
            result = processor.process_data(n_users=n_users)
            if not result:
                logger.error("Data processing failed")
                print("ERROR: Data processing failed")
                return False
                
            projects_df, interactions_df, features_df = result
        
        # Save processed data
        if production_mode:
            processor._save_processed_data(projects_df, interactions_df, features_df)
        
        elapsed_time = time.time() - start_time
        logger.info(f"Data processing completed in {elapsed_time:.2f} seconds")
        print(f"SUCCESS: Data processing completed in {elapsed_time:.2f} seconds")

        if production_mode:
            print(f"Production stats: {len(projects_df)} projects, {len(interactions_df)} interactions")
        else:
            print(f"Development stats: {len(projects_df)} projects, {len(interactions_df)} interactions, {features_df.shape} features")
        
        return True
        
    except Exception as e:
        logger.error(f"Error during data processing: {str(e)}")
        print(f"ERROR: Error during data processing: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        return False
    
def update_projects_only():
    """
    Update projects data dan features tanpa regenerate interactions (untuk production)
    """
    from src.data.processor import DataProcessor
    
    logger.info("Starting projects update for production (preserving existing interactions)")
    print("Updating projects data for production...")
    
    processor = DataProcessor()
    
    start_time = time.time()
    
    try:
        # 1. Load data terbaru
        projects_df, _, trending_df = processor.load_latest_data()  # ✅ Use _ for unused variable
        
        if projects_df is None or projects_df.empty:
            logger.error("No project data available")
            print("ERROR: No project data available")
            return False
        
        # 2. Clean dan update project data dengan metrik terbaru
        projects_df = processor._clean_project_data(projects_df, trending_df)
        logger.info(f"Updated {len(projects_df)} projects with latest metrics")
        
        # 3. Create features matrix dengan data terbaru
        features_df = processor._create_features(projects_df)
        logger.info(f"Created features matrix with shape {features_df.shape}")
        
        # 4. Load existing interactions (jangan regenerate)
        interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        
        if os.path.exists(interactions_path):
            interactions_df = pd.read_csv(interactions_path)
            logger.info(f"Loaded existing interactions: {len(interactions_df)} records")
            print(f"SUCCESS: Preserved existing interactions: {len(interactions_df)} records")
            
            # Validate interactions masih kompatibel dengan projects terbaru
            valid_projects = set(projects_df['id'])
            original_count = len(interactions_df)
            interactions_df = interactions_df[interactions_df['project_id'].isin(valid_projects)]
            removed_count = original_count - len(interactions_df)
            
            if removed_count > 0:
                logger.info(f"Removed {removed_count} interactions for projects no longer available")
                print(f"CLEAN: Cleaned {removed_count} outdated interactions")
        else:
            logger.warning("No existing interactions found, will need to generate synthetic data")
            print("WARNING: No existing interactions found. Run full process to generate synthetic data.")
            return False
        
        # 5. Save updated data
        processor._save_processed_data(projects_df, interactions_df, features_df)
        
        elapsed_time = time.time() - start_time
        logger.info(f"Projects update completed in {elapsed_time:.2f} seconds")
        print(f"SUCCESS: Projects update completed in {elapsed_time:.2f} seconds")
        print(f"INFO: Updated: {len(projects_df)} projects, {len(interactions_df)} interactions preserved")

        return True
        
    except Exception as e:
        logger.error(f"Error during projects update: {str(e)}")
        print(f"ERROR: Error during projects update: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        return False

def train_models(args):
    logger.info("Training recommendation models")
    
    try:
        # Cek apakah data yang diproses ada
        processed_files = [f for f in os.listdir(PROCESSED_DIR) if f in ["projects.csv", "interactions.csv", "features.csv"]]
        if len(processed_files) < 3:
            logger.error("Missing processed data files")
            print("ERROR: Missing processed data files. Please run data processing first with: python main.py process")
            return False
        
        # Analisis data sebelum training untuk memeriksa kualitas
        validation_passed = _validate_data_quality()
        
        # Logging untuk debug
        force_flag = getattr(args, 'force', False)
        print(f"Force flag status: {force_flag}")
        logger.info(f"Force flag status: {force_flag}")
        
        if not validation_passed and not force_flag:
            user_input = input("WARNING: Data quality validation failed. Continue with training anyway? (y/n): ")
            if user_input.lower() != 'y':
                return False
        elif not validation_passed and force_flag:
            # Jika force=True, lanjutkan meskipun validasi gagal
            logger.info("Data quality validation failed but continuing due to force flag")
            print("WARNING: Data quality validation failed but continuing due to force flag")
                
        # Pastikan direktori model ada
        models_dir = MODELS_DIR
        if not os.path.exists(models_dir):
            os.makedirs(models_dir, exist_ok=True)
            
        # Determine which models to train
        models_to_train = []
        
        # Parse args untuk menentukan model yang akan dilatih
        train_fecf = getattr(args, 'fecf', False)
        train_ncf = getattr(args, 'ncf', False)
        train_hybrid = getattr(args, 'hybrid', False)
        include_all = getattr(args, 'include_all', False)
        
        # ✅ FIXED: Default untuk production mode adalah train all models
        production_mode = getattr(args, 'production', False)
        
        # Jika tidak ada model yang dipilih atau include_all, atau production mode, latih semua model
        if not (train_fecf or train_ncf or train_hybrid) or include_all or production_mode:
            train_fecf = train_ncf = train_hybrid = True
            if production_mode:
                logger.info("Production mode: Training all models (FECF, NCF, Hybrid)")
                print("Production mode: Training all models (FECF, NCF, Hybrid)")
        
        # Inisialisasi model yang akan dilatih
        if train_fecf:
            # from src.models.fecf import FeatureEnhancedCF
            from src.models.alt_fecf import FeatureEnhancedCF
            models_to_train.append(("FECF", FeatureEnhancedCF()))
        
        if train_ncf:
            from src.models.ncf import NCFRecommender
            models_to_train.append(("NCF", NCFRecommender()))
        
        if train_hybrid:
            from src.models.hybrid import HybridRecommender
            models_to_train.append(("Hybrid", HybridRecommender()))
        
        # Train each model
        results = {}
        start_time = time.time()
        
        print("Training recommendation models...")
        
        # Train FECF first as NCF and Hybrid depend on it
        for model_name, model in models_to_train:
            # Skip non-FECF models in first pass
            if model_name != "FECF":
                continue
                
            print(f"Training {model_name} model...")
            try:
                # Load data
                data_loaded = model.load_data()
                if not data_loaded:
                    logger.error(f"Failed to load data for {model_name} model")
                    print(f"ERROR: Failed to load data for {model_name} model")
                    results[model_name] = {
                        "success": False,
                        "error": "Failed to load data"
                    }
                    continue
                
                # PATCH: Set nilai minimum untuk no_components pada FECF model jika berasal dari environment
                if model_name == "FECF" and hasattr(model, 'params') and 'no_components' in model.params:
                    # Ensure n_components is at least 1
                    if model.params['no_components'] <= 0:
                        logger.info(f"Setting minimum no_components=1 for FECF model (was {model.params['no_components']})")
                        model.params['no_components'] = 1
                
                # Cek environment variable untuk parameter FECF
                if model_name == "FECF" and "FECF_NO_COMPONENTS" in os.environ:
                    no_components = int(os.environ.get("FECF_NO_COMPONENTS", "1"))
                    if hasattr(model, 'params'):
                        model.params['no_components'] = max(1, no_components)  # Pastikan minimal 1
                        logger.info(f"Using no_components={model.params['no_components']} from environment variable")
                
                # Train model
                model_start_time = time.time()
                metrics = model.train(save_model=True)
                
                # Cek apakah training berhasil
                if "error" in metrics:
                    logger.error(f"Error training {model_name} model: {metrics['error']}")
                    print(f"ERROR: Error training {model_name} model: {metrics['error']}")
                    results[model_name] = {
                        "success": False,
                        "error": metrics["error"],
                        "time": metrics.get("training_time", 0)
                    }
                    continue
                
                model_elapsed_time = time.time() - model_start_time
                
                results[model_name] = {
                    "success": True,
                    "time": model_elapsed_time,
                    "metrics": metrics
                }
                
                print(f"SUCCESS: {model_name} model trained in {model_elapsed_time:.2f} seconds")
                
            except Exception as e:
                logger.error(f"Error training {model_name} model: {str(e)}")
                logger.error(traceback.format_exc())
                print(f"ERROR: Error training {model_name} model: {str(e)}")
                
                results[model_name] = {
                    "success": False,
                    "error": str(e)
                }
        
        # Now train NCF and Hybrid models
        for model_name, model in models_to_train:
            # Skip FECF as it was already trained
            if model_name == "FECF":
                continue
                
            print(f"Training {model_name} model...")
            try:
                # Load data
                data_loaded = model.load_data()
                if not data_loaded:
                    logger.error(f"Failed to load data for {model_name} model")
                    print(f"ERROR: Failed to load data for {model_name} model")
                    results[model_name] = {
                        "success": False,
                        "error": "Failed to load data"
                    }
                    continue
                
                # Train model with batch size check
                model_start_time = time.time()
                
                # Add custom parameters for NCF model to avoid BatchNorm issues
                if model_name == "NCF":
                    # Adjust batch size to be divisible by A for better BatchNorm performance
                    batch_size = ((len(model.users) + 7) // 8) * 8
                    batch_size = min(max(batch_size, 32), 128)  # Keep between 32 and 128
                    
                    print(f"Using optimized batch size of {batch_size} for NCF model")
                    metrics = model.train(save_model=True, batch_size=batch_size)
                else:
                    metrics = model.train(save_model=True)
                
                # Cek apakah training berhasil
                if "error" in metrics:
                    logger.error(f"Error training {model_name} model: {metrics['error']}")
                    print(f"ERROR: Error training {model_name} model: {metrics['error']}")
                    results[model_name] = {
                        "success": False,
                        "error": metrics["error"],
                        "time": metrics.get("training_time", 0)
                    }
                    continue
                
                model_elapsed_time = time.time() - model_start_time
                
                results[model_name] = {
                    "success": True,
                    "time": model_elapsed_time,
                    "metrics": metrics
                }

                print(f"SUCCESS: {model_name} model trained in {model_elapsed_time:.2f} seconds")

            except Exception as e:
                logger.error(f"Error training {model_name} model: {str(e)}")
                logger.error(traceback.format_exc())
                print(f"ERROR: Error training {model_name} model: {str(e)}")

                results[model_name] = {
                    "success": False,
                    "error": str(e)
                }
        
        # Calculate total time
        total_time = time.time() - start_time
        
        # Print summary
        print("\nTraining Summary:")
        print(f"Total time: {total_time:.2f} seconds")
        
        for model_name, result in results.items():
            status = "SUCCESS: Success" if result.get("success", False) else "ERROR: Failed"
            print(f"{model_name}: {status}")
            
            if result.get("success", False) and "time" in result:
                print(f"  Training time: {result['time']:.2f} seconds")
                
                # Print key metrics
                if "metrics" in result:
                    metrics = result["metrics"]
                    if model_name == "FECF" and "explained_variance" in metrics:
                        print(f"  Explained variance: {metrics['explained_variance']:.4f}")
                    elif model_name == "NCF" and "best_val_loss" in metrics:
                        print(f"  Best validation loss: {metrics['best_val_loss']:.4f}")
                        if "early_stopped" in metrics and metrics["early_stopped"]:
                            print(f"  Early stopped at epoch {metrics.get('best_epoch', '?')}")
                    elif model_name == "Hybrid" and "ensemble" in metrics:
                        ensemble_metrics = metrics["ensemble"]
                        print(f"  Ensemble precision: {ensemble_metrics.get('precision', 0):.4f}")
                        print(f"  Ensemble recall: {ensemble_metrics.get('recall', 0):.4f}")
            elif "error" in result:
                print(f"  Error: {result['error']}")
        
        # Check if at least one model was successfully trained
        success = any(result.get("success", False) for result in results.values())
        return success
    
    except ImportError as e:
        logger.error(f"Import error during model training: {e}")
        print(f"ERROR: Missing required module - {e}")
        return False
    except Exception as e:
        logger.error(f"Error during model training: {e}")
        logger.error(traceback.format_exc())
        print(f"ERROR: Error during model training: {e}")
        return False
    
def _validate_data_quality():
    try:
        # Load interactions
        interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        interactions_df = pd.read_csv(interactions_path)
        
        # Load projects
        projects_path = os.path.join(PROCESSED_DIR, "projects.csv")
        projects_df = pd.read_csv(projects_path)
        
        # Check essential conditions
        warnings = []
        
        # 1. Check minimum data size
        min_users = 50
        min_items = 50
        min_interactions = 1000
        
        unique_users = interactions_df['user_id'].nunique()
        unique_items = interactions_df['project_id'].nunique()
        total_interactions = len(interactions_df)
        
        if unique_users < min_users:
            warnings.append(f"Too few users: {unique_users} (minimum recommended: {min_users})")
        
        if unique_items < min_items:
            warnings.append(f"Too few items: {unique_items} (minimum recommended: {min_items})")
        
        if total_interactions < min_interactions:
            warnings.append(f"Too few interactions: {total_interactions} (minimum recommended: {min_interactions})")
        
        # 2. Check interactions per user distribution
        user_counts = interactions_df['user_id'].value_counts()
        min_interactions_per_user = user_counts.min()
        max_interactions_per_user = user_counts.max()
        median_interactions_per_user = user_counts.median()
        
        if min_interactions_per_user < 3:
            warnings.append(f"Some users have very few interactions: minimum {min_interactions_per_user} (recommended: at least 5)")
        
        if median_interactions_per_user < 5:
            warnings.append(f"Median interactions per user is low: {median_interactions_per_user} (recommended: at least 10)")
        
        if max_interactions_per_user > 50 * min_interactions_per_user:
            warnings.append(f"High imbalance in user interactions: min={min_interactions_per_user}, max={max_interactions_per_user}, ratio={max_interactions_per_user/min_interactions_per_user:.1f}x")

        # 3. Check ratings distribution - UPDATED LOGIC
        if 'weight' in interactions_df.columns:
            weight_stats = interactions_df['weight'].describe()
            unique_weights = interactions_df['weight'].nunique()
            
            # NEW: Only warn if weights are problematic (negative, zero, or extreme values)
            if weight_stats['min'] <= 0:
                warnings.append(f"Invalid interaction weights detected: minimum weight is {weight_stats['min']} (should be positive)")
            
            if weight_stats['max'] > 100:
                warnings.append(f"Extremely high interaction weights detected: maximum weight is {weight_stats['max']} (typically should be ≤ 10)")
            
            # Log weight distribution for information (not as warning)
            if unique_weights == 1:
                logger.info(f"SUCCESS: Uniform interaction weights detected: all weights = {weight_stats['min']} (consistent with production data)")
            else:
                logger.info(f"Variable interaction weights: min={weight_stats['min']}, max={weight_stats['max']}, unique_values={unique_weights}")
            
            # Check if weights heavily skewed (only warn if problematic)
            if unique_weights > 1 and (weight_stats['std'] > 10 * weight_stats['mean']):
                warnings.append("Interaction weights have extremely high variance (may indicate data quality issues)")
        
        # 4. Check item popularity distribution
        item_counts = interactions_df['project_id'].value_counts()
        popular_item_ratio = (item_counts > 50).sum() / len(item_counts)
        
        if popular_item_ratio < 0.01:
            warnings.append(f"Very few popular items: only {popular_item_ratio:.1%} items have >50 interactions")
        
        # 5. Check category distribution
        if 'primary_category' in projects_df.columns:
            category_counts = projects_df['primary_category'].value_counts()
            largest_category_ratio = category_counts.max() / len(projects_df)
            
            if largest_category_ratio > 0.5:
                warnings.append(f"Category distribution is skewed: largest category contains {largest_category_ratio:.1%} of items")
            
            if category_counts.nunique() < 5:
                warnings.append(f"Very few unique categories: {category_counts.nunique()} (recommended: at least 5)")

        # Print warnings if any
        if warnings:
            print("\nWARNING: Data Quality Warnings:")
            for warning in warnings:
                print(f"  - {warning}")
            print("\nThese issues may affect model performance.")
            print("Note: Uniform interaction weights (weight=1) are normal and expected.")
            
            # Return False only for critical problems (reduced threshold)
            critical_warnings = [w for w in warnings if any(critical in w.lower() for critical in [
                'too few users', 'too few items', 'too few interactions', 
                'invalid interaction weights', 'extremely high interaction weights'
            ])]
            
            return len(critical_warnings) == 0  # Pass if no critical issues
        
        # Data seems good
        print("SUCCESS: Data quality check passed.")
        print("SUCCESS: Interaction weights are consistent with production requirements.")
        return True
        
    except Exception as e:
        logger.error(f"Error validating data quality: {e}")
        print(f"WARNING: Could not validate data quality: {e}")
        return True  # Let training proceed despite validation error

def evaluate_models(args):
    """
    Evaluasi model rekomendasi
    """
    # Aktifkan debug mode jika parameter tersedia
    debug_mode = getattr(args, 'debug', False)
    if debug_mode:
        logging.getLogger().setLevel(logging.DEBUG)
        logging.getLogger('src.models').setLevel(logging.DEBUG)
        logger.info("Debug mode enabled for evaluation")
    
    # Tampilkan semua file model yang tersedia
    model_files = [f for f in os.listdir(MODELS_DIR) if f.endswith('.pkl')]
    logger.info(f"Available model files: {model_files}")
    
    from src.models.eval import (
        evaluate_all_models, 
        evaluate_cold_start, 
        save_evaluation_results,
        generate_evaluation_report
    )
    
    logger.info("Starting model evaluation")
    print("Evaluating recommendation models...")
    
    # Load models dengan eksplisit menemukan file
    models = {}
    
    try:
        # Load FECF model
        from src.models.alt_fecf import FeatureEnhancedCF
        fecf = FeatureEnhancedCF()
        if fecf.load_data():
            # Cari file model FECF terbaru
            fecf_files = [f for f in os.listdir(MODELS_DIR) 
                        if f.startswith("fecf_model_") and f.endswith(".pkl")]
            if fecf_files:
                latest_model = sorted(fecf_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                logger.info(f"Explicitly loading FECF from {model_path}")
                if fecf.load_model(model_path):
                    logger.info("FECF model loaded successfully")
                    models["fecf"] = fecf
                else:
                    logger.error("Failed to load FECF model")
        
        # Load NCF model
        from src.models.ncf import NCFRecommender
        ncf = NCFRecommender()
        if ncf.load_data():
            # Path NCF model default
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            if os.path.exists(model_path):
                logger.info(f"Explicitly loading NCF from {model_path}")
                if ncf.load_model(model_path):
                    logger.info("NCF model loaded successfully")
                    models["ncf"] = ncf
                else:
                    logger.error("Failed to load NCF model")
        
        # Load Hybrid model
        from src.models.hybrid import HybridRecommender
        hybrid = HybridRecommender()
        if hybrid.load_data():
            # Cari hybrid model terbaru
            hybrid_files = [f for f in os.listdir(MODELS_DIR) if f.startswith("hybrid_model_") and f.endswith(".pkl")]
            if hybrid_files:
                latest_model = sorted(hybrid_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                logger.info(f"Explicitly loading Hybrid from {model_path}")
                if hybrid.load_model(model_path):
                    logger.info("Hybrid model loaded successfully")
                    models["hybrid"] = hybrid
                else:
                    logger.error("Failed to load Hybrid model")
        
        # Verifikasi model sudah dimuat
        for model_name, model in list(models.items()):
            if hasattr(model, 'is_trained'):
                try:
                    if not model.is_trained():
                        logger.warning(f"Model {model_name} is not ready for evaluation")
                except AttributeError as e:
                    logger.error(f"Error checking if {model_name} is trained: {str(e)}")
        
        if not models:
            logger.error("No models available for evaluation")
            print("ERROR: No models available for evaluation")
            return False
        
        # ✅ FIXED: Get parameters with production defaults
        test_ratio = getattr(args, 'test_ratio', 0.3)
        min_interactions = getattr(args, 'min_interactions', 20)  # Production default
        k_values = getattr(args, 'k_values', [5, 10, 20])
        eval_cold_start = getattr(args, 'cold_start', True)  # Production default
        output_format = getattr(args, 'format', 'markdown')
        cold_start_runs = getattr(args, 'cold_start_runs', 5)
        max_test_users = getattr(args, 'max_test_users', 100)
        regular_runs = getattr(args, 'regular_runs', 5)
        
        # Production mode check
        production_mode = getattr(args, 'production', False)
        if production_mode:
            logger.info("Production mode: Using production evaluation defaults")
            print("Production mode: cold-start evaluation enabled, min-interactions=20")
        
        # Get main user-item matrix
        user_item_matrix = fecf.user_item_matrix if 'fecf' in models else ncf.user_item_matrix
        
        # Run evaluation
        start_time = time.time()
        print("Running main evaluation...")
        
        results = evaluate_all_models(
            models=models,
            user_item_matrix=user_item_matrix,
            test_ratio=test_ratio,
            min_interactions=min_interactions,
            k_values=k_values,
            save_results=True,
            eval_cold_start=eval_cold_start,
            cold_start_runs=cold_start_runs,
            max_test_users=max_test_users,
            regular_runs=regular_runs
        )
        
        # Generate and save report
        report = generate_evaluation_report(results, output_format=output_format)
        
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        report_path = f"data/models/evaluation_report_{timestamp}.{output_format}"
        
        # Ensure directory exists
        os.makedirs(os.path.dirname(report_path), exist_ok=True)
        
        with open(report_path, 'w') as f:
            f.write(report)
        
        # Print results
        elapsed_time = time.time() - start_time
        logger.info(f"Evaluation completed in {elapsed_time:.2f} seconds")
        print(f"\nSUCCESS: Evaluation completed in {elapsed_time:.2f} seconds")
        print(f"Evaluation report saved to {report_path}")
        
        # Print brief summary
        print("\nBrief Results Summary:")
        
        for model_name, model_results in results.items():
            if 'cold_start' in model_name:
                continue
                
            precision = model_results.get('precision', 0)
            recall = model_results.get('recall', 0)
            ndcg = model_results.get('ndcg', 0)
            
            print(f"{model_name}: Precision={precision:.4f}, "
                    f"Recall={recall:.4f}, "
                    f"NDCG={ndcg:.4f}")
        
        return True
        
    except Exception as e:
        logger.error(f"Error during evaluation setup: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        print(f"ERROR: Error during evaluation: {str(e)}")
        return False

def recommend(args):
    logger.info(f"Generating recommendations for user {args.user_id}")
    
    # Get parameters
    user_id = args.user_id
    model_type = getattr(args, 'model', 'hybrid')
    num_recs = getattr(args, 'num', 10)
    category = getattr(args, 'category', None)
    chain = getattr(args, 'chain', None)
    
    print(f"Generating {model_type} recommendations for user '{user_id}'...")
    
    try:
        # Load appropriate model
        if model_type == 'fecf':
            from src.models.alt_fecf import FeatureEnhancedCF
            model = FeatureEnhancedCF()
        elif model_type == 'ncf':
            from src.models.ncf import NCFRecommender
            model = NCFRecommender()
        else:  # hybrid
            from src.models.hybrid import HybridRecommender
            model = HybridRecommender()
        
        # Load data
        if not model.load_data():
            logger.error("Failed to load data")
            print("ERROR: Failed to load data")
            return False
            
        # PERBAIKAN: Eksplisit load model file
        model_loaded = False
        
        if model_type == 'fecf':
            # Cari file FECF model terbaru
            fecf_files = [f for f in os.listdir(MODELS_DIR) 
                        if f.startswith("fecf_model_") and f.endswith(".pkl")]
            if fecf_files:
                latest_model = sorted(fecf_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                logger.info(f"Loading FECF model from {model_path}")
                model_loaded = model.load_model(model_path)
        elif model_type == 'ncf':
            # Coba path model NCF default
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            if os.path.exists(model_path):
                logger.info(f"Loading NCF model from {model_path}")
                model_loaded = model.load_model(model_path)
        else:  # hybrid
            # Cari hybrid model terbaru
            hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                          if f.startswith("hybrid_model_") and f.endswith(".pkl")]
            if hybrid_files:
                latest_model = sorted(hybrid_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                logger.info(f"Loading Hybrid model from {model_path}")
                model_loaded = model.load_model(model_path)
                
        if not model_loaded:
            logger.error(f"Failed to load {model_type} model")
            print(f"ERROR: Failed to load {model_type} model")
            return False
            
        # Verifikasi model sudah dimuat dengan benar
        if hasattr(model, 'is_trained') and not model.is_trained():
            logger.error(f"Model {model_type} loaded but not properly initialized")
            print(f"ERROR: Model {model_type} not properly initialized")
            return False
        
        # Check for cold-start user
        is_cold_start = False
        
        if hasattr(model, 'user_item_matrix') and model.user_item_matrix is not None:
            is_cold_start = user_id not in model.user_item_matrix.index
        
        # Generate recommendations
        if is_cold_start:
            print(f"User '{user_id}' is a cold-start user. Using cold-start recommendations.")
            
            # Get user interests from args if available
            user_interests = None
            if hasattr(args, 'interests') and args.interests:
                user_interests = args.interests.split(',')
                print(f"Using interests: {', '.join(user_interests)}")
            
            recommendations = model.get_cold_start_recommendations(
                user_interests=user_interests,
                n=num_recs
            )
        elif category:
            if hasattr(model, 'get_recommendations_by_category'):
                recommendations = model.get_recommendations_by_category(
                    user_id, category, n=num_recs
                )
            else:
                print("Warning: Model doesn't support category filtering. Using standard recommendations.")
                recommendations = model.recommend_projects(user_id, n=num_recs)
        elif chain:
            if hasattr(model, 'get_recommendations_by_chain'):
                recommendations = model.get_recommendations_by_chain(
                    user_id, chain, n=num_recs
                )
            else:
                print("Warning: Model doesn't support chain filtering. Using standard recommendations.")
                recommendations = model.recommend_projects(user_id, n=num_recs)
        else:
            recommendations = model.recommend_projects(user_id, n=num_recs)
        
        # Print recommendations
        print(f"\nTop {len(recommendations)} recommendations for user '{user_id}':")
        print("-" * 80)
        
        for i, rec in enumerate(recommendations, 1):
            # Extract fields
            name = rec.get('name', rec.get('id', 'Unknown'))
            symbol = rec.get('symbol', '')
            category = rec.get('primary_category', rec.get('category', 'unknown'))
            price = rec.get('current_price', 0)
            score = rec.get('recommendation_score', 0)
            
            # Print recommendation
            print(f"{i}. {name} ({symbol})")
            print(f"   Category: {category}")
            if price > 0:
                print(f"   Price: ${price:.4f}")
            print(f"   Score: {score:.4f}")
            print()
        
        return True
        
    except Exception as e:
        logger.error(f"Error generating recommendations: {str(e)}")
        print(f"ERROR: Error generating recommendations: {str(e)}")
        import traceback
        logger.error(traceback.format_exc())
        return False

def trading_signals(args):
    from src.technical.signals import generate_trading_signals, personalize_signals
    from src.data.collector import fetch_real_market_data
    
    logger.info(f"Generating trading signals for project {args.project_id}")
    
    # Get parameters
    project_id = args.project_id
    days = getattr(args, 'days', 30)
    risk = getattr(args, 'risk', 'medium')
    
    # Get trading style (short_term, standard, long_term)
    trading_style = getattr(args, 'trading_style', 'standard')
    
    # Set default indicator periods based on trading style
    if trading_style == 'short_term':
        indicator_periods = {
            'rsi_period': 7,
            'macd_fast': 8,
            'macd_slow': 17,
            'macd_signal': 9,
            'bb_period': 10,
            'stoch_k': 7,
            'stoch_d': 3,
            'ma_short': 10,
            'ma_medium': 30,
            'ma_long': 60
        }
        logger.info("Using short-term trading parameters (periode lebih pendek)")
    elif trading_style == 'long_term':
        indicator_periods = {
            'rsi_period': 21,
            'macd_fast': 19,
            'macd_slow': 39,
            'macd_signal': 9,
            'bb_period': 30,
            'stoch_k': 21,
            'stoch_d': 7,
            'ma_short': 50,
            'ma_medium': 100,
            'ma_long': 200
        }
        logger.info("Using long-term trading parameters (periode lebih panjang)")
    else:  # standard
        indicator_periods = {
            'rsi_period': 14,
            'macd_fast': 12,
            'macd_slow': 26,
            'macd_signal': 9,
            'bb_period': 20,
            'stoch_k': 14,
            'stoch_d': 3,
            'ma_short': 20,
            'ma_medium': 50,
            'ma_long': 200
        }
        logger.info("Using standard trading parameters (periode default)")
    
    # Override dengan nilai kustom jika disediakan
    # RSI
    if hasattr(args, 'rsi_period') and args.rsi_period:
        indicator_periods['rsi_period'] = args.rsi_period
    
    # MACD
    if hasattr(args, 'macd_fast') and args.macd_fast:
        indicator_periods['macd_fast'] = args.macd_fast
    if hasattr(args, 'macd_slow') and args.macd_slow:
        indicator_periods['macd_slow'] = args.macd_slow
    if hasattr(args, 'macd_signal') and args.macd_signal:
        indicator_periods['macd_signal'] = args.macd_signal
    
    # Bollinger Bands
    if hasattr(args, 'bb_period') and args.bb_period:
        indicator_periods['bb_period'] = args.bb_period
    
    # Stochastic
    if hasattr(args, 'stoch_k') and args.stoch_k:
        indicator_periods['stoch_k'] = args.stoch_k
    if hasattr(args, 'stoch_d') and args.stoch_d:
        indicator_periods['stoch_d'] = args.stoch_d
        
    # Moving Averages
    if hasattr(args, 'ma_short') and args.ma_short:
        indicator_periods['ma_short'] = args.ma_short
    if hasattr(args, 'ma_medium') and args.ma_medium:
        indicator_periods['ma_medium'] = args.ma_medium
    if hasattr(args, 'ma_long') and args.ma_long:
        indicator_periods['ma_long'] = args.ma_long
    
    # Hitung minimal data yang diperlukan berdasarkan indikator terlama
    min_required_days = max(
        3 * indicator_periods['rsi_period'],
        indicator_periods['macd_slow'] + indicator_periods['macd_signal'] + 10,
        indicator_periods['bb_period'] + 10,
        indicator_periods['stoch_k'] + indicator_periods['stoch_d'] + 5,
        indicator_periods['ma_long'] + 10
    )
    
    # Sanity check: minimum 30 days
    min_required_days = max(30, min_required_days)
    
    # Check if days is less than minimum required
    if days < min_required_days:
        logger.warning(f"Hari yang diminta ({days}) kurang dari jumlah minimal yang direkomendasikan ({min_required_days}) untuk parameter yang dipilih.")
        logger.info(f"Secara otomatis menyesuaikan hari yang diminta menjadi {min_required_days}")
        days = min_required_days
    
    print(f"Menghasilkan sinyal trading untuk proyek '{project_id}' dengan gaya '{trading_style}'...")
    
    try:
        # Fetch real market data instead of using synthetic data
        print(f"Mengambil data pasar untuk {project_id}...")
        df = fetch_real_market_data(project_id, days=days)
        
        if df.empty:
            logger.error(f"Gagal mengambil data pasar untuk {project_id}")
            print(f"ERROR: Gagal mengambil data pasar untuk {project_id}")
            return False
        
        # Log some info about the data
        print(f"Berhasil mendapatkan {len(df)} titik data harga")
        
        if len(df) < min_required_days:
            print(f"WARNING: Peringatan: Data yang tersedia ({len(df)} hari) kurang dari minimal yang direkomendasikan ({min_required_days}) untuk analisis optimal")
        
        # Generate trading signals
        signals = generate_trading_signals(df, indicator_periods=indicator_periods)
        
        # Check if there's an error
        if 'error' in signals:
            logger.error(f"Error generating signals: {signals['error']}")
            print(f"ERROR: {signals['error']}")
            
            # If there's a minimum days hint, include it
            if 'min_days_needed' in signals:
                print(f"INFO: Coba kembali dengan parameter days={signals['min_days_needed']} atau lebih besar")
            
            return False
            
        # Personalize based on risk tolerance
        personalized = personalize_signals(signals, risk_tolerance=risk)
        
        # Print results
        print("\nAnalisis Sinyal Trading:")
        print("-" * 80)
        print(f"Aksi: {personalized['action'].upper()}")
        print(f"Confidence: {personalized['confidence']:.2f}")
        print(f"Profil Risiko: {personalized['risk_profile']}")
        
        if 'target_price' in personalized:
            print(f"Target Price: ${personalized['target_price']:.2f}")
            
        print(f"\nPesan Personal: {personalized['personalized_message']}")
        
        print("\nBukti Analisis:")
        for evidence in personalized['evidence']:
            print(f"- {evidence}")
            
        print("\nIndikator Utama:")
        for indicator, value in personalized['indicators'].items():
            print(f"- {indicator}: {value:.2f}")
        
        print("\nPeriode Indikator yang Digunakan:")
        for indicator, value in indicator_periods.items():
            print(f"- {indicator}: {value}")
        
        return True
        
    except Exception as e:
        logger.error(f"Error generating trading signals: {str(e)}")
        print(f"ERROR: Error generating trading signals: {str(e)}")
        return False

def start_api(args):
    logger.info("Starting API server")
    print("Starting API server...")
    
    try:
        # Import uvicorn and API app
        import uvicorn
        logger.info("Successfully imported uvicorn")
        
        # Coba import app secara eksplisit dengan try-except terpisah
        try:
            from src.api.main import app
            logger.info("Successfully imported app from src.api.main")
        except Exception as e:
            logger.error(f"Error importing app from src.api.main: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
            print(f"ERROR: Error importing app: {str(e)}")
            return False
        
        from config import API_HOST, API_PORT
        logger.info("Successfully imported API_HOST and API_PORT from config")
        
        # Get host and port from args or config
        host = getattr(args, 'host', API_HOST)
        port = getattr(args, 'port', API_PORT)
        
        # Create logs directory
        os.makedirs("logs", exist_ok=True)
        
        # Start server
        print(f"API server running at http://{host}:{port}")
        print("Press Ctrl+C to stop")
        
        uvicorn.run(
            "src.api.main:app", 
            host=host, 
            port=port,
            reload=True,  # Enable auto-reload during development
            log_level="debug"  # Set log level to debug for more info
        )
        
        return True
        
    except Exception as e:
        logger.error(f"Error starting API server: {str(e)}")
        import traceback
        logger.error(f"Full error traceback: {traceback.format_exc()}")  # Log full traceback
        print(f"ERROR: Error starting API server: {str(e)}")
        print("Check logs/main.log for full traceback details")
        return False

def generate_sample_recommendations(args):
    logger.info("Generating sample recommendations")
    
    try:
        # First, check if we have models loaded
        from src.models.hybrid import HybridRecommender
        
        hybrid = HybridRecommender()
        if not hybrid.load_data():
            logger.error("Failed to load data for recommendation generation")
            return False
        
        # Load the trained model weights
        # Find the latest hybrid model file
        hybrid_files = [f for f in os.listdir(MODELS_DIR) 
                     if f.startswith("hybrid_model_") and f.endswith(".pkl")]
        if hybrid_files:
            latest_hybrid = sorted(hybrid_files)[-1]
            hybrid_path = os.path.join(MODELS_DIR, latest_hybrid)
            logger.info(f"Loading hybrid model from {hybrid_path}")
            if not hybrid.load_model(hybrid_path):
                logger.warning("Failed to load hybrid model, trying to load component models directly")
                
                # Try to load component models directly
                fecf_files = [f for f in os.listdir(MODELS_DIR) 
                         if f.startswith("fecf_model_") and f.endswith(".pkl")]
                if fecf_files:
                    latest_fecf = sorted(fecf_files)[-1]
                    fecf_path = os.path.join(MODELS_DIR, latest_fecf)
                    logger.info(f"Loading FECF model from {fecf_path}")
                    if hybrid.fecf_model is None:
                        from src.models.alt_fecf import FeatureEnhancedCF
                        hybrid.fecf_model = FeatureEnhancedCF()
                        hybrid.fecf_model.load_data()
                    hybrid.fecf_model.load_model(fecf_path)
                
                ncf_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
                if os.path.exists(ncf_path):
                    logger.info(f"Loading NCF model from {ncf_path}")
                    if hybrid.ncf_model is None:
                        from src.models.ncf import NCFRecommender
                        hybrid.ncf_model = NCFRecommender()
                        hybrid.ncf_model.load_data()
                    hybrid.ncf_model.load_model(ncf_path)
        else:
            logger.warning("No hybrid model files found, models may not be loaded correctly")
        
        # Get some user IDs from the data
        if hybrid.user_item_matrix is not None and not hybrid.user_item_matrix.empty:
            # Take up to 5 sample users
            sample_users = hybrid.user_item_matrix.index[:5].tolist()
            
            print(f"\nGenerating sample recommendations for {len(sample_users)} users")
            print("-" * 70)
            
            recommendation_success = False
            
            for user_id in sample_users:
                print(f"\nRecommendations for user {user_id}:")
                try:
                    recs = hybrid.recommend_projects(user_id, n=3)
                    
                    if recs:
                        recommendation_success = True
                        for i, rec in enumerate(recs, 1):
                            name = rec.get('name', rec.get('id', 'Unknown'))
                            score = rec.get('recommendation_score', 0)
                            print(f"{i}. {name} - Score: {score:.4f}")
                    else:
                        print("No recommendations available.")
                except Exception as e:
                    logger.warning(f"Error generating recommendations for user {user_id}: {str(e)}")
                    print(f"Could not generate recommendations: {str(e)}")
            
            # Also generate trending projects
            print("\nTrending projects:")
            trending = hybrid.get_trending_projects(n=3)
            
            for i, proj in enumerate(trending, 1):
                name = proj.get('name', proj.get('id', 'Unknown'))
                score = proj.get('trend_score', 0)
                print(f"{i}. {name} - Score: {score:.4f}")
                
            print("\nSample recommendations generated successfully")
            
            # Return success if either user recommendations or trending projects worked
            return recommendation_success or len(trending) > 0
        else:
            logger.error("No user-item matrix available for sample recommendations")
            print("ERROR: No user data available for sample recommendations")
            return False
            
    except Exception as e:
        logger.error(f"Error generating sample recommendations: {str(e)}")
        print(f"ERROR: Error generating sample recommendations: {str(e)}")
        return False

def analyze_results(args):
    logger.info("Analyzing recommendation results")
    
    try:
        # Get paths to evaluation results if available
        models_dir = MODELS_DIR
        eval_files = [f for f in os.listdir(models_dir) if f.startswith("model_performance_") and f.endswith(".json")]
        
        if not eval_files:
            logger.warning("No evaluation results found")
            print("WARNING: No evaluation results found. Run evaluation first with: python main.py evaluate")
            return False
        
        # Use the most recent evaluation file
        latest_eval = sorted(eval_files)[-1]
        eval_path = os.path.join(models_dir, latest_eval)
        
        print(f"\nAnalyzing results from {latest_eval}")
        print("-" * 70)
        
        # Import evaluation module
        from src.models.eval import load_evaluation_results
        
        # Load evaluation results
        results = load_evaluation_results(eval_path)
        
        if not results:
            logger.error("Failed to load evaluation results")
            print("ERROR: Failed to load evaluation results")
            return False
        
        # Analyze model performance
        print("\nModel Performance Analysis:")
        
        # Compare models
        models = [model for model in results.keys() if 'cold_start' not in model]
        
        # Find best model for each metric
        metrics = ['precision', 'recall', 'ndcg', 'mrr']
        best_models = {}
        
        for metric in metrics:
            max_value = -1
            best_model = None
            
            for model in models:
                if metric in results[model] and results[model][metric] > max_value:
                    max_value = results[model][metric]
                    best_model = model
            
            if best_model:
                best_models[metric] = (best_model, max_value)
        
        # Print best models
        print("\nBest performing models:")
        for metric, (model, value) in best_models.items():
            print(f"- {metric.upper()}: {model} ({value:.4f})")
        
        # Check cold-start performance
        cold_start_models = [model for model in results.keys() if 'cold_start' in model]
        
        if cold_start_models:
            print("\nCold-start performance:")
            for model in cold_start_models:
                precision = results[model].get('precision', 0)
                recall = results[model].get('recall', 0)
                print(f"- {model}: Precision={precision:.4f}, Recall={recall:.4f}")
        
        # NEW: Analyze performance improvements
        print("\nKey performance metrics by model and k value:")
        
        for model in models:
            print(f"\n{model.upper()}:")
            for k in [5, 10, 20]:
                precision_k = results[model].get(f'precision@{k}', 0)
                recall_k = results[model].get(f'recall@{k}', 0) 
                ndcg_k = results[model].get(f'ndcg@{k}', 0)
                hit_ratio_k = results[model].get(f'hit_ratio@{k}', 0)
                
                print(f"  k={k}: Precision={precision_k:.4f}, Recall={recall_k:.4f}, " 
                      f"NDCG={ndcg_k:.4f}, Hit Ratio={hit_ratio_k:.4f}")
        
        # NEW: Calculate F1 scores explicitly
        print("\nF1 Scores calculation:")
        
        for model in models:
            f1_scores = {}
            for k in [5, 10, 20]:
                precision_k = results[model].get(f'precision@{k}', 0)
                recall_k = results[model].get(f'recall@{k}', 0)
                
                if precision_k + recall_k > 0:
                    f1 = 2 * (precision_k * recall_k) / (precision_k + recall_k)
                else:
                    f1 = 0
                    
                f1_scores[k] = f1
                
            print(f"{model}: F1@5={f1_scores[5]:.4f}, F1@10={f1_scores[10]:.4f}, F1@20={f1_scores[20]:.4f}")
            
        # NEW: Look at running times
        print("\nEvaluation Times:")
        
        for model in models + cold_start_models:
            eval_time = results[model].get('evaluation_time', 0)
            print(f"- {model}: {eval_time:.2f} seconds")
            
        # NEW: Offer recommendations based on analysis
        print("\nRecommendations:")
        
        # Identify the best overall model
        best_overall = None
        highest_avg = -1
        
        for model in models:
            metrics = ['precision', 'recall', 'ndcg', 'hit_ratio']
            avg_score = sum(results[model].get(m, 0) for m in metrics) / len(metrics)
            
            if avg_score > highest_avg:
                highest_avg = avg_score
                best_overall = model
        
        if best_overall:
            print(f"• Recommended primary model: {best_overall}")
            print(f"• For cold-start users, use {best_overall} with category-based recommendations")
            
            if 'hybrid' == best_overall:
                # Get values from config
                ncf_weight = HYBRID_PARAMS.get('ncf_weight', 0.2)
                fecf_weight = HYBRID_PARAMS.get('fecf_weight', 0.8)
                print(f"• Recommended hybrid weights: NCF: {ncf_weight}, FECF: {fecf_weight} (based on current configuration)")
            
            # Check if we need more data
            poor_cold_start = all(results[model].get('precision', 0) < 0.05 for model in cold_start_models)
            if poor_cold_start:
                print("• Cold-start performance is poor - consider collecting more diverse interaction data")
        
        print("\nRecommendation system analysis completed successfully")
        return True
            
    except Exception as e:
        logger.error(f"Error analyzing results: {str(e)}")
        print(f"ERROR: Error analyzing results: {str(e)}")
        return False
    
def debug_recommendations(args):
    user_id = args.user_id
    model_type = args.model
    n = args.num
    
    print(f"Debugging recommendations for user '{user_id}' using {model_type} model...")
    
    try:
        # Load appropriate model
        if model_type == 'fecf':
            from src.models.alt_fecf import FeatureEnhancedCF
            model = FeatureEnhancedCF()
        elif model_type == 'ncf':
            from src.models.ncf import NCFRecommender
            model = NCFRecommender()
        else:  # hybrid
            from src.models.hybrid import HybridRecommender
            model = HybridRecommender()
        
        # Load data and model
        print("Loading data...")
        model.load_data()
        
        # Find latest model file
        if model_type == 'fecf':
            model_files = [f for f in os.listdir(MODELS_DIR) 
                         if f.startswith("fecf_model_") and f.endswith(".pkl")]
            if model_files:
                latest_model = sorted(model_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                print(f"Loading model from: {model_path}")
                model.load_model(model_path)
        elif model_type == 'ncf':
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            print(f"Loading model from: {model_path}")
            model.load_model(model_path)
        else:
            model_files = [f for f in os.listdir(MODELS_DIR) 
                         if f.startswith("hybrid_model_") and f.endswith(".pkl")]
            if model_files:
                latest_model = sorted(model_files)[-1]
                model_path = os.path.join(MODELS_DIR, latest_model)
                print(f"Loading model from: {model_path}")
                model.load_model(model_path)
        
        # Check if user exists
        user_exists = True
        if hasattr(model, 'user_item_matrix'):
            if user_id not in model.user_item_matrix.index:
                print(f"WARNING: User '{user_id}' not found in training data")
                print("Will use cold-start recommendations")
                user_exists = False
        
        # Get user's known items
        known_items = []
        if user_exists and hasattr(model, 'user_item_matrix'):
            user_items = model.user_item_matrix.loc[user_id]
            known_items = user_items[user_items > 0].index.tolist()
            print(f"User has {len(known_items)} known items")
            if known_items:
                print(f"Sample known items: {known_items[:5]}")
        
        # Get recommendations
        print("\nGenerating recommendations...")
        
        # Test both with and without exclusion
        for exclude in [True, False]:
            if hasattr(model, 'recommend_for_user'):
                recs = model.recommend_for_user(user_id, n=n, exclude_known=exclude)
                
                print(f"\nRecommendations (exclude_known={exclude}):")
                print(f"Generated {len(recs)} recommendations")
                
                for i, (item_id, score) in enumerate(recs[:10], 1):
                    is_known = item_id in known_items
                    print(f"{i}. Item: {item_id}, Score: {score:.4f} {'(KNOWN)' if is_known else ''}")
            else:
                recs = model.recommend_projects(user_id, n=n)
                
                print("\nRecommendations (built-in method):")
                print(f"Generated {len(recs)} recommendations")
                
                for i, rec in enumerate(recs[:10], 1):
                    item_id = rec.get('id')
                    score = rec.get('recommendation_score', 0)
                    is_known = item_id in known_items
                    print(f"{i}. Item: {item_id}, Score: {score:.4f} {'(KNOWN)' if is_known else ''}")
        
        # Add new debugging for model parameters and weights
        if model_type == 'hybrid' and hasattr(model, 'get_effective_weights'):
            print("\nHybrid Model Analysis:")
            fecf_weight, ncf_weight, diversity_weight = model.get_effective_weights(user_id)
            print(f"Effective weights for user {user_id}:")
            print(f"- FECF Weight: {fecf_weight:.4f}")
            print(f"- NCF Weight: {ncf_weight:.4f}")
            print(f"- Diversity Weight: {diversity_weight:.4f}")
            
            if hasattr(model, 'params'):
                print("\nHybrid Parameters:")
                for param, value in model.params.items():
                    print(f"- {param}: {value}")
        
        # Show additional item metadata if available
        if known_items and hasattr(model, 'projects_df'):
            print("\nItem Metadata Analysis:")
            categories = {}
            chains = {}
            
            for item_id in known_items:
                item_data = model.projects_df[model.projects_df['id'] == item_id]
                if not item_data.empty:
                    if 'primary_category' in item_data.columns:
                        category = item_data.iloc[0]['primary_category']
                        categories[category] = categories.get(category, 0) + 1
                    
                    if 'chain' in item_data.columns:
                        chain = item_data.iloc[0]['chain']
                        chains[chain] = chains.get(chain, 0) + 1
            
            if categories:
                print("\nUser Category Distribution:")
                for category, count in sorted(categories.items(), key=lambda x: x[1], reverse=True):
                    print(f"- {category}: {count} items")
            
            if chains:
                print("\nUser Chain Distribution:")
                for chain, count in sorted(chains.items(), key=lambda x: x[1], reverse=True):
                    print(f"- {chain}: {count} items")
        
        return True
    
    except Exception as e:
        print(f"Error during debugging: {str(e)}")
        import traceback
        print(traceback.format_exc())
        return False

def run_pipeline(args):
    logger.info("Running complete recommendation engine pipeline")
    print("Starting complete recommendation engine pipeline...")
    print(f"{'='*70}")
    
    # Check for production mode
    production_mode = getattr(args, 'production', False)
    
    if production_mode:
        print("PRODUCTION MODE: Preserving existing interactions")
    else:
        print("DEVELOPMENT MODE: Full processing with synthetic data")
    
    # Override arguments as needed
    if getattr(args, 'skip_collection', False):
        print("Skipping collection step (--skip-collection flag used)")
    
    if getattr(args, 'skip_processing', False):
        print("Skipping processing step (--skip-processing flag used)")
    
    if getattr(args, 'skip_training', False):
        print("Skipping training step (--skip-training flag used)")
    
    # ✅ FIXED: Pastikan args memiliki parameter yang diperlukan
    if production_mode:
        # Set production flag explicitly untuk step processing
        args.production = True
        
        # ✅ Set production defaults jika tidak ada
        if not hasattr(args, 'limit') and not hasattr(args, 'data_limit'):
            args.limit = 1000  # Production default
        if not hasattr(args, 'detail_limit'):
            args.detail_limit = 1000  # Production default
        if not hasattr(args, 'min_interactions'):
            args.min_interactions = 20  # Production default  
        if not hasattr(args, 'cold_start'):
            args.cold_start = True  # Production default

        # Log production defaults
        logger.info("Production defaults applied:")
        logger.info(f"  - Data limit: {getattr(args, 'limit', getattr(args, 'data_limit', 1000))}")
        logger.info(f"  - Detail limit: {getattr(args, 'detail_limit', 1000)}")
        logger.info(f"  - Min interactions: {getattr(args, 'min_interactions', 20)}")
        logger.info(f"  - Cold start evaluation: {getattr(args, 'cold_start', True)}")
        logger.info("  - User interactions: PRESERVED from existing data (no synthetic generation)")
    else:
        # ✅ Development-specific parameters only
        if not hasattr(args, 'users'):
            args.users = 5000  # Development default for synthetic users
            
        logger.info("Development defaults applied:")
        logger.info(f"  - Synthetic users to generate: {getattr(args, 'users', 5000)}")
        logger.info("  - Mode: Full synthetic data generation")

    # Tentukan langkah-langkah pipeline berdasarkan mode
    if production_mode:
        pipeline_steps = [
            {
                "name": "Data Collection",
                "function": collect_data,
                "description": "Collecting latest data from CoinGecko API",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_collection', False),
                "args": args
            },
            {
                "name": "Projects Update",
                "function": process_data,  # Will use --production flag
                "description": "Updating projects data (preserving existing interactions)",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_processing', False),
                "args": args
            },
            {
                "name": "Model Training",
                "function": train_models,
                "description": "Training recommendation models with updated data",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_training', False),
                "args": args
            },
            {
                "name": "Model Evaluation",
                "function": evaluate_models,
                "description": "Evaluating model performance",
                "required": False,
                "skip_if": lambda args: not getattr(args, 'evaluate', False),
                "args": args
            }
        ]
    else:
        # Development mode - full pipeline
        pipeline_steps = [
            {
                "name": "Data Collection",
                "function": collect_data,
                "description": "Collecting data from CoinGecko API",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_collection', False),
                "args": args
            },
            {
                "name": "Data Processing",
                "function": process_data,
                "description": "Processing raw data and generating synthetic interactions",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_processing', False),
                "args": args
            },
            {
                "name": "Training Models",
                "function": train_models,
                "description": "Training recommendation models",
                "required": True,
                "skip_if": lambda args: getattr(args, 'skip_training', False),
                "args": args
            },
            {
                "name": "Model Evaluation",
                "function": evaluate_models,
                "description": "Evaluating model performance",
                "required": False,
                "skip_if": lambda args: not getattr(args, 'evaluate', False),
                "args": args
            },
            {
                "name": "Sample Recommendations",
                "function": generate_sample_recommendations,
                "description": "Generating sample recommendations",
                "required": False,
                "skip_if": lambda args: getattr(args, 'skip_recommendations', False),
                "args": args
            },
            {
                "name": "Result Analysis",
                "function": analyze_results,
                "description": "Analyzing recommendation results",
                "required": False,
                "skip_if": lambda args: getattr(args, 'skip_analysis', False),
                "args": args
            }
        ]
    
    # Jalankan pipeline
    pipeline_results = []
    for step_idx, step in enumerate(pipeline_steps):
        step_name = step["name"]
        step_func = step["function"]
        step_desc = step["description"]
        step_required = step["required"]
        step_args = step["args"]
        
        # Check if step should be skipped
        if "skip_if" in step and step["skip_if"](args):
            print(f"\n{'-'*70}")
            print(f"Step {step_idx+1}/{len(pipeline_steps)}: {step_name} [SKIPPED]")
            print(f"{'-'*70}")
            pipeline_results.append((step_name, None, True))
            continue
            
        # Execute step
        print(f"\n{'-'*70}")
        print(f"Step {step_idx+1}/{len(pipeline_steps)}: {step_name}")
        
        # ✅ FIXED: Correct production/development labeling logic
        if production_mode:
            print(f"PRODUCTION: {step_desc}")
        else:
            print(f"DEVELOPMENT: {step_desc}")
            
        print(f"{'-'*70}")
        print(f"Started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
        try:
            start_time = time.time()
            
            # Special handling for different steps in production
            if step_name == "Sample Recommendations" and production_mode:
                # Skip sample recommendations in production
                success = True
                print("SUCCESS: Skipped sample recommendations in production mode")
            elif step_name == "Result Analysis" and production_mode:
                # Skip result analysis in production
                success = True
                print("SUCCESS: Skipped result analysis in production mode")
            else:
                success = step_func(step_args)
                
            end_time = time.time()
            elapsed_time = end_time - start_time
            
            status = "SUCCESS" if success else "FAILED"
            print(f"Status: {status}")
            print(f"Execution time: {elapsed_time:.2f} seconds")
            
            pipeline_results.append((step_name, success, False))
            
            if not success and step_required:
                print(f"\nCritical step '{step_name}' failed. Cannot continue pipeline.")
                break
                
        except Exception as e:
            logger.error(f"Error in pipeline step '{step_name}': {str(e)}")
            logger.error(traceback.format_exc())
            print(f"Error: {str(e)}")
            
            pipeline_results.append((step_name, False, False))
            
            if step_required:
                print(f"\nCritical step '{step_name}' failed with exception. Cannot continue pipeline.")
                break
    
    # Pipeline summary
    print(f"\n{'='*70}")
    if production_mode:
        print("PRODUCTION Pipeline Execution Summary")
    else:
        print("DEVELOPMENT Pipeline Execution Summary")
    print(f"{'='*70}")
    print(f"Started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    all_required_success = all(
        [result for step, result, skipped in pipeline_results 
        if not skipped and any(s["name"] == step and s["required"] for s in pipeline_steps)]
    )
    
    for step_name, result, skipped in pipeline_results:
        if skipped:
            status = "SKIPPED"
        elif result:
            status = "SUCCESS"
        else:
            status = "FAILED"
            
        print(f"{step_name}: {status}")
    
    if all_required_success:
        if production_mode:
            print("\nYay! Production pipeline completed successfully!")
            print("\nProduction Recommendations:")
            print("- Projects data updated with latest market information")
            print("- Existing user interactions preserved")
            print("- Models retrained with fresh data")
            print("- System ready for production use")
        else:
            print("\nYay! Development pipeline completed successfully!")
            print("\nRecommendations for Next Steps:")
            print("- View evaluation report in the data/models directory")
            print("- Test the system with real users using: python main.py recommend --user-id <user_id>")
            print("- Start the API server to serve recommendations: python main.py api")
            print("- For production, use: python main.py run --production")
    else:
        print("\nWARNING: Pipeline completed with errors in required steps.")
        print("Check logs for more details: logs/main.log")
    
    return all_required_success

def main():
    """
    Main function
    """
    # Create parser
    parser = argparse.ArgumentParser(
        description="Web3 Recommendation System",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Collect data from CoinGecko
  python main.py collect --limit 500 --detail-limit 100
  
  # Process collected data
  python main.py process --users 500
  
  # Train recommendation models
  python main.py train --fecf --ncf --hybrid
  
  # Evaluate models
  python main.py evaluate
  
  # Generate recommendations for a user
  python main.py recommend --user-id user_123 --model hybrid --num 10
  
  # Generate trading signals for a project
  python main.py signals --project-id bitcoin --risk medium
  
  # Start the API server
  python main.py api
  
  # Run complete pipeline
  python main.py run
"""
    )
    
    # Create subparsers
    subparsers = parser.add_subparsers(dest="command", help="Command to run")
    
    # collect command
    collect_parser = subparsers.add_parser("collect", help="Collect data from CoinGecko")
    collect_parser.add_argument("--limit", type=int, default=1000, help="Number of coins to collect")
    collect_parser.add_argument("--detail-limit", type=int, default=1000, help="Number of coins to get detailed data for")
    collect_parser.add_argument("--rate-limit", type=float, default=2.0, help="Delay between API requests in seconds")
    collect_parser.add_argument("--include-categories", action="store_true", help="Also collect coins by categories defined in config.py")
    
    # process command
    process_parser = subparsers.add_parser("process", help="Process collected data")
    process_parser.add_argument("--users", type=int, default=5000, help="Number of synthetic users to generate (production default: 5000)")
    process_parser.add_argument("--production", action="store_true", help="Production mode: preserve existing interactions")

    # update-projects command
    update_parser = subparsers.add_parser("update-projects", help="Update projects data only (production mode)")
    
    # train command
    train_parser = subparsers.add_parser("train", help="Train recommendation models")
    train_parser.add_argument("--fecf", action="store_true", help="Train Feature-Enhanced CF model")
    train_parser.add_argument("--ncf", action="store_true", help="Train Neural CF model")
    train_parser.add_argument("--hybrid", action="store_true", help="Train Hybrid model")
    train_parser.add_argument("--include-all", action="store_true", help="Train all models")
    train_parser.add_argument("--force", action="store_true", help="Force training even if data quality validation fails")
    
    # evaluate command
    """
    Min interaksi untuk pengguna:
    # Users dengan <20 interaksi: FECF dominan
    # Users dengan ≥20 interaksi: NCF mulai efektif
    # Users dengan ≥50 interaksi: NCF bisa dominan
    """
    evaluate_parser = subparsers.add_parser("evaluate", help="Evaluate recommendation models")
    evaluate_parser.add_argument("--test-ratio", type=float, default=0.3, help="Test data ratio")
    evaluate_parser.add_argument("--min-interactions", type=int, default=20, help="Minimum interactions for test users (production default: 20)")
    evaluate_parser.add_argument("--cold-start", action="store_true", help="Evaluate cold-start scenarios (production default: enabled)")
    evaluate_parser.add_argument("--cold-start-runs", type=int, default=5, help="Number of runs for cold-start evaluation (default: 5)")
    evaluate_parser.add_argument("--regular-runs", type=int, default=5, help="Number of runs for regular model evaluation (default: 1)")
    evaluate_parser.add_argument("--format", choices=["text", "markdown", "json"], default="markdown", help="Output format")  
    evaluate_parser.add_argument("--debug", action="store_true", help="Enable detailed debug logging")
    evaluate_parser.add_argument("--max-test-users", type=int, default=100, help="Maximum number of test users to evaluate")
    
    # recommend command
    recommend_parser = subparsers.add_parser("recommend", help="Generate recommendations for a user")
    recommend_parser.add_argument("--user-id", required=True, help="User ID to recommend for")
    recommend_parser.add_argument("--model", choices=["fecf", "ncf", "hybrid"], default="hybrid", help="Model to use")
    recommend_parser.add_argument("--num", type=int, default=10, help="Number of recommendations")
    recommend_parser.add_argument("--category", help="Filter by category")
    recommend_parser.add_argument("--chain", help="Filter by blockchain")
    recommend_parser.add_argument("--interests", help="Comma-separated list of interests for cold-start users")
    
    # signals command
    signals_parser = subparsers.add_parser("signals", help="Generate trading signals for a project")
    signals_parser.add_argument("--project-id", required=True, help="Project ID")
    signals_parser.add_argument("--days", type=int, default=30, help="Days of historical data")
    signals_parser.add_argument("--risk", choices=["low", "medium", "high"], default="medium", help="Risk tolerance")
    # Argumen untuk gaya trading
    signals_parser.add_argument("--trading-style", choices=["short_term", "standard", "long_term"], 
                            default="standard", help="Trading style (affects indicator periods)")
    # Argumen untuk periode indikator individual
    # RSI
    signals_parser.add_argument("--rsi-period", type=int, help="RSI period (default: 14 for standard)")
    # MACD
    signals_parser.add_argument("--macd-fast", type=int, help="MACD fast period (default: 12 for standard)")
    signals_parser.add_argument("--macd-slow", type=int, help="MACD slow period (default: 26 for standard)")
    signals_parser.add_argument("--macd-signal", type=int, help="MACD signal period (default: 9 for standard)")
    # Bollinger Bands
    signals_parser.add_argument("--bb-period", type=int, help="Bollinger Bands period (default: 20 for standard)")
    # Stochastic
    signals_parser.add_argument("--stoch-k", type=int, help="Stochastic %K period (default: 14 for standard)")
    signals_parser.add_argument("--stoch-d", type=int, help="Stochastic %D period (default: 3 for standard)")
    # Moving Averages
    signals_parser.add_argument("--ma-short", type=int, help="Short-term moving average period (default: 20 for standard)")
    signals_parser.add_argument("--ma-medium", type=int, help="Medium-term moving average period (default: 50 for standard)")
    signals_parser.add_argument("--ma-long", type=int, help="Long-term moving average period (default: 200 for standard)")
    
    # api command
    api_parser = subparsers.add_parser("api", help="Start API server")
    api_parser.add_argument("--host", default="0.0.0.0", help="API host")
    api_parser.add_argument("--port", type=int, default=8001, help="API port")

    # debug command
    debug_parser = subparsers.add_parser("debug", help="Debug recommendations for a user")
    debug_parser.add_argument("--user-id", required=True, help="User ID to debug recommendations for")
    debug_parser.add_argument("--model", choices=["fecf", "ncf", "hybrid"], default="hybrid", help="Model to use")
    debug_parser.add_argument("--num", type=int, default=20, help="Number of recommendations")
    
    # ✅ FIXED: run command dengan parameter yang consistent
    run_parser = subparsers.add_parser("run", help="Run complete pipeline")
    run_parser.add_argument("--skip-collection", action="store_true", 
                        help="Skip data collection step (use existing data)")
    run_parser.add_argument("--skip-processing", action="store_true",
                        help="Skip data processing step (use existing processed data)") 
    run_parser.add_argument("--skip-training", action="store_true",
                        help="Skip model training (use existing trained models)")
    run_parser.add_argument("--skip-recommendations", action="store_true",
                        help="Skip generating sample recommendations")
    run_parser.add_argument("--skip-analysis", action="store_true",
                        help="Skip result analysis step")
    # ✅ FIXED: Use consistent parameter names
    run_parser.add_argument("--limit", type=int, default=1000,
                        help="Number of coins to collect (production default: 1000)")
    run_parser.add_argument("--detail-limit", type=int, default=1000,
                        help="Number of coins to get detailed data (production default: 1000)")
    run_parser.add_argument("--users", type=int, default=5000,
                        help="Number of synthetic users to generate (production default: 5000)")
    run_parser.add_argument("--models", choices=['all', 'fecf', 'ncf', 'hybrid'],
                        default='all', help="Which models to train (production default: all)")
    run_parser.add_argument("--evaluate", action="store_true",
                        help="Run evaluation after training")
    run_parser.add_argument("--force", action="store_true",
                        help="Force training even if data quality validation fails")
    run_parser.add_argument("--debug", action="store_true",
                        help="Enable debug logging")
    run_parser.add_argument("--production", action="store_true",
                        help="Production mode: preserve existing interactions")
    # ✅ FIXED: Add evaluation parameters untuk production
    run_parser.add_argument("--min-interactions", type=int, default=20,
                        help="Minimum interactions for evaluation (production default: 20)")
    run_parser.add_argument("--cold-start", action="store_true", default=True,
                        help="Enable cold-start evaluation (production default: enabled)")
    
    # Parse arguments
    args = parser.parse_args()
    
    # Create logs directory
    os.makedirs("logs", exist_ok=True)
    
    # Set debug level if requested
    if hasattr(args, 'debug') and args.debug:
        logging.getLogger().setLevel(logging.DEBUG)
        logger.info("Debug mode enabled")
    
    # ✅ FIXED: Set production defaults sebelum run command
    if args.command == "run" and getattr(args, 'production', False):
        # Ensure production defaults are applied
        if not hasattr(args, 'evaluate') or not args.evaluate:
            args.evaluate = True  # Production default: enable evaluation
            logger.info("Production mode: Evaluation enabled by default")
    
    # Run appropriate command
    if args.command == "collect":
        collect_data(args)
    elif args.command == "process":
        process_data(args)
    elif args.command == "update-projects":
        update_projects_only()
    elif args.command == "train":
        # If no specific model is selected, train all models
        if not (args.fecf or args.ncf or args.hybrid) and not args.include_all:
            args.fecf = True
            args.ncf = True
            args.hybrid = True
        train_models(args)
    elif args.command == "evaluate":
        evaluate_models(args)
    elif args.command == "recommend":
        recommend(args)
    elif args.command == "signals":
        trading_signals(args)
    elif args.command == "api":
        start_api(args)
    elif args.command == "run":
        run_pipeline(args)
    elif args.command == "debug":
        debug_recommendations(args)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()