import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
import time
import pickle
import json
from datetime import datetime
from pathlib import Path
from scipy.special import expit  # Sigmoid function

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import HYBRID_PARAMS, MODELS_DIR, PROCESSED_DIR, CRYPTO_DOMAIN_WEIGHTS

# Import model components
from src.models.alt_fecf import FeatureEnhancedCF
from src.models.ncf import NCFRecommender

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class HybridRecommender:
    """
    Hybrid Recommender dengan pendekatan ensemble yang ditingkatkan
    Optimasi untuk cryptocurrency domain dengan adaptive ensemble dan diversity boosting
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
        # Model parameters dengan default yang lebih baik
        default_params = {
            "ncf_weight": 0.5,              # PERBAIKAN: Dari 0.35 ke 0.5
            "fecf_weight": 0.5,             # PERBAIKAN: Dari 0.65 ke 0.5
            "interaction_threshold_low": 10,  # PERBAIKAN: Dari 5 ke 10
            "interaction_threshold_high": 30, # PERBAIKAN: Dari 15 ke 30
            "diversity_factor": 0.3,        
            "cold_start_fecf_weight": 0.95,  # PERBAIKAN: Dari 0.75 ke 0.95
            "explore_ratio": 0.30,          
            "normalization": "percentile",   # PERBAIKAN: Dari sigmoid ke percentile
            "ensemble_method": "selective",  # PERBAIKAN: Dari stacking ke selective
            "n_candidates_factor": 3,       
            "category_diversity_weight": 0.25,
            "trending_boost_factor": 0.2,    
            "confidence_threshold": 0.4,     # TAMBAHAN: Threshold untuk NCF confidence
            "min_ncf_interactions": 20,     # TAMBAHAN: Minimal interactions untuk NCF
        }
        
        # Update dengan parameter yang disediakan atau dari config
        self.params = default_params.copy()
        if HYBRID_PARAMS:
            self.params.update(HYBRID_PARAMS)
        if params:
            self.params.update(params)
        
        # Initialize component models
        self.fecf_model = None
        self.ncf_model = None
        
        # Logged ensemble method flag
        self._logged_ensemble_method = False

        # Data
        self.projects_df = None
        self.interactions_df = None
        self.user_item_matrix = None
        
        # Track recommendation sources for analytics
        self.recommendation_sources = {}
        
        # Cache untuk hasil normalisasi dan rekomendasi
        self._normalization_cache = {}
        self._recommendation_cache = {}
        
        # Domain-specific weights for cryptocurrency
        self.crypto_weights = CRYPTO_DOMAIN_WEIGHTS if 'CRYPTO_DOMAIN_WEIGHTS' in globals() else {
            "trend_importance": 0.75,
            "popularity_decay": 0.1,
            "category_correlation": 0.65,
            "market_cap_influence": 0.55,
            "chain_importance": 0.45,
        }
        
        # Performance metrics for adaptive ensemble
        self.model_performance = {
            'fecf': {'precision': 0.0, 'recall': 0.0, 'ndcg': 0.0, 'hit_ratio': 0.0},
            'ncf': {'precision': 0.0, 'recall': 0.0, 'ndcg': 0.0, 'hit_ratio': 0.0}
        }
        
    def load_data(self, 
                 projects_path: Optional[str] = None, 
                 interactions_path: Optional[str] = None,
                 features_path: Optional[str] = None) -> bool:
        try:
            # Initialize component models if needed
            if self.fecf_model is None:
                self.fecf_model = FeatureEnhancedCF()
            
            if self.ncf_model is None:
                self.ncf_model = NCFRecommender()
                
            # Load data for FECF
            fecf_success = self.fecf_model.load_data(
                projects_path=projects_path,
                interactions_path=interactions_path,
                features_path=features_path
            )
            
            # Load data for NCF
            ncf_success = self.ncf_model.load_data(
                projects_path=projects_path,
                interactions_path=interactions_path
            )
            
            if fecf_success and ncf_success:
                # Store references to the data
                self.projects_df = self.fecf_model.projects_df
                self.interactions_df = self.fecf_model.interactions_df
                self.user_item_matrix = self.fecf_model.user_item_matrix
                
                # Pre-process kategori untuk memudahkan penanganan
                self.preprocess_categories()
                
                # Calculate interaction statistics for adaptive weighting
                self._calculate_interaction_statistics()
                
                logger.info("Data loaded successfully for Enhanced Hybrid Recommender")
                return True
            else:
                logger.error("Failed to load data for one or more component models")
                return False
                
        except Exception as e:
            logger.error(f"Error loading data for Enhanced Hybrid Recommender: {str(e)}")
            return False
    
    def _calculate_interaction_statistics(self):
        """Calculate interaction statistics for adaptive weighting"""
        if self.user_item_matrix is None:
            return
            
        # Calculate user interaction counts
        self.user_interaction_counts = (self.user_item_matrix > 0).sum(axis=1)
        
        # Calculate item popularity
        self.item_popularity = (self.user_item_matrix > 0).sum(axis=0)
        
        # Calculate quantiles for user interactions
        self.interaction_quantiles = {
            'q10': self.user_interaction_counts.quantile(0.1),
            'q25': self.user_interaction_counts.quantile(0.25),
            'q50': self.user_interaction_counts.quantile(0.5),
            'q75': self.user_interaction_counts.quantile(0.75),
            'q90': self.user_interaction_counts.quantile(0.9)
        }
        
        # Calculate quantiles for item popularity
        self.popularity_quantiles = {
            'q10': self.item_popularity.quantile(0.1),
            'q25': self.item_popularity.quantile(0.25),
            'q50': self.item_popularity.quantile(0.5),
            'q75': self.item_popularity.quantile(0.75),
            'q90': self.item_popularity.quantile(0.9),
            'q95': self.item_popularity.quantile(0.95),
            'q99': self.item_popularity.quantile(0.99)
        }
        
        logger.info(f"Calculated interaction statistics: median user interactions={self.interaction_quantiles['q50']:.1f}, "
                   f"median item popularity={self.popularity_quantiles['q50']:.1f}")
    
    def preprocess_categories(self):
        if self.projects_df is None or 'primary_category' not in self.projects_df.columns:
            return
            
        # Check sample untuk mendeteksi format kategori
        sample_value = self.projects_df['primary_category'].iloc[0] if len(self.projects_df) > 0 else None
        
        # Buat kolom kategori yang distandarisasi
        self.projects_df['categories_list'] = self.projects_df['primary_category'].apply(self.process_categories)
        
        # Hitung distribusi kategori untuk diversitas recommendation
        self.category_distribution = {}
        for categories in self.projects_df['categories_list']:
            for category in categories:
                if category in self.category_distribution:
                    self.category_distribution[category] += 1
                else:
                    self.category_distribution[category] = 1
        
        # Normalisasi distribusi
        total_categories = sum(self.category_distribution.values())
        self.category_distribution = {k: v/total_categories for k, v in self.category_distribution.items()}
        
        logger.info(f"Preprocessed categories for {len(self.projects_df)} projects")
    
    def process_categories(self, category_value):
        """Proses kategori baik itu string tunggal maupun list"""
        if isinstance(category_value, list):
            return category_value  # Kembalikan list kategori
        elif isinstance(category_value, str):
            # Periksa apakah string dalam format list
            if category_value.startswith('[') and category_value.endswith(']'):
                try:
                    # Coba parse JSON jika format valid
                    parsed = json.loads(category_value)
                    if isinstance(parsed, list):
                        return parsed
                    return [category_value]
                except:
                    # Fallback ke parsing manual jika JSON parse gagal
                    if ',' in category_value:
                        # Strip kurung dan whitespace, split by comma
                        cleaned = category_value.strip('[]" ')
                        return [cat.strip(' "\'') for cat in cleaned.split(',')]
                    return [category_value]
            return [category_value]  # Wrap string tunggal dalam list
        return ['unknown']  # Default fallback
        
    def train(self, fecf_params: Optional[Dict[str, Any]] = None, ncf_params: Optional[Dict[str, Any]] = None, save_model: bool = True) -> Dict[str, Any]:
        metrics = {}
        
        # Train FECF
        logger.info("Training Feature-Enhanced CF component")
        fecf_metrics = self.fecf_model.train(save_model=save_model)
        metrics['fecf'] = fecf_metrics
        
        # Train NCF
        logger.info("Training Neural CF component with optimized parameters")
        
        # Update NCF params dengan nilai yang lebih baik untuk domain cryptocurrency
        optimized_ncf_params = {
            "val_ratio": 0.15,              # Porsi validasi tetap
            "batch_size": 256,              # Batch size tetap
            "num_epochs": 30,               # Lebih banyak epochs
            "learning_rate": 0.0003         # Optimized learning rate
        }
        
        # Gabungkan dengan ncf_params yang disediakan
        if ncf_params:
            optimized_ncf_params.update(ncf_params)
            
        ncf_metrics = self.ncf_model.train(save_model=save_model, **optimized_ncf_params)
        metrics['ncf'] = ncf_metrics
        
        # Capture performance for adaptive ensemble
        if 'validation_metrics' in fecf_metrics:
            self.model_performance['fecf'] = fecf_metrics['validation_metrics']
        if 'validation_metrics' in ncf_metrics:
            self.model_performance['ncf'] = ncf_metrics['validation_metrics']
        
        # Save hybrid model weights if requested
        if save_model:
            self.save_model()
        
        # Create ensemble performance estimate
        base_fecf_weight = self.params.get('fecf_weight', 0.5)
        base_ncf_weight = self.params.get('ncf_weight', 0.5)
        
        # Estimate combined performance
        ensemble_metrics = {}
        for metric in ['precision', 'recall', 'ndcg', 'hit_ratio']:
            fecf_value = self.model_performance['fecf'].get(metric, 0)
            ncf_value = self.model_performance['ncf'].get(metric, 0)
            ensemble_metrics[metric] = (fecf_value * base_fecf_weight + ncf_value * base_ncf_weight)
        
        metrics['ensemble'] = ensemble_metrics
        metrics['base_weights'] = {'fecf': base_fecf_weight, 'ncf': base_ncf_weight}
        
        return metrics
    
    def save_model(self, filepath: Optional[str] = None) -> str:
        if filepath is None:
            # Create default path
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filepath = os.path.join(MODELS_DIR, f"hybrid_model_{timestamp}.pkl")
            
        # Create directory if it doesn't exist
        os.makedirs(os.path.dirname(filepath), exist_ok=True)
        
        # Get paths to component models
        fecf_path = None
        ncf_path = None
        
        # Find latest FECF model
        fecf_models = [f for f in os.listdir(MODELS_DIR) if f.startswith("fecf_model_") and f.endswith(".pkl")]
        if fecf_models:
            fecf_path = os.path.join(MODELS_DIR, sorted(fecf_models)[-1])  # Get the latest
            
        # Find NCF model
        ncf_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
        if not os.path.exists(ncf_path):
            ncf_models = [f for f in os.listdir(MODELS_DIR) if f.startswith("ncf_model_") and f.endswith(".pkl")]
            if ncf_models:
                ncf_path = os.path.join(MODELS_DIR, sorted(ncf_models)[-1])  # Get the latest
        
        # Save model state with references to component models
        model_state = {
            'params': self.params,
            'fecf_path': fecf_path,
            'ncf_path': ncf_path,
            'model_performance': self.model_performance,
            'timestamp': datetime.now().isoformat()
        }
        
        with open(filepath, 'wb') as f:
            pickle.dump(model_state, f)
            
        logger.info(f"Enhanced Hybrid model saved to {filepath}")
        logger.info(f"  - FECF reference: {fecf_path}")
        logger.info(f"  - NCF reference: {ncf_path}")
        
        return filepath
    
    def load_model(self, 
              hybrid_filepath: Optional[str] = None,
              fecf_filepath: Optional[str] = None, 
              ncf_filepath: Optional[str] = None) -> bool:
        # Load hybrid weights if provided
        if hybrid_filepath and os.path.exists(hybrid_filepath):
            try:
                logger.info(f"Loading enhanced hybrid model from {hybrid_filepath}")
                with open(hybrid_filepath, 'rb') as f:
                    model_state = pickle.load(f)
                    
                self.params = model_state.get('params', self.params)
                self.model_performance = model_state.get('model_performance', self.model_performance)
                logger.info(f"Enhanced Hybrid model weights loaded from {hybrid_filepath}")
                
                # Get component model paths from hybrid model if not provided
                if fecf_filepath is None:
                    fecf_filepath = model_state.get('fecf_path')
                    logger.info(f"Using FECF path from hybrid model: {fecf_filepath}")
                    
                if ncf_filepath is None:
                    ncf_filepath = model_state.get('ncf_path')
                    logger.info(f"Using NCF path from hybrid model: {ncf_filepath}")
                    
            except Exception as e:
                logger.error(f"Error loading hybrid model: {str(e)}")
                return False
        
        # Initialize component models if needed
        if self.fecf_model is None:
            try:
                from src.models.alt_fecf import FeatureEnhancedCF
                self.fecf_model = FeatureEnhancedCF()
            except ImportError:
                logger.error("Could not import FeatureEnhancedCF module")
                return False
            
        if self.ncf_model is None:
            try:
                from src.models.ncf import NCFRecommender
                self.ncf_model = NCFRecommender()
            except ImportError:
                logger.error("Could not import NCFRecommender module")
                return False
        
        # Load data for both models if not already loaded
        if self.projects_df is None:
            data_loaded = self.load_data()
            if not data_loaded:
                logger.error("Failed to load data during model loading")
                return False
        
        # Load component models
        fecf_success = True
        if fecf_filepath and os.path.exists(fecf_filepath):
            fecf_success = self.fecf_model.load_model(fecf_filepath)
            if fecf_success:
                logger.info(f"FECF component loaded from {fecf_filepath}")
            else:
                logger.error(f"Failed to load FECF component from {fecf_filepath}")
        
        ncf_success = True
        if ncf_filepath and os.path.exists(ncf_filepath):
            ncf_success = self.ncf_model.load_model(ncf_filepath)
            if ncf_success:
                logger.info(f"NCF component loaded from {ncf_filepath}")
            else:
                logger.error(f"Failed to load NCF component from {ncf_filepath}")
        
        # Preprocess categories
        self.preprocess_categories()
        
        # Calculate interaction statistics
        self._calculate_interaction_statistics()
        
        # Model dianggap sukses jika minimal salah satu komponen berhasil dimuat
        return fecf_success or ncf_success
    
    def update_model_performance(self, eval_results: Dict[str, Any]):
        """Update performance metrics dari hasil evaluasi"""
        # Update FECF performance
        if 'fecf' in eval_results:
            self.model_performance['fecf'] = {
                'precision': eval_results['fecf'].get('precision', 0),
                'recall': eval_results['fecf'].get('recall', 0),
                'ndcg': eval_results['fecf'].get('ndcg', 0),
                'hit_ratio': eval_results['fecf'].get('hit_ratio', 0)
            }
        
        # Update NCF performance
        if 'ncf' in eval_results:
            self.model_performance['ncf'] = {
                'precision': eval_results['ncf'].get('precision', 0),
                'recall': eval_results['ncf'].get('recall', 0),
                'ndcg': eval_results['ncf'].get('ndcg', 0),
                'hit_ratio': eval_results['ncf'].get('hit_ratio', 0)
            }
        
        # TAMBAHAN: Track Hybrid performance juga untuk monitoring
        if 'hybrid' in eval_results:
            self.model_performance['hybrid'] = {
                'precision': eval_results['hybrid'].get('precision', 0),
                'recall': eval_results['hybrid'].get('recall', 0),
                'ndcg': eval_results['hybrid'].get('ndcg', 0),
                'hit_ratio': eval_results['hybrid'].get('hit_ratio', 0)
            }
            
            # Calculate performance improvement over components
            if 'fecf' in self.model_performance and 'ncf' in self.model_performance:
                fecf_avg = np.mean([self.model_performance['fecf'][m] 
                                for m in ['precision', 'recall', 'ndcg', 'hit_ratio']])
                ncf_avg = np.mean([self.model_performance['ncf'][m] 
                                for m in ['precision', 'recall', 'ndcg', 'hit_ratio']])
                hybrid_avg = np.mean([self.model_performance['hybrid'][m] 
                                    for m in ['precision', 'recall', 'ndcg', 'hit_ratio']])
                
                best_component = max(fecf_avg, ncf_avg)
                improvement = ((hybrid_avg - best_component) / best_component) * 100
                
                logger.info(f"Hybrid performance vs best component: {improvement:+.1f}%")
        
        logger.info(f"Updated model performance metrics: {self.model_performance}")
        
    def is_trained(self) -> bool:
        # Hybrid model dianggap terlatih jika minimal satu komponen terlatih
        fecf_trained = (self.fecf_model is not None and 
                    hasattr(self.fecf_model, 'model') and 
                    self.fecf_model.model is not None)
        
        ncf_trained = (self.ncf_model is not None and 
                    hasattr(self.ncf_model, 'model') and 
                    self.ncf_model.model is not None)
        
        return fecf_trained or ncf_trained
    
    def normalize_scores(self, recommendations: List[Tuple[str, float]], 
                    method: Optional[str] = None) -> List[Tuple[str, float]]:
        if not recommendations:
            return []
            
        # Use specified method or default from params
        method = method or self.params.get('normalization', 'sigmoid')
        
        # Quick return if no normalization requested
        if method == 'none':
            return recommendations
            
        # Extract items and scores
        items = [item_id for item_id, _ in recommendations]
        scores = np.array([score for _, score in recommendations])
        
        # Cache key for this normalization
        items_key = '-'.join(items[:5]) + f"-{len(items)}"
        scores_key = f"{scores.mean():.4f}-{scores.std():.4f}-{len(scores)}"
        cache_key = f"{items_key}-{scores_key}-{method}"
        
        # Check cache first
        if cache_key in self._normalization_cache:
            return self._normalization_cache[cache_key]
            
        # Apply normalization method
        if method == 'linear':
            # Min-max scaling
            min_val = scores.min()
            max_val = scores.max()
            if max_val > min_val:
                normalized = (scores - min_val) / (max_val - min_val)
            else:
                normalized = np.ones_like(scores) * 0.5
                
        elif method == 'sigmoid':
            # Enhanced sigmoid normalization with better scaling
            mean = scores.mean()
            std = max(scores.std(), 1e-5)  # Avoid division by zero
            z_scores = (scores - mean) / std
            normalized = expit(z_scores * 1.5)  # Steeper sigmoid for better differentiation
            
        elif method == 'rank':
            # Rank-based normalization with exponential decay
            ranks = np.argsort(np.argsort(scores)[::-1]) + 1
            max_rank = len(ranks)
            # Exponential decay for pronounced ranking effect
            normalized = np.exp(-0.5 * ranks / max_rank)
            # Re-normalize to [0,1] range
            normalized = (normalized - normalized.min()) / (normalized.max() - normalized.min())
        else:
            # Fallback to original scores
            normalized = scores
            
        # Create normalized recommendations
        normalized_recs = list(zip(items, normalized))
        
        # Store in cache
        self._normalization_cache[cache_key] = normalized_recs
        
        return normalized_recs
    
    def get_effective_weights(self, user_id: str, context: Optional[Dict] = None) -> Tuple[float, float, float]:
        """
        Mendapatkan bobot model yang adaptif berdasarkan confidence dan user characteristics

        Logika Adaptive Weighting:
        Interactions < 10:          FECF 95%, NCF 5%   (cold start)
        Interactions 10-20:         FECF 80%, NCF 20%  (low interactions)
        Interactions 20-30:         FECF 80%→50%, NCF 20%→50% (gradual transition)
        Interactions 30-50:         FECF 50%, NCF 50%  (base weights)
        Interactions 50-100:        FECF 45%, NCF 55%  (NCF mulai unggul)
        Interactions > 100:         FECF 40%, NCF 60%  (NCF dominan)

        Penjelasan Penggunaan Thresholds:
        threshold_low (10): Di bawah ini, user dianggap cold-start, gunakan cold_start_fecf_weight
        min_ncf_interactions (20): Minimal interaksi untuk NCF mulai berkontribusi
        threshold_high (30): Di atas ini, NCF mulai lebih dipercaya
        confidence_threshold (0.4): Minimal performance score untuk NCF dianggap reliable
        """
        # Base weights dari config
        base_fecf_weight = self.params.get('fecf_weight', 0.5)
        base_ncf_weight = self.params.get('ncf_weight', 0.5)
        diversity_factor = self.params.get('diversity_factor', 0.3)
        
        # Get thresholds dari params
        threshold_low = self.params.get('interaction_threshold_low', 10)
        threshold_high = self.params.get('interaction_threshold_high', 30)
        min_ncf_interactions = self.params.get('min_ncf_interactions', 20)
        cold_start_fecf_weight = self.params.get('cold_start_fecf_weight', 0.95)
        
        # Check user interaction count
        user_interaction_count = 0
        if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
            user_interactions = self.user_item_matrix.loc[user_id]
            user_interaction_count = (user_interactions > 0).sum()
        
        # PERBAIKAN: Adaptive weighting berdasarkan interaction count menggunakan thresholds dari params
        if user_interaction_count < threshold_low:
            # Very few interactions - heavily favor FECF
            fecf_weight = cold_start_fecf_weight
            ncf_weight = 1.0 - cold_start_fecf_weight
        elif user_interaction_count < min_ncf_interactions:
            # Low interactions - still favor FECF but less extreme
            fecf_weight = 0.8
            ncf_weight = 0.2
        elif user_interaction_count < threshold_high:
            # Medium interactions - gradual transition
            # Linear interpolation between min_ncf_interactions dan threshold_high
            ratio = (user_interaction_count - min_ncf_interactions) / (threshold_high - min_ncf_interactions)
            fecf_weight = 0.8 - (0.3 * ratio)  # 0.8 -> 0.5
            ncf_weight = 0.2 + (0.3 * ratio)   # 0.2 -> 0.5
        else:
            # High interactions - NCF mulai lebih efektif
            if user_interaction_count > 100:
                # Very high interactions - NCF should perform better
                fecf_weight = 0.4
                ncf_weight = 0.6
            elif user_interaction_count > 50:
                # Moderate-high interactions
                fecf_weight = 0.45
                ncf_weight = 0.55
            else:
                # Use base weights from config
                fecf_weight = base_fecf_weight
                ncf_weight = base_ncf_weight
        
        # Check model confidence/performance
        if hasattr(self, 'model_performance'):
            fecf_perf = self.model_performance.get('fecf', {})
            ncf_perf = self.model_performance.get('ncf', {})
            
            # Calculate performance ratio
            fecf_score = np.mean([fecf_perf.get('precision', 0), fecf_perf.get('recall', 0)])
            ncf_score = np.mean([ncf_perf.get('precision', 0), ncf_perf.get('recall', 0)])
            
            # Get confidence threshold from params
            confidence_threshold = self.params.get('confidence_threshold', 0.4)
            
            if fecf_score > 0 and ncf_score > 0:
                # Jika NCF performance di bawah threshold, kurangi bobotnya
                if ncf_score < confidence_threshold:
                    logger.debug(f"NCF performance below threshold ({ncf_score:.3f} < {confidence_threshold}), reducing weight")
                    ncf_weight = max(0.1, ncf_weight * 0.5)  # Reduce NCF weight significantly
                    fecf_weight = 1.0 - ncf_weight
                # Jika FECF jauh lebih baik, increase weight-nya
                elif fecf_score > ncf_score * 1.5:
                    fecf_weight = min(0.9, fecf_weight * 1.2)
                    ncf_weight = 1.0 - fecf_weight
                elif ncf_score > fecf_score * 1.2:
                    # Rare case where NCF is better
                    ncf_weight = min(0.7, ncf_weight * 1.2)
                    fecf_weight = 1.0 - ncf_weight
        
        # Normalize weights
        total = fecf_weight + ncf_weight
        fecf_weight /= total
        ncf_weight /= total
        
        logger.debug(f"Adaptive weights for user {user_id} (interactions={user_interaction_count}): "
                    f"FECF={fecf_weight:.3f}, NCF={ncf_weight:.3f}")
        
        return fecf_weight, ncf_weight, diversity_factor
    
    def _extract_user_context(self, user_id: str) -> Dict:
        """Ekstrak faktor kontekstual pengguna"""
        context = {
            'interaction_count': 0,
            'recency': 'new',      # new, regular, established
            'activity_pattern': 'unknown',  # casual, regular, power
            'category_focus': 0.5,  # 0 (broad) to 1 (narrow)
            'chain_diversity': 0.5, # 0 (single chain) to 1 (multi-chain)
            'exploration_rate': 0.3 # 0 (conservative) to 1 (explorer)
        }
        
        # Menghitung jumlah interaksi
        if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
            user_interactions = self.user_item_matrix.loc[user_id]
            positive_interactions = user_interactions[user_interactions > 0]
            context['interaction_count'] = len(positive_interactions)
            
            # Menentukan recency
            # Implementasi sebenarnya akan menggunakan data timestamp
            if context['interaction_count'] < 10:
                context['recency'] = 'new'
            elif context['interaction_count'] < 50:
                context['recency'] = 'regular'
            else:
                context['recency'] = 'established'
                
            # Menentukan pola aktivitas
            # Implementasi sebenarnya akan menggunakan analisis pola aktivitas yang lebih kompleks
            if context['interaction_count'] < 15:
                context['activity_pattern'] = 'casual'
            elif context['interaction_count'] < 75:
                context['activity_pattern'] = 'regular'
            else:
                context['activity_pattern'] = 'power'
                
            # Menghitung kategori fokus
            if 'primary_category' in self.projects_df.columns:
                interacted_items = positive_interactions.index
                interacted_categories = []
                
                for item in interacted_items:
                    item_data = self.projects_df[self.projects_df['id'] == item]
                    if not item_data.empty and 'primary_category' in item_data:
                        category = item_data.iloc[0]['primary_category']
                        interacted_categories.append(category)
                
                # Hitung rasio kategori unik terhadap total interaksi
                if interacted_categories:
                    unique_categories = len(set(interacted_categories))
                    context['category_focus'] = 1 - (unique_categories / len(interacted_categories))
                    
            # Menghitung diversitas chain dengan cara serupa
            if 'chain' in self.projects_df.columns:
                interacted_items = positive_interactions.index
                interacted_chains = []
                
                for item in interacted_items:
                    item_data = self.projects_df[self.projects_df['id'] == item]
                    if not item_data.empty and 'chain' in item_data:
                        chain = item_data.iloc[0]['chain']
                        interacted_chains.append(chain)
                
                # Hitung rasio chain unik terhadap total interaksi
                if interacted_chains:
                    unique_chains = len(set(interacted_chains))
                    context['chain_diversity'] = unique_chains / min(len(interacted_chains), 5)  # Normalisasi ke max 5 chain
                    
            # Estimasi exploration rate
            # Implementasi sebenarnya akan menganalisis pola eksplorasi dari waktu ke waktu
            if context['recency'] == 'new':
                context['exploration_rate'] = 0.6  # New users biasanya lebih suka eksplorasi
            else:
                context['exploration_rate'] = max(0.2, min(0.8, 
                                            0.5 * context['category_focus'] + 0.5 * context['chain_diversity']))
        
        return context
    
    def _extract_temporal_context(self) -> Dict:
        """Ekstrak konteks temporal (waktu, musim, tren pasar)"""
        # Dalam implementasi sebenarnya, ini bisa jauh lebih canggih
        context = {
            'time_of_day': 'regular', # morning, afternoon, evening, night
            'day_of_week': 'weekday', # weekday, weekend
            'market_trend': 'neutral' # bull, bear, neutral, volatile
        }
        
        # Menghitung time of day
        current_hour = datetime.now().hour
        if 5 <= current_hour < 12:
            context['time_of_day'] = 'morning'
        elif 12 <= current_hour < 17:
            context['time_of_day'] = 'afternoon'
        elif 17 <= current_hour < 22:
            context['time_of_day'] = 'evening'
        else:
            context['time_of_day'] = 'night'
            
        # Menghitung day of week
        weekday = datetime.now().weekday()
        context['day_of_week'] = 'weekday' if weekday < 5 else 'weekend'
        
        # Market trend - dalam implementasi sebenarnya akan menggunakan data pasar crypto real-time
        # Misalnya mengambil dari API eksternal
        # Di sini kita gunakan pendekatan sederhana berdasarkan rata-rata perubahan trend_score
        if self.projects_df is not None and 'trend_score' in self.projects_df.columns:
            avg_trend = self.projects_df['trend_score'].mean()
            if avg_trend > 60:
                context['market_trend'] = 'bull'
            elif avg_trend < 40:
                context['market_trend'] = 'bear'
            elif avg_trend.std() > 25:  # Volatilitas tinggi
                context['market_trend'] = 'volatile'
            else:
                context['market_trend'] = 'neutral'
        
        return context
    
    def _extract_domain_context(self, additional_context: Optional[Dict] = None) -> Dict:
        """Ekstrak faktor kontekstual domain cryptocurrency"""
        context = {
            'top_categories': [],
            'trending_chains': [],
            'market_volatility': 0.5,  # 0 (stabil) to 1 (sangat volatil)
            'innovation_rate': 0.5     # 0 (lambat) to 1 (cepat)
        }
        
        # Menghitung top categories
        if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
            # Top categories berdasarkan trend_score
            if 'trend_score' in self.projects_df.columns:
                trending_by_category = self.projects_df.groupby('primary_category')['trend_score'].mean()
                context['top_categories'] = trending_by_category.nlargest(3).index.tolist()
        
        # Menghitung trending chains
        if self.projects_df is not None and 'chain' in self.projects_df.columns:
            # Trending chains berdasarkan trend_score
            if 'trend_score' in self.projects_df.columns:
                trending_by_chain = self.projects_df.groupby('chain')['trend_score'].mean()
                context['trending_chains'] = trending_by_chain.nlargest(3).index.tolist()
        
        # Estimasi market volatility
        if self.projects_df is not None and 'trend_score' in self.projects_df.columns:
            trend_std = self.projects_df['trend_score'].std()
            # Normalize to 0-1 range assuming std range of 0-50
            context['market_volatility'] = min(1.0, trend_std / 50)
        
        # Merge dengan additional context jika ada
        if additional_context:
            context.update(additional_context)
        
        return context
    
    def _calculate_user_adjustments(self, user_context: Dict) -> Tuple[float, float]:
        """Hitung penyesuaian bobot berdasarkan konteks pengguna"""
        fecf_adj = 0.0
        ncf_adj = 0.0
        
        # 1. Adjustment berdasarkan jumlah interaksi
        interaction_count = user_context.get('interaction_count', 0)
        if interaction_count < 10:
            # Cold start - FECF lebih bagus
            fecf_adj += 0.15
            ncf_adj -= 0.15
        elif interaction_count > 50:
            # Banyak interaksi - NCF mulai unggul untuk personalisasi
            fecf_adj -= 0.1
            ncf_adj += 0.1
        
        # 2. Adjustment berdasarkan recency
        recency = user_context.get('recency', 'new')
        if recency == 'new':
            # Pengguna baru - FECF lebih bagus
            fecf_adj += 0.1
            ncf_adj -= 0.1
        elif recency == 'established':
            # Pengguna lama - NCF lebih tahu preferensi
            fecf_adj -= 0.05
            ncf_adj += 0.05
        
        # 3. Adjustment berdasarkan kategori fokus
        category_focus = user_context.get('category_focus', 0.5)
        if category_focus > 0.7:
            # Fokus sempit - NCF lebih bagus karena bisa menangkap preferensi spesifik
            fecf_adj -= 0.1
            ncf_adj += 0.1
        elif category_focus < 0.3:
            # Fokus luas - FECF lebih bagus karena bisa menangkap kesamaan berbasis fitur
            fecf_adj += 0.05
            ncf_adj -= 0.05
        
        # 4. Adjustment berdasarkan tingkat eksplorasi
        exploration_rate = user_context.get('exploration_rate', 0.3)
        if exploration_rate > 0.6:
            # Tingkat eksplorasi tinggi - FECF lebih bagus
            fecf_adj += 0.1
            ncf_adj -= 0.1
        elif exploration_rate < 0.3:
            # Tingkat eksplorasi rendah - NCF lebih bagus karena lebih tepat pada preferensi spesifik
            fecf_adj -= 0.05
            ncf_adj += 0.05
        
        # Cap adjustments
        fecf_adj = max(-0.2, min(0.2, fecf_adj))
        ncf_adj = max(-0.2, min(0.2, ncf_adj))
        
        return fecf_adj, ncf_adj
    
    def _calculate_temporal_adjustments(self, temporal_context: Dict) -> Tuple[float, float]:
        """Hitung penyesuaian bobot berdasarkan konteks temporal"""
        fecf_adj = 0.0
        ncf_adj = 0.0
        
        # 1. Market trend adjustment
        market_trend = temporal_context.get('market_trend', 'neutral')
        if market_trend == 'bull':
            # Bull market - NCF bisa lebih baik karena preferensi lebih stabil
            fecf_adj -= 0.05
            ncf_adj += 0.05
        elif market_trend == 'bear':
            # Bear market - FECF bisa lebih baik karena feature-based
            fecf_adj += 0.05
            ncf_adj -= 0.05
        elif market_trend == 'volatile':
            # Volatile market - FECF jauh lebih baik karena feature-based
            fecf_adj += 0.15
            ncf_adj -= 0.15
        
        # 2. Time of day adjustment (bisa ditingkatkan berdasarkan pola aktivitas cryptocurrency)
        time_of_day = temporal_context.get('time_of_day', 'regular')
        if time_of_day == 'night':
            # Malam hari - pengguna mungkin lebih exploratory
            fecf_adj += 0.05
            ncf_adj -= 0.05
        
        # Cap adjustments
        fecf_adj = max(-0.15, min(0.15, fecf_adj))
        ncf_adj = max(-0.15, min(0.15, ncf_adj))
        
        return fecf_adj, ncf_adj
    
    def _calculate_domain_adjustments(self, domain_context: Dict) -> Tuple[float, float]:
        """Hitung penyesuaian bobot berdasarkan konteks domain cryptocurrency"""
        fecf_adj = 0.0
        ncf_adj = 0.0
        
        # 1. Market volatility adjustment
        volatility = domain_context.get('market_volatility', 0.5)
        if volatility > 0.7:
            # Pasar sangat volatil - FECF lebih handal
            fecf_adj += 0.1
            ncf_adj -= 0.1
        elif volatility < 0.3:
            # Pasar stabil - kedua model bisa baik
            pass
        
        # 2. Innovation rate adjustment
        innovation_rate = domain_context.get('innovation_rate', 0.5)
        if innovation_rate > 0.7:
            # Banyak inovasi/coin baru - FECF lebih baik karena bisa mengatasi cold-start item
            fecf_adj += 0.1
            ncf_adj -= 0.1
        
        # Cap adjustments
        fecf_adj = max(-0.1, min(0.1, fecf_adj))
        ncf_adj = max(-0.1, min(0.1, ncf_adj))
        
        return fecf_adj, ncf_adj
    
    def _calculate_performance_adjustments(self) -> Tuple[float, float]:
        """Hitung penyesuaian bobot berdasarkan performa historis model"""
        fecf_adj = 0.0
        ncf_adj = 0.0
        
        # Get metrics from model performance records
        fecf_perf = self.model_performance.get('fecf', {})
        ncf_perf = self.model_performance.get('ncf', {})
        
        # Calculate average performance across multiple metrics
        metrics = ['precision', 'recall', 'ndcg', 'hit_ratio']
        
        fecf_avg = np.mean([fecf_perf.get(m, 0) for m in metrics])
        ncf_avg = np.mean([ncf_perf.get(m, 0) for m in metrics])
        
        # Only adjust if we have meaningful performance data
        if fecf_avg > 0 and ncf_avg > 0:
            # Compute performance ratio
            ratio = fecf_avg / (ncf_avg + 1e-10)  # Avoid division by zero
            
            # Map ratio to adjustments
            if ratio > 1.3:  # FECF substantially better
                fecf_adj = 0.1
                ncf_adj = -0.1
            elif 1.1 < ratio <= 1.3:  # FECF moderately better
                fecf_adj = 0.05
                ncf_adj = -0.05
            elif 0.9 <= ratio <= 1.1:  # Similar performance
                pass  # No adjustment
            elif 0.7 <= ratio < 0.9:  # NCF moderately better
                fecf_adj = -0.05
                ncf_adj = 0.05
            else:  # NCF substantially better
                fecf_adj = -0.1
                ncf_adj = 0.1
        
        return fecf_adj, ncf_adj
    
    def _adjust_diversity_factor(self, base_diversity: float, user_context: Dict) -> float:
        """Menyesuaikan faktor diversitas berdasarkan konteks pengguna"""
        diversity = base_diversity
        
        # Pengguna dengan tingkat eksplorasi tinggi mendapatkan diversitas lebih tinggi
        exploration_rate = user_context.get('exploration_rate', 0.3)
        if exploration_rate > 0.6:
            diversity += 0.1
        elif exploration_rate < 0.3:
            diversity -= 0.05
        
        # Pengguna baru mendapatkan diversitas lebih tinggi untuk discovery
        recency = user_context.get('recency', 'new')
        if recency == 'new':
            diversity += 0.1
        
        # Bounds check
        diversity = max(0.1, min(0.5, diversity))
        
        return diversity
    
    def get_weight_distribution(self) -> Dict[str, Any]:
        """Analyze weight distribution across all users"""
        if self.user_item_matrix is None:
            return {}
        
        weight_stats = {
            'user_counts': {},
            'weight_distributions': {
                'low_interaction': {'fecf': [], 'ncf': []},
                'medium_interaction': {'fecf': [], 'ncf': []},
                'high_interaction': {'fecf': [], 'ncf': []}
            }
        }
        
        for user_id in self.user_item_matrix.index[:100]:  # Sample 100 users
            user_interactions = self.user_item_matrix.loc[user_id]
            interaction_count = (user_interactions > 0).sum()
            
            fecf_w, ncf_w, _ = self.get_effective_weights(user_id)
            
            if interaction_count < 10:
                category = 'low_interaction'
            elif interaction_count < 50:
                category = 'medium_interaction'
            else:
                category = 'high_interaction'
                
            weight_stats['weight_distributions'][category]['fecf'].append(fecf_w)
            weight_stats['weight_distributions'][category]['ncf'].append(ncf_w)
        
        # Calculate averages
        for category in weight_stats['weight_distributions']:
            if weight_stats['weight_distributions'][category]['fecf']:
                weight_stats['weight_distributions'][category]['avg_fecf'] = np.mean(
                    weight_stats['weight_distributions'][category]['fecf']
                )
                weight_stats['weight_distributions'][category]['avg_ncf'] = np.mean(
                    weight_stats['weight_distributions'][category]['ncf']
                )
        
        return weight_stats
    
    def get_ensemble_recommendations(self, 
                               fecf_recs: List[Tuple[str, float]], 
                               ncf_recs: List[Tuple[str, float]],
                               fecf_weight: float = 0.5,
                               ncf_weight: float = 0.5,
                               user_id: Optional[str] = None,
                               context: Optional[Dict] = None,
                               ensemble_method: Optional[str] = None) -> List[Tuple[str, float]]:
        """
        Menggabungkan rekomendasi dengan selective ensemble strategy
        """
        if not fecf_recs and not ncf_recs:
            return []
            
        # Quick return if only one model's recommendations are available
        if not fecf_recs:
            return ncf_recs
        if not ncf_recs:
            return fecf_recs
            
        ensemble_method = ensemble_method or self.params.get('ensemble_method', 'selective')
        
        # PERBAIKAN: Implementasi selective ensemble
        if ensemble_method == 'selective':
            # Analyze score distributions
            fecf_scores = [score for _, score in fecf_recs]
            ncf_scores = [score for _, score in ncf_recs]
            
            # Calculate confidence metrics
            fecf_mean = np.mean(fecf_scores) if fecf_scores else 0
            fecf_std = np.std(fecf_scores) if len(fecf_scores) > 1 else 0
            ncf_mean = np.mean(ncf_scores) if ncf_scores else 0
            ncf_std = np.std(ncf_scores) if len(ncf_scores) > 1 else 0
            
            # NCF confidence check - jika scores terlalu rendah atau terlalu uniform, ignore NCF
            ncf_confidence_threshold = 0.4
            ncf_is_confident = ncf_mean > ncf_confidence_threshold and ncf_std > 0.1
            
            if not ncf_is_confident:
                logger.debug(f"NCF confidence low (mean={ncf_mean:.3f}, std={ncf_std:.3f}), using FECF only")
                # Just use FECF recommendations
                return fecf_recs[:len(fecf_recs)]
            
            # Create score dictionaries with better normalization
            fecf_dict = {}
            ncf_dict = {}
            
            # PERBAIKAN: Better score normalization
            # Use percentile-based normalization instead of min-max
            fecf_scores_array = np.array(fecf_scores)
            ncf_scores_array = np.array(ncf_scores)
            
            # Calculate percentiles for normalization
            fecf_p10 = np.percentile(fecf_scores_array, 10)
            fecf_p90 = np.percentile(fecf_scores_array, 90)
            ncf_p10 = np.percentile(ncf_scores_array, 10)
            ncf_p90 = np.percentile(ncf_scores_array, 90)
            
            # Normalize scores to [0, 1] using percentiles
            for item_id, score in fecf_recs:
                if fecf_p90 > fecf_p10:
                    normalized_score = (score - fecf_p10) / (fecf_p90 - fecf_p10)
                    normalized_score = np.clip(normalized_score, 0, 1)
                else:
                    normalized_score = 0.5
                fecf_dict[item_id] = normalized_score
                
            for item_id, score in ncf_recs:
                if ncf_p90 > ncf_p10:
                    normalized_score = (score - ncf_p10) / (ncf_p90 - ncf_p10)
                    normalized_score = np.clip(normalized_score, 0, 1)
                else:
                    normalized_score = 0.5
                ncf_dict[item_id] = normalized_score
            
            # Get all unique items
            all_items = set(fecf_dict.keys()) | set(ncf_dict.keys())
            
            # Combine with selective strategy
            results = {}
            
            for item in all_items:
                fecf_score = fecf_dict.get(item, 0)
                ncf_score = ncf_dict.get(item, 0)
                
                # PERBAIKAN: Selective combination based on agreement and confidence
                if item in fecf_dict and item in ncf_dict:
                    # Both models recommend
                    score_diff = abs(fecf_score - ncf_score)
                    
                    if score_diff < 0.2:  # Models agree
                        # Use weighted average with bonus for agreement
                        combined_score = (fecf_score * fecf_weight + ncf_score * ncf_weight) * 1.1
                    else:
                        # Models disagree - trust FECF more due to better performance
                        if fecf_score > ncf_score:
                            combined_score = fecf_score * 0.9 + ncf_score * 0.1
                        else:
                            # NCF ranks higher - be cautious
                            combined_score = fecf_score * 0.7 + ncf_score * 0.3
                            
                    results[item] = min(1.0, combined_score)
                    
                elif item in fecf_dict:
                    # Only FECF recommends - slight penalty
                    results[item] = fecf_score * 0.95
                    
                else:
                    # Only NCF recommends - larger penalty due to lower confidence
                    results[item] = ncf_score * 0.8
            
            # Convert to list and sort
            combined_recs = [(item, score) for item, score in results.items()]
            combined_recs.sort(key=lambda x: x[1], reverse=True)
            
            return combined_recs
        
        else:
            # Fallback to weighted average
            return self._weighted_average_ensemble(fecf_recs, ncf_recs, fecf_weight, ncf_weight)
    
    def _get_item_context(self, item_id: str) -> Dict:
        """Extract contextual information about an item"""
        context = {
            'category': 'unknown',
            'chain': 'unknown',
            'trend_score': 50,
            'market_cap_tier': 'medium',  # low, medium, high
            'age': 'unknown',  # new, established
            'popularity': 0.5   # 0 to 1
        }
        
        if self.projects_df is not None:
            item_data = self.projects_df[self.projects_df['id'] == item_id]
            
            if not item_data.empty:
                item = item_data.iloc[0]
                
                # Extract category
                if 'primary_category' in item:
                    context['category'] = item['primary_category']
                    
                # Extract chain
                if 'chain' in item:
                    context['chain'] = item['chain']
                    
                # Extract trend score
                if 'trend_score' in item:
                    context['trend_score'] = item['trend_score']
                    
                # Determine market cap tier
                if 'market_cap' in item:
                    market_cap = item['market_cap']
                    if market_cap > 1e9:  # $1B+
                        context['market_cap_tier'] = 'high'
                    elif market_cap > 1e8:  # $100M+
                        context['market_cap_tier'] = 'medium'
                    else:
                        context['market_cap_tier'] = 'low'
                        
                # Determine age
                if 'genesis_date' in item:
                    try:
                        genesis = pd.to_datetime(item['genesis_date'])
                        if (datetime.now() - genesis).days < 180:  # Less than 6 months
                            context['age'] = 'new'
                        else:
                            context['age'] = 'established'
                    except:
                        pass
                        
                # Extract popularity
                if 'popularity_score' in item:
                    context['popularity'] = item['popularity_score'] / 100
        
        return context
    
    def _compute_item_weights(self, item_context: Dict, user_context: Dict, temporal_context: Dict, domain_context: Dict, base_fecf: float = 0.5, base_ncf: float = 0.5) -> Tuple[float, float]:
        """
        Compute optimal model weights for a specific item based on multiple contexts
        """
        fecf_weight = base_fecf
        ncf_weight = base_ncf
        
        # 1. Item-specific adjustments
        # Age-based adjustment
        if item_context['age'] == 'new':
            # New items - FECF is better
            fecf_weight += 0.05
            ncf_weight -= 0.05
        
        # Market cap adjustment
        if item_context['market_cap_tier'] == 'low':
            # Low market cap (long tail) - FECF is better
            fecf_weight += 0.05
            ncf_weight -= 0.05
        elif item_context['market_cap_tier'] == 'high':
            # High market cap (popular) - Both models work well
            pass
        
        # Trend based adjustment
        if item_context['trend_score'] > 70:
            # Highly trending - increase NCF slightly 
            # (trend is often captured by collaborative effects)
            fecf_weight -= 0.03
            ncf_weight += 0.03
        
        # 2. User-Item interaction patterns
        # User's category focus x item category
        if user_context.get('category_focus', 0.5) > 0.7 and item_context['category'] in user_context.get('preferred_categories', []):
            # User focuses on this category - NCF better at capturing this
            fecf_weight -= 0.05
            ncf_weight += 0.05
            
        # 3. Market condition adjustments
        # Volatility x item type
        if domain_context.get('market_volatility', 0.5) > 0.7:
            if item_context['market_cap_tier'] == 'low':
                # Volatile market + small caps = FECF much better
                fecf_weight += 0.1
                ncf_weight -= 0.1
        
        # Ensure weights are in valid range
        fecf_weight = max(0.4, min(0.8, fecf_weight))
        ncf_weight = 1.0 - fecf_weight
        
        return fecf_weight, ncf_weight

    def _apply_trending_boost(self, results: Dict[str, float], boost_factor: Optional[float] = None) -> None:
        """Apply trending boost to results with configurable boost factor"""
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            # Use provided boost_factor or get from params
            trend_boost_factor = boost_factor if boost_factor is not None else self.params.get('trending_boost_factor', 0.2)
            
            if trend_boost_factor > 0:
                # Create item to trend lookup
                item_to_trend = dict(zip(self.projects_df['id'], self.projects_df['trend_score']))
                
                # Apply trend boosting
                for item in list(results.keys()):
                    if item in item_to_trend:
                        trend_score = item_to_trend[item]
                        
                        # Normalize trend score to [0, 1]
                        norm_trend = min(1.0, max(0.0, trend_score / 100.0))
                        
                        # Apply boost only for highly trending items
                        if norm_trend > 0.6:  # More than 60/100 trending score
                            boost = (norm_trend - 0.6) * trend_boost_factor
                            results[item] = min(1.0, results[item] + boost)
    
    def apply_diversity(self, recommendations: List[Tuple[str, float]], 
                    n: int, diversity_weight: float = 0.25) -> List[Tuple[str, float]]:
        if not recommendations or len(recommendations) <= n:
            return recommendations
            
        # Prepare item metadata
        item_categories = {}
        item_chains = {}
        item_market_caps = {}
        
        if self.projects_df is not None:
            # Extract item metadata
            for _, row in self.projects_df.iterrows():
                if 'id' not in row:
                    continue
                    
                item_id = row['id']
                
                # Extract multiple categories if available
                if 'categories_list' in row:
                    item_categories[item_id] = row['categories_list']
                elif 'primary_category' in row:
                    item_categories[item_id] = self.process_categories(row['primary_category'])
                
                # Extract chain information
                if 'chain' in row:
                    item_chains[item_id] = row['chain']
                    
                # Extract market cap for balance between new/established projects
                if 'market_cap' in row:
                    item_market_caps[item_id] = row['market_cap']
        
        # If no category/chain data available, just return top-n
        if not item_categories and not item_chains:
            return recommendations[:n]
            
        # Select top items without diversity first (guaranteed selection)
        top_count = max(n // 4, 1)  # ~25% by pure score
        result = recommendations[:top_count]
        
        # Track selected categories and chains
        selected_categories = {}
        selected_chains = {}
        selected_market_cap_tiers = {'high': 0, 'medium': 0, 'low': 0, 'unknown': 0}
        
        # Define market cap thresholds (can be derived from data)
        if item_market_caps:
            market_caps = list(item_market_caps.values())
            market_caps.sort()
            if market_caps:
                market_cap_high = market_caps[int(len(market_caps) * 0.9)]  # Top 10%
                market_cap_medium = market_caps[int(len(market_caps) * 0.5)]  # Medium 40%
            else:
                market_cap_high = 1e9
                market_cap_medium = 1e8
        else:
            market_cap_high = 1e9
            market_cap_medium = 1e8
        
        # Populate initial tracking
        for item_id, _ in result:
            # Track categories
            if item_id in item_categories:
                for category in item_categories[item_id]:
                    selected_categories[category] = selected_categories.get(category, 0) + 1
            
            # Track chains
            if item_id in item_chains:
                chain = item_chains[item_id]
                selected_chains[chain] = selected_chains.get(chain, 0) + 1
                
            # Track market cap tiers
            if item_id in item_market_caps:
                market_cap = item_market_caps[item_id]
                if market_cap >= market_cap_high:
                    selected_market_cap_tiers['high'] += 1
                elif market_cap >= market_cap_medium:
                    selected_market_cap_tiers['medium'] += 1
                else:
                    selected_market_cap_tiers['low'] += 1
            else:
                selected_market_cap_tiers['unknown'] += 1
        
        # Calculate diversity limits with more nuanced approach
        # Adjust based on total recommendations requested
        max_per_category = max(2, int(n * 0.25))  # Maximum ~25% per category
        max_per_chain = max(3, int(n * 0.33))     # Maximum ~33% per chain
        
        # Market cap tier targets (percentages of total)
        # Aim for diverse mix of established/mid/new projects
        market_cap_targets = {
            'high': int(n * 0.3),    # 30% high cap
            'medium': int(n * 0.4),  # 40% medium cap
            'low': int(n * 0.3),     # 30% low cap
            'unknown': int(n * 0.1)  # Allow 10% unknown
        }
        
        # Process remaining candidates
        remaining = recommendations[top_count:]
        
        # Calculate diversity adjusted scores for all remaining items
        diversity_adjusted = []
        
        for item_id, score in remaining:
            category_adjustment = 0
            chain_adjustment = 0
            market_cap_adjustment = 0
            
            # Category diversity adjustment with underrepresented boost
            if item_id in item_categories:
                # Periksa semua kategori item
                cat_adjustments = []
                item_cats = item_categories[item_id]
                
                for category in item_cats:
                    cat_count = selected_categories.get(category, 0)
                    
                    if cat_count >= max_per_category:
                        # Heavy penalty for overrepresented category
                        cat_adjustments.append(-0.5)
                    elif cat_count == 0:
                        # Strong boost for new categories - especially underrepresented ones
                        category_freq = self.category_distribution.get(category, 0.05)
                        rarity_boost = 0.2 + (0.2 * (1 - min(1.0, category_freq * 20)))
                        cat_adjustments.append(rarity_boost)
                    else:
                        # Smaller adjustment based on count
                        adjustment = 0.15 * (1 - cat_count / max_per_category)
                        cat_adjustments.append(adjustment)
                
                # Use mean of top adjustments for better balance
                if cat_adjustments:
                    cat_adjustments.sort(reverse=True)  # Sort by highest boost first
                    top_n_adjustments = cat_adjustments[:min(2, len(cat_adjustments))]  # Use top 2 adjustments
                    category_adjustment = sum(top_n_adjustments) / len(top_n_adjustments)
            
            # Chain diversity adjustment
            if item_id in item_chains:
                chain = item_chains[item_id]
                chain_count = selected_chains.get(chain, 0)
                
                if chain_count >= max_per_chain:
                    chain_adjustment = -0.4  # Stronger penalty
                elif chain_count == 0:
                    chain_adjustment = 0.25  # Stronger bonus
                else:
                    chain_adjustment = 0.1 * (1 - chain_count / max_per_chain)
            
            # Market cap diversity adjustment
            if item_id in item_market_caps:
                market_cap = item_market_caps[item_id]
                tier = 'high' if market_cap >= market_cap_high else 'medium' if market_cap >= market_cap_medium else 'low'
                
                # Calculate how full this tier is compared to target
                tier_count = selected_market_cap_tiers[tier]
                tier_target = market_cap_targets[tier]
                
                if tier_count >= tier_target:
                    # Tier is full or overrepresented
                    fullness_ratio = tier_count / tier_target
                    market_cap_adjustment = -0.2 * fullness_ratio
                elif tier_count < tier_target * 0.5:
                    # Tier is significantly underrepresented
                    market_cap_adjustment = 0.15
                else:
                    # Small positive adjustment
                    market_cap_adjustment = 0.05
            
            # Apply diversity weight
            # Adaptive weights for more nuanced diversity control
            category_weight = 0.6  # Categories are most important for crypto
            chain_weight = 0.25    # Chains are secondary
            market_cap_weight = 0.15  # Market cap is tertiary
            
            # Calculate weighted diversity adjustment
            diversity_score = (
                category_adjustment * category_weight + 
                chain_adjustment * chain_weight + 
                market_cap_adjustment * market_cap_weight
            ) * diversity_weight
            
            adjusted_score = score + diversity_score
            
            # Store original item, score, and adjusted score
            diversity_adjusted.append((item_id, score, adjusted_score))
        
        # Sort by adjusted score
        diversity_adjusted.sort(key=lambda x: x[2], reverse=True)
        
        # Select remaining items with greater diversity consciousness
        for item_id, original_score, _ in diversity_adjusted:
            if len(result) >= n:
                break
                
            # Update category and chain tracking
            if item_id in item_categories:
                for category in item_categories[item_id]:
                    selected_categories[category] = selected_categories.get(category, 0) + 1
            
            if item_id in item_chains:
                chain = item_chains[item_id]
                selected_chains[chain] = selected_chains.get(chain, 0) + 1
                
            # Update market cap tracking
            if item_id in item_market_caps:
                market_cap = item_market_caps[item_id]
                if market_cap >= market_cap_high:
                    selected_market_cap_tiers['high'] += 1
                elif market_cap >= market_cap_medium:
                    selected_market_cap_tiers['medium'] += 1
                else:
                    selected_market_cap_tiers['low'] += 1
            else:
                selected_market_cap_tiers['unknown'] += 1
            
            # Add to result with original score
            result.append((item_id, original_score))
        
        return result
    
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True) -> List[Tuple[str, float]]:
        # OPTIMIZATION: Check cache first
        cache_key = f"{user_id}_{n}_{exclude_known}"
        if hasattr(self, '_recommendation_cache') and cache_key in self._recommendation_cache:
            cache_entry = self._recommendation_cache[cache_key]
            cache_time = cache_entry.get('time', 0)
            # Use cache if it's less than 15 minutes old
            if time.time() - cache_time < 900:  # 15 minutes in seconds
                return cache_entry['recommendations']
        
        # Check if this is a cold-start user
        is_cold_start = True
        if self.user_item_matrix is not None:
            is_cold_start = user_id not in self.user_item_matrix.index
            
        # Handle cold-start case
        if is_cold_start:
            return self._get_cold_start_recommendations(user_id, n)
            
        # Get effective weights
        fecf_weight, ncf_weight, diversity_weight = self.get_effective_weights(user_id)

        # Log weights yang digunakan untuk debugging
        logger.debug(f"Using weights for user {user_id}: FECF={fecf_weight:.3f}, NCF={ncf_weight:.3f}")
        
        # Can't do anything if neither model is available
        if fecf_weight == 0 and ncf_weight == 0:
            logger.error("No models available for recommendations")
            return []
            
        # Determine number of candidates to get from each model
        n_candidates = min(n * self.params.get('n_candidates_factor', 3), 150)
        
        # Get FECF recommendations if available
        fecf_recs = []
        if fecf_weight > 0:
            try:
                start_time = time.time()
                fecf_recs = self.fecf_model.recommend_for_user(
                    user_id, 
                    n=n_candidates,
                    exclude_known=exclude_known
                )
                logger.debug(f"FECF recommendations for {user_id} took {time.time() - start_time:.3f}s")
            except Exception as e:
                logger.warning(f"Error getting FECF recommendations: {e}")
                # Adjust weights if FECF fails
                if ncf_weight > 0:
                    ncf_weight = 1.0
                    fecf_weight = 0.0
                else:
                    # Both models failed, fallback to cold-start
                    return self._get_cold_start_recommendations(user_id, n)
        
        # Get NCF recommendations if available
        ncf_recs = []
        if ncf_weight > 0:
            try:
                start_time = time.time()
                ncf_recs = self.ncf_model.recommend_for_user(
                    user_id, 
                    n=n_candidates,
                    exclude_known=exclude_known
                )
                logger.debug(f"NCF recommendations for {user_id} took {time.time() - start_time:.3f}s")
            except Exception as e:
                logger.warning(f"Error getting NCF recommendations: {e}")
                # Adjust weights if NCF fails
                if fecf_weight > 0:
                    fecf_weight = 1.0
                    ncf_weight = 0.0
                else:
                    # Both models failed, fallback to cold-start
                    return self._get_cold_start_recommendations(user_id, n)
        
        # Use enhanced ensemble to combine recommendations
        start_time = time.time()
        combined_recs = self.get_ensemble_recommendations(
            fecf_recs=fecf_recs,
            ncf_recs=ncf_recs,
            fecf_weight=fecf_weight,  # Ini akan override default parameters
            ncf_weight=ncf_weight,    # Ini akan override default parameters
            user_id=user_id,          # Pass user_id untuk context
            ensemble_method=self.params.get('ensemble_method', 'selective')
        )
        logger.debug(f"Ensemble recommendations for {user_id} took {time.time() - start_time:.3f}s")
        
        # Apply diversity
        start_time = time.time()
        diversified_recs = self.apply_diversity(
            combined_recs, 
            n=n, 
            diversity_weight=diversity_weight
        )
        logger.debug(f"Diversity application for {user_id} took {time.time() - start_time:.3f}s")
        
        # Store in cache
        if not hasattr(self, '_recommendation_cache'):
            self._recommendation_cache = {}
            
        self._recommendation_cache[cache_key] = {
            'recommendations': diversified_recs[:n],
            'time': time.time()
        }
        
        # Track sources for analytics
        fecf_items = {item_id for item_id, _ in fecf_recs}
        ncf_items = {item_id for item_id, _ in ncf_recs}
        
        for item_id, _ in diversified_recs[:n]:
            sources = []
            if item_id in fecf_items:
                sources.append('fecf')
            if item_id in ncf_items:
                sources.append('ncf')
                
            if not sources:
                sources = ['unknown']
                
            self.recommendation_sources[item_id] = sources
        
        return diversified_recs[:n]
    
    def _get_cold_start_recommendations(self, user_id: str, n: int = 10) -> List[Tuple[str, float]]:
        logger.info(f"Generating cold-start recommendations for user {user_id}")
        
        # Use optimized weights for cold-start with FECF heavily favored
        fecf_weight = self.params.get('cold_start_fecf_weight', 0.95)
        ncf_weight = 1.0 - fecf_weight
        
        # Get FECF cold-start recommendations
        fecf_recs = []
        if fecf_weight > 0 and self.fecf_model is not None:
            try:
                start_time = time.time()
                fecf_cold_start = self.fecf_model.get_cold_start_recommendations(n=n*3)
                fecf_recs = [(rec['id'], rec['recommendation_score']) 
                           for rec in fecf_cold_start if 'id' in rec]
                logger.debug(f"FECF cold-start recommendations took {time.time() - start_time:.3f}s")
            except Exception as e:
                logger.warning(f"Error getting FECF cold-start recommendations: {e}")
                
        # Get NCF cold-start recommendations (popularity-based)
        ncf_recs = []
        if ncf_weight > 0 and self.ncf_model is not None:
            try:
                start_time = time.time()
                ncf_cold_start = self.ncf_model.get_popular_projects(n=n*3)
                ncf_recs = [(rec['id'], rec['recommendation_score']) 
                          for rec in ncf_cold_start if 'id' in rec]
                logger.debug(f"NCF cold-start recommendations took {time.time() - start_time:.3f}s")
            except Exception as e:
                logger.warning(f"Error getting NCF cold-start recommendations: {e}")
                
        # If both models failed, get trending projects as a fallback
        if not fecf_recs and not ncf_recs:
            logger.warning("Both models failed for cold-start, using trending/popular fallback")
            
            trending_projects = []
            if hasattr(self, 'projects_df'):
                if 'trend_score' in self.projects_df.columns:
                    # Get highly trending projects
                    trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*3)
                    trending_projects = [(row['id'], row['trend_score']/100) 
                                      for _, row in trending.iterrows()]
                    
                    # Mix with some popular by market cap
                    if 'market_cap' in self.projects_df.columns and len(trending_projects) < n*3:
                        popular = self.projects_df.sort_values('market_cap', ascending=False).head(n*3)
                        # Convert market_cap to normalized score
                        max_market_cap = popular['market_cap'].max()
                        if max_market_cap > 0:
                            popular_projects = [(row['id'], row['market_cap']/max_market_cap * 0.8) 
                                             for _, row in popular.iterrows() 
                                             if row['id'] not in [p[0] for p in trending_projects]]
                            trending_projects.extend(popular_projects)
                elif 'popularity_score' in self.projects_df.columns:
                    # Fallback to popularity
                    popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n*3)
                    trending_projects = [(row['id'], row['popularity_score']/100) 
                                      for _, row in popular.iterrows()]
                else:
                    # Last resort: random projects
                    trending_projects = [(row['id'], 0.5) 
                                      for _, row in self.projects_df.sample(min(n*3, len(self.projects_df))).iterrows()]
            
            return trending_projects[:n]
                
        # Get category distribution (if available) for diversity
        category_distribution = {}
        if hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            for _, row in self.projects_df.iterrows():
                category = row['primary_category']
                if category in category_distribution:
                    category_distribution[category] += 1
                else:
                    category_distribution[category] = 1
            
            # Normalize
            total = sum(category_distribution.values())
            if total > 0:
                category_distribution = {k: v/total for k, v in category_distribution.items()}
        
        # Use sophisticated ensemble with heavy weight on FECF for cold-start
        combined_recs = self.get_ensemble_recommendations(
            fecf_recs=fecf_recs,
            ncf_recs=ncf_recs,
            fecf_weight=fecf_weight,
            ncf_weight=ncf_weight,
            ensemble_method='stacking'  # Simpler method for cold-start
        )
        
        # Also get purely trending projects as a source of diversity
        trending_recs = []
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*2)
            trending_recs = [(row['id'], row['trend_score']/100 * 0.9)  # Scale trend score and apply minor discount
                           for _, row in trending.iterrows()]
        
        # Balance model-based and trending-based recommendations for cold-start
        # More algorithmic approach to ratio
        model_ratio = 0.7  # Start with 70% model-based
        trend_ratio = 0.3  # And 30% trending
        
        # Adjust ratios based on data quality (we can check here if needed)
        # Just using static values for now, but could be dynamic
        
        # Calculate counts with minimum guarantees
        model_count = max(int(n * model_ratio), n // 2)
        trend_count = max(n - model_count, n // 5)
        
        # Rebalance if needed
        if model_count + trend_count > n:
            excess = model_count + trend_count - n
            if model_count > trend_count:
                model_count -= excess
            else:
                trend_count -= excess
        
        # Ensure we're using the best of each source
        selected_model_recs = combined_recs[:model_count]
        
        # Filter trending to avoid duplicates with model recs
        model_items = {item_id for item_id, _ in selected_model_recs}
        filtered_trending = [(item_id, score) for item_id, score in trending_recs 
                            if item_id not in model_items]
        selected_trending_recs = filtered_trending[:trend_count]
        
        # Combine recommendations with guaranteed diversity
        diversity_seeds = selected_model_recs + selected_trending_recs
        
        # Apply even more diversity enhancement
        diversified = self.apply_diversity(
            diversity_seeds, 
            n=n, 
            diversity_weight=self.params.get('category_diversity_weight', 0.25) * 1.5  # Stronger diversity for cold-start
        )
        
        return diversified[:n]
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
        start_time = time.time()
        
        # Check if this is a cold-start user
        is_cold_start = False
        if self.user_item_matrix is not None:
            is_cold_start = user_id not in self.user_item_matrix.index
            
        # Get user interaction count for context
        user_interaction_count = 0
        if not is_cold_start and self.user_item_matrix is not None:
            user_interactions = self.user_item_matrix.loc[user_id]
            user_interaction_count = (user_interactions > 0).sum()
        
        # Get recommendations as (project_id, score) tuples
        recommendations = self.recommend_for_user(user_id, n)
        
        # Convert to detailed project dictionaries
        detailed_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # Add recommendation source info if available
                if project_id in self.recommendation_sources:
                    sources = self.recommendation_sources[project_id]
                    project_dict['recommendation_source'] = '+'.join(sources)
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        # Add overall recommendation metadata to the first recommendation
        if detailed_recommendations:
            # Get effective weights
            fecf_weight, ncf_weight, diversity_weight = self.get_effective_weights(user_id)
            
            recommendation_metadata = {
                'user_id': user_id,
                'is_cold_start': is_cold_start,
                'interaction_count': user_interaction_count,
                'model_weights': {
                    'fecf': fecf_weight,
                    'ncf': ncf_weight,
                    'diversity': diversity_weight
                },
                'processing_time_ms': int((time.time() - start_time) * 1000),
                'timestamp': datetime.now().isoformat()
            }
            
            detailed_recommendations[0]['recommendation_metadata'] = recommendation_metadata
        
        return detailed_recommendations
    
    def get_recommendations_by_category(self, user_id: str, category: str, n: int = 10, chain: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi yang difilter berdasarkan kategori dengan opsional filter chain
        """
        logger.info(f"Getting category-filtered recommendations for user {user_id}, category={category}, chain={chain}, strict={strict}")
        
        # Perbaikan baru: Verifikasi kategori lowercase untuk pencocokan yang lebih baik
        category_lower = category.lower()
        
        # Get initial recommendations with increased count for filtering
        multiplier = 5  # Meningkatkan multiplier untuk mendapatkan lebih banyak kandidat potensial
        recommendations = self.recommend_for_user(user_id, n=n*multiplier)
        
        # Filter projects by category (and chain if provided)
        filtered_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_df_row = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_df_row.empty:
                # Check category match
                category_match = False
                project_category = None
                
                # Check different category fields for match dengan pencocokan yang ditingkatkan
                if 'categories_list' in project_df_row.columns:
                    for _, row in project_df_row.iterrows():
                        cats = row['categories_list']
                        
                        # Handle different category formats
                        categories_list = []
                        if isinstance(cats, list):
                            categories_list = cats
                        elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                            try:
                                categories_list = eval(cats)
                            except:
                                categories_list = [cats]
                        else:
                            categories_list = [cats]
                        
                        # Perbaikan: Iterasi kategori dengan pencocokan yang ditingkatkan
                        for cat in categories_list:
                            if cat and isinstance(cat, str):
                                cat_lower = cat.lower()
                                if (category_lower in cat_lower or cat_lower in category_lower or 
                                    category_lower == cat_lower):
                                    category_match = True
                                    project_category = cat
                                    break
                
                # Coba cari di kolom primary_category dengan pencocokan yang lebih teliti
                if not category_match and 'primary_category' in project_df_row.columns:
                    for _, row in project_df_row.iterrows():
                        primary_cat = row['primary_category']
                        if primary_cat and isinstance(primary_cat, str):
                            primary_cat_lower = primary_cat.lower()
                            if (category_lower in primary_cat_lower or 
                                primary_cat_lower in category_lower or
                                category_lower == primary_cat_lower):
                                category_match = True
                                project_category = primary_cat
                                break
                
                # Apply chain filter if provided
                chain_match = True  # Default to True if no chain filter
                if chain and 'chain' in project_df_row.columns:
                    chain_match = False
                    for _, row in project_df_row.iterrows():
                        if isinstance(row['chain'], str) and (
                            chain.lower() in row['chain'].lower() or 
                            row['chain'].lower() in chain.lower()
                        ):
                            chain_match = True
                            break
                
                # Add to filtered recommendations if both category and chain match
                if category_match and chain_match:
                    # Add match info to help identify original matches
                    filtered_recommendations.append((project_id, score, {
                        'category_match': True,
                        'chain_match': True,
                        'project_category': project_category
                    }))
        
        # Apply additional diversity if we have more than needed
        filtered_count = len(filtered_recommendations)
        logger.info(f"Found {filtered_count} recommendations matching filters exactly")
        
        # Perbaikan: In strict mode, only return exact matches
        if strict:
            if filtered_count == 0:
                logger.warning(f"No recommendations match the filters in strict mode")
                return []
                
            # Convert to detailed recommendations
            detailed_recommendations = []
            
            for project_id, score, match_info in filtered_recommendations[:n]:
                # Find project data
                project_data = self.projects_df[self.projects_df['id'] == project_id]
                
                if not project_data.empty:
                    # Convert to dictionary
                    project_dict = project_data.iloc[0].to_dict()
                    
                    # Add recommendation score
                    project_dict['recommendation_score'] = float(score)
                    
                    # Add filter match metadata
                    project_dict['filter_match'] = 'exact'
                    
                    # Add to results
                    detailed_recommendations.append(project_dict)
            
            return detailed_recommendations[:n]
        
        # Non-strict mode: Add fallback recommendations if needed
        if filtered_count < n and not strict:
            logger.warning(f"Only {filtered_count}/{n} recommendations match filters exactly. Adding fallbacks.")
            
            # Try chain-only matches first if both filters used
            if chain and filtered_count < n // 2:
                chain_only_matches = []
                
                # Get existing IDs to avoid duplicates
                existing_ids = {item[0] for item in filtered_recommendations}
                
                # Try to find chain matches first
                for project_id, score in recommendations:
                    if project_id in existing_ids:
                        continue
                        
                    # Find project data
                    project_df_row = self.projects_df[self.projects_df['id'] == project_id]
                    
                    if not project_df_row.empty and 'chain' in project_df_row.columns:
                        # Check chain match
                        chain_match = False
                        for _, row in project_df_row.iterrows():
                            if isinstance(row['chain'], str) and (
                                chain.lower() in row['chain'].lower() or 
                                row['chain'].lower() in chain.lower()
                            ):
                                chain_match = True
                                break
                                
                        if chain_match:
                            chain_only_matches.append((project_id, score * 0.95, {
                                'category_match': False,
                                'chain_match': True,
                                'match_type': 'chain_only'
                            }))
                            
                            if len(chain_only_matches) + filtered_count >= n:
                                break
                
                filtered_recommendations.extend(chain_only_matches)
            
            # If still not enough, add category-only matches next
            if len(filtered_recommendations) < n and category:
                category_only_matches = []
                
                # Get existing IDs to avoid duplicates
                existing_ids = {item[0] for item in filtered_recommendations}
                
                # Try to find category matches
                for project_id, score in recommendations:
                    if project_id in existing_ids:
                        continue
                        
                    # Find project data
                    project_df_row = self.projects_df[self.projects_df['id'] == project_id]
                    
                    if not project_df_row.empty:
                        # Check category match - pencocokan yang ditingkatkan
                        category_match = False
                        project_category = None
                        
                        # Check for category match with improved matching
                        if 'primary_category' in project_df_row.columns:
                            for _, row in project_df_row.iterrows():
                                primary_cat = row['primary_category']
                                if primary_cat and isinstance(primary_cat, str) and (
                                    category_lower in primary_cat.lower() or 
                                    primary_cat.lower() in category_lower
                                ):
                                    category_match = True
                                    project_category = primary_cat
                                    break
                        
                        if category_match:
                            category_only_matches.append((project_id, score * 0.9, {
                                'category_match': True,
                                'chain_match': False,
                                'project_category': project_category,
                                'match_type': 'category_only'
                            }))
                            
                            if len(category_only_matches) + len(filtered_recommendations) >= n:
                                break
                
                filtered_recommendations.extend(category_only_matches)
        
        # Convert to detailed recommendations
        detailed_recommendations = []
        
        for project_id, score, match_info in filtered_recommendations[:n]:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # Add filter match metadata
                if match_info.get('category_match') and (not chain or match_info.get('chain_match')):
                    project_dict['filter_match'] = 'exact'
                elif match_info.get('match_type') == 'chain_only':
                    project_dict['filter_match'] = 'chain_only'
                elif match_info.get('match_type') == 'category_only':
                    project_dict['filter_match'] = 'category_only'
                else:
                    project_dict['filter_match'] = 'fallback'
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        return detailed_recommendations[:n]
    
    def _get_category_based_recommendations(self, category: str, n: int = 10, chain: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """Helper method to get category-based popular items without personalization"""
        if not self.projects_df is None:
            # Find projects matching the category and optionally chain
            matching_projects = []
            
            for _, row in self.projects_df.iterrows():
                # Check category match
                category_match = False
                
                # Try different category fields
                if 'categories_list' in self.projects_df.columns:
                    cats = row['categories_list']
                    
                    # Handle different category formats
                    categories_list = []
                    if isinstance(cats, list):
                        categories_list = cats
                    elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                        try:
                            categories_list = eval(cats)
                        except:
                            categories_list = [cats]
                    else:
                        categories_list = [cats]
                    
                    # Check for category match
                    for cat in categories_list:
                        if isinstance(cat, str) and (category.lower() in cat.lower() or cat.lower() in category.lower()):
                            category_match = True
                            break
                
                if not category_match and 'primary_category' in self.projects_df.columns:
                    if isinstance(row['primary_category'], str) and (
                        category.lower() in row['primary_category'].lower() or 
                        row['primary_category'].lower() in category.lower()
                    ):
                        category_match = True
                
                if not category_match and 'category' in self.projects_df.columns:
                    if isinstance(row['category'], str) and (
                        category.lower() in row['category'].lower() or 
                        row['category'].lower() in category.lower()
                    ):
                        category_match = True
                
                # Apply chain filter if provided
                chain_match = True  # Default to True if no chain filter
                if chain and 'chain' in self.projects_df.columns:
                    chain_match = isinstance(row['chain'], str) and (
                        chain.lower() in row['chain'].lower() or 
                        row['chain'].lower() in chain.lower()
                    )
                
                # Add to matching projects if both category and category match
                if category_match and chain_match:
                    matching_projects.append(row)
            
            # If strict mode and no matches, return empty list
            if strict and len(matching_projects) == 0:
                return []
            
            # Sort by popularity score if available
            result = []
            
            if 'trend_score' in self.projects_df.columns and 'popularity_score' in self.projects_df.columns:
                # Sort by combined trend and popularity
                sorted_projects = sorted(
                    matching_projects,
                    key=lambda x: (
                        x.get('trend_score', 0) * 0.7 + 
                        x.get('popularity_score', 0) * 0.3
                    ),
                    reverse=True
                )
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    score = (row.get('trend_score', 0) * 0.7 + row.get('popularity_score', 0) * 0.3) / 100
                    project_dict['recommendation_score'] = float(min(0.95, score))
                    result.append(project_dict)
            elif 'trend_score' in self.projects_df.columns:
                # Sort by trend score
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('trend_score', 0), reverse=True)
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = float(min(0.9, row.get('trend_score', 0) / 100))
                    result.append(project_dict)
            elif 'popularity_score' in self.projects_df.columns:
                # Sort by popularity score
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('popularity_score', 0), reverse=True)
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = float(min(0.85, row.get('popularity_score', 0) / 100))
                    result.append(project_dict)
            elif 'market_cap' in self.projects_df.columns:
                # Sort by market cap
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('market_cap', 0), reverse=True)
                
                # Calculate max market cap for normalization
                max_market_cap = max([row.get('market_cap', 0) for row in sorted_projects]) if sorted_projects else 1
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    score = 0.8 * row.get('market_cap', 0) / max_market_cap if max_market_cap > 0 else 0.5
                    project_dict['recommendation_score'] = float(score)
                    result.append(project_dict)
            else:
                # Just return the matching projects with a default score
                for row in matching_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = 0.7  # Default score
                    result.append(project_dict)
            
            # In strict mode, don't add any fallbacks
            if strict:
                return result
            
            # If we don't have enough results and not in strict mode, find similar categories
            if len(result) < n // 2 and not strict:
                # Try to find projects with similar categories (partial match)
                similar_matches = []
                existing_ids = {rec['id'] for rec in result}
                
                for _, row in self.projects_df.iterrows():
                    if row['id'] in existing_ids:
                        continue
                    
                    partial_match = False
                    
                    # Check for broader category matches
                    if 'primary_category' in self.projects_df.columns:
                        primary_cat = row['primary_category']
                        if isinstance(primary_cat, str):
                            # Try simple word overlap
                            cat_words = set(primary_cat.lower().split())
                            search_words = set(category.lower().split())
                            if cat_words.intersection(search_words):
                                partial_match = True
                    
                    # Only consider chain if specified
                    chain_match = True
                    if chain and 'chain' in self.projects_df.columns:
                        chain_match = isinstance(row['chain'], str) and (
                            chain.lower() in row['chain'].lower() or 
                            row['chain'].lower() in chain.lower()
                        )
                    
                    if partial_match and chain_match:
                        project_dict = row.to_dict()
                        # Use lower score for partial matches
                        if 'trend_score' in row:
                            score = min(0.75, row['trend_score'] / 150)
                        else:
                            score = 0.6
                        project_dict['recommendation_score'] = float(score)
                        similar_matches.append(project_dict)
                
                # Add similar matches up to the desired count
                for match in similar_matches:
                    if len(result) >= n:
                        break
                    result.append(match)
            
            return result
        
        # Fallback to empty list if no projects data
        return []
    
    def get_recommendations_by_chain(self, user_id: str, chain: str, n: int = 10, category: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi berdasarkan blockchain dengan opsional filter kategori
        """
        logger.info(f"Getting chain-filtered recommendations for user {user_id}, chain={chain}, category={category}, strict={strict}")
        
        # Check if user exists
        if user_id not in self.user_item_matrix.index:
            logger.warning(f"User {user_id} not found in training data, falling back to chain-based popular items")
            return self._get_chain_based_recommendations(chain, n, category, strict=strict)
        
        # Get initial recommendations with increased count for filtering
        multiplier = 4  # NCF may need more filtering headroom
        recommendations = self.recommend_for_user(user_id, n=n*multiplier)
        
        # Filter projects by chain (and category if provided)
        filtered_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_df_row = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_df_row.empty:
                # Check chain match
                chain_match = False
                
                if 'chain' in project_df_row.columns:
                    for _, row in project_df_row.iterrows():
                        if isinstance(row['chain'], str) and (
                            chain.lower() in row['chain'].lower() or 
                            row['chain'].lower() in chain.lower()
                        ):
                            chain_match = True
                            break
                
                # Apply category filter if provided
                category_match = True  # Default to True if no category filter
                if category:
                    category_match = False
                    
                    # Try different category fields
                    if 'categories_list' in project_df_row.columns:
                        for _, row in project_df_row.iterrows():
                            cats = row['categories_list']
                            
                            # Handle different category formats
                            categories_list = []
                            if isinstance(cats, list):
                                categories_list = cats
                            elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                                try:
                                    categories_list = eval(cats)
                                except:
                                    categories_list = [cats]
                            else:
                                categories_list = [cats]
                            
                            # Check for category match
                            for cat in categories_list:
                                if isinstance(cat, str) and (category.lower() in cat.lower() or cat.lower() in category.lower()):
                                    category_match = True
                                    break
                    
                    if not category_match and 'primary_category' in project_df_row.columns:
                        for _, row in project_df_row.iterrows():
                            if isinstance(row['primary_category'], str) and (
                                category.lower() in row['primary_category'].lower() or 
                                row['primary_category'].lower() in category.lower()
                            ):
                                category_match = True
                                break
                    
                    if not category_match and 'category' in project_df_row.columns:
                        for _, row in project_df_row.iterrows():
                            if isinstance(row['category'], str) and (
                                category.lower() in row['category'].lower() or 
                                row['category'].lower() in category.lower()
                            ):
                                category_match = True
                                break
                
                # Add to filtered recommendations if both chain and category match
                if chain_match and category_match:
                    # PERBAIKAN: Tambahkan info filter match
                    filtered_recommendations.append((project_id, score, {
                        'chain_match': True,
                        'category_match': category_match
                    }))
        
        # Convert to detailed recommendations
        detailed_recommendations = []
        
        for item in filtered_recommendations[:n]:
            # PERBAIKAN: Menyesuaikan unpacking sesuai dengan format yang dikirim
            if len(item) == 3:
                project_id, score, match_info = item
            else:
                project_id, score = item
                match_info = {'chain_match': True, 'category_match': True}
            
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # PERBAIKAN: Tambahkan filter_match untuk exact match
                project_dict['filter_match'] = 'exact'
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        logger.info(f"Found {len(detailed_recommendations)} filtered recommendations")
        
        # Perbaikan: If strict mode, only return exact matches
        if strict:
            return detailed_recommendations[:n]
        
        # If we have too few recommendations, backfill with chain-based popular items
        if len(detailed_recommendations) < n // 2:
            logger.warning(f"Too few filtered recommendations, adding chain-based popular items")
            
            # Get IDs of already added recommendations
            existing_ids = {rec['id'] for rec in detailed_recommendations}
            
            # Get chain-based popular items
            chain_popular = self._get_chain_based_recommendations(chain, n*2, category)
            
            # Add non-duplicate items
            for rec in chain_popular:
                if rec['id'] not in existing_ids and len(detailed_recommendations) < n:
                    # Mark as popular supplementary item
                    rec['recommendation_source'] = 'chain-popular'
                    # PERBAIKAN: Jika rec sudah memiliki filter_match, pastikan itu konsisten
                    if 'filter_match' not in rec:
                        rec['filter_match'] = 'fallback'
                    detailed_recommendations.append(rec)
        
        return detailed_recommendations[:n]
    
    def _get_chain_based_recommendations(self, chain: str, n: int = 10, category: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """Helper method to get chain-based popular items without personalization"""
        if not self.projects_df is None:
            # Find projects matching the chain and optionally category
            matching_projects = []
            
            for _, row in self.projects_df.iterrows():
                # Check chain match
                chain_match = False
                
                if 'chain' in self.projects_df.columns:
                    if isinstance(row['chain'], str) and (
                        chain.lower() in row['chain'].lower() or 
                        row['chain'].lower() in chain.lower()
                    ):
                        chain_match = True
                
                # Apply category filter if provided
                category_match = True  # Default to True if no category filter
                if category:
                    category_match = False
                    
                    # Check different category fields
                    if 'categories_list' in self.projects_df.columns:
                        cats = row['categories_list']
                        
                        # Handle different category formats
                        categories_list = []
                        if isinstance(cats, list):
                            categories_list = cats
                        elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                            try:
                                categories_list = eval(cats)
                            except:
                                categories_list = [cats]
                        else:
                            categories_list = [cats]
                        
                        # Check for category match
                        for cat in categories_list:
                            if isinstance(cat, str) and (category.lower() in cat.lower() or cat.lower() in category.lower()):
                                category_match = True
                                break
                    
                    if not category_match and 'primary_category' in self.projects_df.columns:
                        if isinstance(row['primary_category'], str) and (
                            category.lower() in row['primary_category'].lower() or 
                            row['primary_category'].lower() in category.lower()
                        ):
                            category_match = True
                    
                    if not category_match and 'category' in self.projects_df.columns:
                        if isinstance(row['category'], str) and (
                            category.lower() in row['category'].lower() or 
                            row['category'].lower() in category.lower()
                        ):
                            category_match = True
                
                # Add to matching projects if both chain and category match
                if chain_match and category_match:
                    matching_projects.append(row)
            
            # If strict mode and no matches, return empty list
            if strict and len(matching_projects) == 0:
                return []
            
            # Sort by popularity score if available
            result = []
            
            if 'trend_score' in self.projects_df.columns and 'popularity_score' in self.projects_df.columns:
                # Sort by combined trend and popularity
                sorted_projects = sorted(
                    matching_projects,
                    key=lambda x: (
                        x.get('trend_score', 0) * 0.7 + 
                        x.get('popularity_score', 0) * 0.3
                    ),
                    reverse=True
                )
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    score = (row.get('trend_score', 0) * 0.7 + row.get('popularity_score', 0) * 0.3) / 100
                    project_dict['recommendation_score'] = float(min(0.95, score))
                    # PERBAIKAN: Tambahkan filter_match untuk exact match
                    project_dict['filter_match'] = 'exact'
                    result.append(project_dict)
            elif 'trend_score' in self.projects_df.columns:
                # Sort by trend score
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('trend_score', 0), reverse=True)
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = float(min(0.9, row.get('trend_score', 0) / 100))
                    # PERBAIKAN: Tambahkan filter_match untuk exact match
                    project_dict['filter_match'] = 'exact'
                    result.append(project_dict)
            elif 'popularity_score' in self.projects_df.columns:
                # Sort by popularity score
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('popularity_score', 0), reverse=True)
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = float(min(0.85, row.get('popularity_score', 0) / 100))
                    # PERBAIKAN: Tambahkan filter_match untuk exact match
                    project_dict['filter_match'] = 'exact'
                    result.append(project_dict)
            elif 'market_cap' in self.projects_df.columns:
                # Sort by market cap
                sorted_projects = sorted(matching_projects, key=lambda x: x.get('market_cap', 0), reverse=True)
                
                # Calculate max market cap for normalization
                max_market_cap = max([row.get('market_cap', 0) for row in sorted_projects]) if sorted_projects else 1
                
                for row in sorted_projects[:n]:
                    project_dict = row.to_dict()
                    score = 0.8 * row.get('market_cap', 0) / max_market_cap if max_market_cap > 0 else 0.5
                    project_dict['recommendation_score'] = float(score)
                    # PERBAIKAN: Tambahkan filter_match untuk exact match
                    project_dict['filter_match'] = 'exact'
                    result.append(project_dict)
            else:
                # Just return the matching projects with a default score
                for row in matching_projects[:n]:
                    project_dict = row.to_dict()
                    project_dict['recommendation_score'] = 0.7  # Default score
                    # PERBAIKAN: Tambahkan filter_match untuk exact match
                    project_dict['filter_match'] = 'exact'
                    result.append(project_dict)
                    
            # Jika strict mode, kembalikan hanya hasil yang cocok
            if strict:
                return result
            
            # If we don't have enough results and not in strict mode, add more with looser chain matching
            if len(result) < n // 2 and not strict:
                # Try to find projects with similar chain
                similar_matches = []
                existing_ids = {rec['id'] for rec in result}
                
                # Chain word fragments that might match
                chain_fragments = [fragment for fragment in chain.lower().split('-') if len(fragment) > 2]
                chain_fragments.extend([fragment for fragment in chain.lower().split('_') if len(fragment) > 2])
                
                for _, row in self.projects_df.iterrows():
                    if row['id'] in existing_ids:
                        continue
                    
                    partial_chain_match = False
                    
                    # Check for partial chain match
                    if 'chain' in self.projects_df.columns and isinstance(row['chain'], str):
                        row_chain = row['chain'].lower()
                        for fragment in chain_fragments:
                            if fragment in row_chain:
                                partial_chain_match = True
                                break
                    
                    # Check category if provided
                    category_match = True
                    if category:
                        category_match = False
                        if 'primary_category' in self.projects_df.columns:
                            if isinstance(row['primary_category'], str) and (
                                category.lower() in row['primary_category'].lower() or 
                                row['primary_category'].lower() in category.lower()
                            ):
                                category_match = True
                    
                    if partial_chain_match and category_match:
                        project_dict = row.to_dict()
                        # Lower score for partial matches
                        if 'trend_score' in row:
                            score = min(0.75, row['trend_score'] / 150)
                        else:
                            score = 0.6
                        project_dict['recommendation_score'] = float(score)
                        # PERBAIKAN: Tambahkan filter_match untuk partial match
                        project_dict['filter_match'] = 'fallback'
                        similar_matches.append(project_dict)
                
                # Sort similar matches by score
                similar_matches.sort(key=lambda x: x['recommendation_score'], reverse=True)
                
                # Add similar matches up to the desired count
                for match in similar_matches:
                    if len(result) >= n:
                        break
                    result.append(match)
            
            return result
        
        # Fallback to empty list if no projects data
        return []
    
    def get_recommendations_by_category_and_chain(self, user_id: str, category: str, chain: str, n: int = 10, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi berdasarkan kategori dan chain secara bersamaan
        """
        logger.info(f"Getting recommendations filtered by both category '{category}' and chain '{chain}' for user {user_id}")
        
        # Using category filter with chain parameter is more efficient
        return self.get_recommendations_by_category(user_id, category, n=n, chain=chain, strict=strict)
    
    def get_cold_start_recommendations(self, 
                          user_interests: Optional[List[str]] = None,
                          n: int = 10) -> List[Dict[str, Any]]:
        # Untuk HybridRecommender, kita bisa menggunakan dummy user_id untuk _get_cold_start_recommendations
        # karena method tersebut hanya menggunakan user_id untuk konsistensi interface
        dummy_user_id = "cold_start_user"
        
        # Jika user_interests disediakan, kita perlu menggunakannya untuk meningkatkan rekomendasi
        enhanced_recommendations = []
        
        # Khusus untuk kasus dengan user_interests
        if user_interests and self.projects_df is not None:
            logger.info(f"Using user interests for cold-start: {user_interests}")
            
            # Prepare category matches for interest-based filtering
            interest_projects = {}
            category_distributions = {}
            
            # Get projects for each interest category
            for interest in user_interests:
                # Build index of categories with flexible matching
                matching_projects = []
                for _, row in self.projects_df.iterrows():
                    if 'categories_list' in row:
                        categories = row['categories_list']
                        # Check if any category matches the interest
                        if any(interest.lower() in cat.lower() or cat.lower() in interest.lower() 
                               for cat in categories):
                            matching_projects.append((row['id'], 1.0))
                    elif 'primary_category' in row:
                        category = row['primary_category']
                        if isinstance(category, str) and (interest.lower() in category.lower() or 
                                                        category.lower() in interest.lower()):
                            matching_projects.append((row['id'], 1.0))
                
                # Get trend-weighted projects for this interest
                if 'trend_score' in self.projects_df.columns:
                    for _, row in self.projects_df.iterrows():
                        if row['id'] not in [p[0] for p in matching_projects]:
                            # Add trending projects even if they don't match exactly
                            trend_score = row['trend_score'] / 100 if row['trend_score'] <= 100 else 1.0
                            if trend_score > 0.7:  # Very trending
                                # Check for secondary matches
                                if 'categories_list' in row:
                                    categories = row['categories_list']
                                    # Add with lower score for partial matches
                                    for cat in categories:
                                        if (interest.lower() in cat.lower() or cat.lower() in interest.lower()):
                                            matching_projects.append((row['id'], 0.8))
                                            break
                
                interest_projects[interest] = matching_projects
                category_distributions[interest] = len(matching_projects)
            
            # Sort interests by available project count (prioritize interests with more matches)
            sorted_interests = sorted(category_distributions.items(), key=lambda x: x[1], reverse=True)
            
            # Calculate how many projects to get from each interest
            total_projects = sum(category_distributions.values())
            interest_allocation = {}
            if total_projects > 0:
                remaining = n
                for interest, count in sorted_interests:
                    # Allocate proportionally with minimum guarantees
                    allocation = max(1, min(remaining, int(n * count / total_projects)))
                    interest_allocation[interest] = allocation
                    remaining -= allocation
                
                # Distribute any remaining slots
                if remaining > 0:
                    for interest in interest_allocation:
                        if remaining > 0:
                            interest_allocation[interest] += 1
                            remaining -= 1
                        else:
                            break
            
            # Get top projects from each interest category
            for interest, allocation in interest_allocation.items():
                if interest in interest_projects:
                    projects = interest_projects[interest]
                    if projects:
                        # Sort by score
                        projects.sort(key=lambda x: x[1], reverse=True)
                        # Add top projects not already selected
                        selected_ids = [p[0] for p in enhanced_recommendations]
                        for project_id, score in projects:
                            if project_id not in selected_ids and len(enhanced_recommendations) < n:
                                enhanced_recommendations.append((project_id, score))
                                selected_ids.append(project_id)
                                
                                # Respect allocation limits
                                if sum(1 for p in enhanced_recommendations if p[0] in 
                                      [proj[0] for proj in interest_projects[interest]]) >= allocation:
                                    break
            
            # If we still don't have enough recommendations, use trending and popular
            if len(enhanced_recommendations) < n:
                # Get trending projects
                trending_projects = []
                if 'trend_score' in self.projects_df.columns:
                    trending = self.projects_df.sort_values('trend_score', ascending=False)
                    selected_ids = [p[0] for p in enhanced_recommendations]
                    for _, row in trending.iterrows():
                        if row['id'] not in selected_ids and len(trending_projects) < (n - len(enhanced_recommendations)):
                            trending_projects.append((row['id'], row['trend_score'] / 100 * 0.9))
                
                # Add to recommendations
                enhanced_recommendations.extend(trending_projects)
            
            # Apply diversity
            enhanced_recommendations = self.apply_diversity(
                enhanced_recommendations, 
                n=n, 
                diversity_weight=self.params.get('category_diversity_weight', 0.25) * 1.5  # Stronger diversity
            )
        else:
            # Fallback to regular cold-start when no interests specified
            enhanced_recommendations = self._get_cold_start_recommendations(dummy_user_id, n=n)
        
        # Konversi ke bentuk dictionary
        detailed_recommendations = []
        for project_id, score in enhanced_recommendations[:n]:
            # Cari data proyek
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Konversi ke dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Tambahkan skor rekomendasi
                project_dict['recommendation_score'] = float(score)
                
                # Tambahkan ke hasil
                detailed_recommendations.append(project_dict)
        
        return detailed_recommendations
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        # Leverage FECF for trending recommendations
        if self.fecf_model is not None:
            return self.fecf_model.get_trending_projects(n)
        
        # Fallback if FECF not available
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*2)
            
            # Ensure category diversity
            if 'primary_category' in trending.columns or 'categories_list' in trending.columns:
                # Apply diversity directly
                trend_tuples = [(row['id'], row['trend_score']/100) for _, row in trending.iterrows()]
                diversified = self.apply_diversity(trend_tuples, n, diversity_weight=0.3)
                
                # Convert back to dictionaries
                result = []
                for item_id, score in diversified:
                    project_data = self.projects_df[self.projects_df['id'] == item_id]
                    if not project_data.empty:
                        project_dict = project_data.iloc[0].to_dict()
                        project_dict['recommendation_score'] = float(score)
                        project_dict['trend_score'] = project_dict.get('trend_score', score * 100)
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return top trending without diversity
                result = []
                for _, project in trending.head(n).iterrows():
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0)) / 100
                    result.append(project_dict)
                return result
        else:
            logger.warning("No trend score available, returning top projects by popularity")
            return self.get_popular_projects(n)
    
    def get_popular_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        # Leverage FECF for popularity with cryptocurreny optimizations
        if self.fecf_model is not None:
            return self.fecf_model.get_popular_projects(n)
        
        # Direct implementation with enhancements
        if hasattr(self, 'projects_df'):
            # Combine multiple metrics for a more comprehensive popularity score
            df = self.projects_df.copy()
            
            if 'popularity_score' in df.columns and 'trend_score' in df.columns:
                # Create balanced score with market cap influence
                df['combined_score'] = df['popularity_score'] * 0.6
                
                # Boost with trend score
                df['combined_score'] += df['trend_score'] * 0.4
                
                # Market cap influence
                if 'market_cap' in df.columns and df['market_cap'].max() > 0:
                    # Add small boost for established projects
                    df['market_cap_normalized'] = df['market_cap'] / df['market_cap'].max()
                    df['combined_score'] += df['market_cap_normalized'] * 10
                
                # Sort by this comprehensive metric
                popular = df.sort_values('combined_score', ascending=False).head(n*2)
                
                # Apply diversity to popular projects
                popular_tuples = [(row['id'], row['combined_score']/100) for _, row in popular.iterrows()]
                diversified = self.apply_diversity(popular_tuples, n, diversity_weight=0.25)
                
                # Convert back to dictionaries
                result = []
                for item_id, score in diversified:
                    project_data = df[df['id'] == item_id]
                    if not project_data.empty:
                        project_dict = project_data.iloc[0].to_dict()
                        project_dict['recommendation_score'] = float(score)
                        result.append(project_dict)
                
                return result[:n]
            elif 'popularity_score' in df.columns:
                # Just use popularity score
                popular = df.sort_values('popularity_score', ascending=False).head(n*2)
                
                # Apply diversity
                popular_tuples = [(row['id'], row['popularity_score']/100) for _, row in popular.iterrows()]
                diversified = self.apply_diversity(popular_tuples, n, diversity_weight=0.25)
                
                # Convert to dictionaries
                result = []
                for item_id, score in diversified:
                    project_data = df[df['id'] == item_id]
                    if not project_data.empty:
                        project_dict = project_data.iloc[0].to_dict()
                        project_dict['recommendation_score'] = float(score)
                        result.append(project_dict)
                
                return result[:n]
            elif 'market_cap' in df.columns:
                # Use market cap as fallback
                popular = df.sort_values('market_cap', ascending=False).head(n*2)
                
                # Apply diversity
                if popular['market_cap'].max() > 0:
                    popular_tuples = [(row['id'], row['market_cap']/popular['market_cap'].max() * 0.9) 
                                   for _, row in popular.iterrows()]
                else:
                    popular_tuples = [(row['id'], 0.5) for _, row in popular.iterrows()]
                
                diversified = self.apply_diversity(popular_tuples, n, diversity_weight=0.25)
                
                # Convert to dictionaries
                result = []
                for item_id, score in diversified:
                    project_data = df[df['id'] == item_id]
                    if not project_data.empty:
                        project_dict = project_data.iloc[0].to_dict()
                        project_dict['recommendation_score'] = float(score)
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return random selection with diversity
                random_tuples = [(row['id'], 0.5) for _, row in df.sample(min(n*2, len(df))).iterrows()]
                diversified = self.apply_diversity(random_tuples, n, diversity_weight=0.3)
                
                # Convert to dictionaries
                result = []
                for item_id, score in diversified:
                    project_data = df[df['id'] == item_id]
                    if not project_data.empty:
                        project_dict = project_data.iloc[0].to_dict()
                        project_dict['recommendation_score'] = float(score)
                        result.append(project_dict)
                
                return result[:n]
        
        # Should never reach here
        return []
    
    def get_similar_projects(self, project_id: str, n: int = 10) -> List[Dict[str, Any]]:
        # Delegate to FECF for similarity with cryptocurrency optimizations
        if self.fecf_model is not None:
            return self.fecf_model.get_similar_projects(project_id, n)
        
        # Custom implementation if FECF not available
        if hasattr(self, 'projects_df'):
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                project = project_data.iloc[0]
                
                # Get categories
                categories = []
                if 'categories_list' in project:
                    categories = project['categories_list']
                elif 'primary_category' in project:
                    categories = self.process_categories(project['primary_category'])
                
                # Get chain
                chain = project.get('chain', 'unknown')
                
                # Get market cap tier
                market_cap_tier = 'unknown'
                if 'market_cap' in project:
                    market_cap = project['market_cap']
                    if market_cap > 1e9:
                        market_cap_tier = 'high'
                    elif market_cap > 1e8:
                        market_cap_tier = 'medium'
                    else:
                        market_cap_tier = 'low'
                
                # Calculate similarity scores for all projects
                similarity_scores = []
                
                for _, other_project in self.projects_df.iterrows():
                    if other_project['id'] == project_id:
                        continue
                    
                    # Calculate category similarity
                    category_sim = 0.0
                    other_categories = []
                    
                    if 'categories_list' in other_project:
                        other_categories = other_project['categories_list']
                    elif 'primary_category' in other_project:
                        other_categories = self.process_categories(other_project['primary_category'])
                    
                    # Calculate category overlap
                    if categories and other_categories:
                        common_categories = set(categories) & set(other_categories)
                        category_sim = len(common_categories) / max(len(categories), len(other_categories))
                    
                    # Chain similarity
                    chain_sim = 0.0
                    if 'chain' in other_project:
                        other_chain = other_project['chain']
                        chain_sim = 1.0 if chain == other_chain else 0.0
                    
                    # Market cap similarity
                    market_cap_sim = 0.0
                    if 'market_cap' in other_project:
                        other_market_cap = other_project['market_cap']
                        other_tier = 'unknown'
                        if other_market_cap > 1e9:
                            other_tier = 'high'
                        elif other_market_cap > 1e8:
                            other_tier = 'medium'
                        else:
                            other_tier = 'low'
                            
                        market_cap_sim = 1.0 if market_cap_tier == other_tier else 0.5 if (
                            (market_cap_tier == 'high' and other_tier == 'medium') or
                            (market_cap_tier == 'medium' and other_tier in ['high', 'low']) or
                            (market_cap_tier == 'low' and other_tier == 'medium')) else 0.25
                    
                    # Combined similarity score with cryptocurrency-specific weights
                    # Category is most important for crypto similarity
                    combined_sim = (
                        category_sim * 0.6 +
                        chain_sim * 0.3 +
                        market_cap_sim * 0.1
                    )
                    
                    # Boost score if projects share multiple categories
                    if isinstance(category_sim, float) and category_sim > 0.5:
                        combined_sim *= 1.2
                    
                    # Add trend correlation if available
                    if 'trend_score' in project and 'trend_score' in other_project:
                        trend_diff = abs(project['trend_score'] - other_project['trend_score'])
                        trend_sim = 1.0 - (trend_diff / 100.0) if project['trend_score'] <= 100 else 0.5
                        combined_sim = combined_sim * 0.85 + trend_sim * 0.15
                    
                    # Store similarity with original and adjusted scores
                    similarity_scores.append({
                        'id': other_project['id'],
                        'original_sim': combined_sim,
                        'adjusted_sim': combined_sim,
                        'categories': other_categories,
                        'chain': other_project.get('chain', 'unknown')
                    })
                
                # Apply diversity on top similar projects
                # First sort by original similarity
                similarity_scores.sort(key=lambda x: x['original_sim'], reverse=True)
                top_similar = similarity_scores[:min(n*3, len(similarity_scores))]
                
                # Apply diversity adjustments
                selected_categories = {}
                selected_chains = {}
                
                # Define diversity limits
                max_per_category = max(2, int(n * 0.35))  # Slightly higher for similar projects
                max_per_chain = max(3, int(n * 0.4))
                
                # Process top candidates for diversity
                result = []
                for i, item in enumerate(top_similar):
                    # Always include very top items
                    if i < max(2, n // 4):
                        # Update category and chain counters
                        for category in item['categories']:
                            selected_categories[category] = selected_categories.get(category, 0) + 1
                        
                        chain = item['chain']
                        selected_chains[chain] = selected_chains.get(chain, 0) + 1
                        
                        # Add to result
                        project_data = self.projects_df[self.projects_df['id'] == item['id']]
                        if not project_data.empty:
                            project_dict = project_data.iloc[0].to_dict()
                            project_dict['similarity_score'] = float(item['original_sim'])
                            project_dict['recommendation_score'] = float(item['original_sim'])
                            result.append(project_dict)
                    else:
                        # Apply diversity considerations
                        category_adjustment = 0
                        chain_adjustment = 0
                        
                        # Category diversity check
                        over_represented_category = False
                        for category in item['categories']:
                            cat_count = selected_categories.get(category, 0)
                            if cat_count >= max_per_category:
                                over_represented_category = True
                                break
                        
                        if over_represented_category:
                            category_adjustment = -0.2
                        else:
                            # Check if this adds a new category
                            new_category = False
                            for category in item['categories']:
                                if category not in selected_categories:
                                    new_category = True
                                    break
                            
                            if new_category:
                                category_adjustment = 0.15
                        
                        # Chain diversity check
                        chain = item['chain']
                        chain_count = selected_chains.get(chain, 0)
                        
                        if chain_count >= max_per_chain:
                            chain_adjustment = -0.15
                        elif chain_count == 0:
                            chain_adjustment = 0.1
                        
                        # Apply adjustments
                        diversity_weight = 0.3  # Moderate diversity for similar projects
                        diversity_adjusted_sim = item['original_sim'] + (category_adjustment + chain_adjustment) * diversity_weight
                        
                        item['adjusted_sim'] = max(0.01, diversity_adjusted_sim)
                
                # Re-sort by adjusted scores
                top_similar.sort(key=lambda x: x['adjusted_sim'], reverse=True)
                
                # Select top projects
                selected_ids = [p['id'] for p in result]
                for item in top_similar:
                    if len(result) >= n:
                        break
                    
                    if item['id'] not in selected_ids:
                        project_data = self.projects_df[self.projects_df['id'] == item['id']]
                        if not project_data.empty:
                            project_dict = project_data.iloc[0].to_dict()
                            project_dict['similarity_score'] = float(item['adjusted_sim'])
                            project_dict['recommendation_score'] = float(item['adjusted_sim'])
                            result.append(project_dict)
                            
                            # Update tracking
                            selected_ids.append(item['id'])
                            for category in item['categories']:
                                selected_categories[category] = selected_categories.get(category, 0) + 1
                            
                            chain = item['chain']
                            selected_chains[chain] = selected_chains.get(chain, 0) + 1
                
                return result
                    
            # If project not found, fall back to popular projects
            logger.warning(f"Project {project_id} not found, returning popular projects")
            return self.get_popular_projects(n)
        
        # Should never reach here
        return []


if __name__ == "__main__":
    # Testing the module
    enhanced_hybrid = HybridRecommender()
    
    # Load data
    if enhanced_hybrid.load_data():
        # Train model
        metrics = enhanced_hybrid.train(save_model=True)
        print(f"Training metrics: {metrics}")
        
        # Test recommendations
        if enhanced_hybrid.user_item_matrix is not None and not enhanced_hybrid.user_item_matrix.empty:
            test_user = enhanced_hybrid.user_item_matrix.index[0]
            print(f"\nHybrid recommendations for user {test_user}:")
            recs = enhanced_hybrid.recommend_projects(test_user, n=5)
            
            for i, rec in enumerate(recs, 1):
                print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
                
        # Test popular projects
        print("\nPopular projects:")
        popular = enhanced_hybrid.get_popular_projects(n=5)
        
        for i, proj in enumerate(popular, 1):
            print(f"{i}. {proj.get('name', proj.get('id'))} - Score: {proj.get('recommendation_score', 0):.4f}")
    else:
        print("Failed to load data for Enhanced Hybrid Recommender")