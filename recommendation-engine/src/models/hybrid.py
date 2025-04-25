"""
Enhanced Hybrid Recommender yang mengintegrasikan FECF dan NCF 
dengan metode ensemble yang lebih canggih untuk domain cryptocurrency
"""

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
    optimalisasi untuk data sparse, dan penanganan kategori yang lebih baik
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
        """
        Initialize Enhanced Hybrid Recommender
        
        Args:
            params: Model parameters (overwrites defaults from config)
        """
        # Model parameters dengan default yang lebih baik
        default_params = {
            "ncf_weight": 0.3,              # Kurangi bobot NCF karena underperform
            "fecf_weight": 0.7,             # Tingkatkan bobot FECF
            "interaction_threshold_low": 3,  # Turunkan threshold cold start
            "interaction_threshold_high": 10, # Turunkan high threshold
            "diversity_factor": 0.2,         # Kurangi faktor diversitas yang terlalu agresif
            "cold_start_fecf_weight": 0.9,   # Lebih dominan FECF untuk cold start
            "explore_ratio": 0.15,           # Kurangi eksplorasi
            "normalization": "sigmoid",      # Metode normalisasi ("linear", "sigmoid", "rank", "none")
            "ensemble_method": "weighted_avg", # Metode ensemble ("weighted_avg", "max", "rank_fusion")
            "n_candidates_factor": 3,        # Faktor jumlah kandidat vs. hasil akhir
            "category_diversity_weight": 0.15, # Bobot diversitas kategori
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
            "trend_importance": 0.7,
            "popularity_decay": 0.05,
            "category_correlation": 0.6,
            "market_cap_influence": 0.4,
            "chain_importance": 0.3
        }
        
    def load_data(self, 
                 projects_path: Optional[str] = None, 
                 interactions_path: Optional[str] = None,
                 features_path: Optional[str] = None) -> bool:
        """
        Load data for the model
        
        Args:
            projects_path: Path to projects data
            interactions_path: Path to interactions data
            features_path: Path to features data
            
        Returns:
            bool: Success status
        """
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
                
                logger.info("Data loaded successfully for Enhanced Hybrid Recommender")
                return True
            else:
                logger.error("Failed to load data for one or more component models")
                return False
                
        except Exception as e:
            logger.error(f"Error loading data for Enhanced Hybrid Recommender: {str(e)}")
            return False
    
    def preprocess_categories(self):
        """
        Pre-process kategori untuk menangani multiple categories dengan lebih baik
        """
        if self.projects_df is None or 'primary_category' not in self.projects_df.columns:
            return
            
        # Check sample untuk mendeteksi format kategori
        sample_value = self.projects_df['primary_category'].iloc[0] if len(self.projects_df) > 0 else None
        
        # Buat kolom kategori yang distandarisasi
        self.projects_df['categories_list'] = self.projects_df['primary_category'].apply(self.process_categories)
        
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
        
    def train(self, 
             fecf_params: Optional[Dict[str, Any]] = None,
             ncf_params: Optional[Dict[str, Any]] = None,
             save_model: bool = True) -> Dict[str, Any]:
        """
        Train all component models
        
        Args:
            fecf_params: Parameters for FECF model
            ncf_params: Parameters for NCF model
            save_model: Whether to save the trained models
            
        Returns:
            dict: Training metrics
        """
        metrics = {}
        
        # Train FECF
        logger.info("Training Feature-Enhanced CF component")
        fecf_metrics = self.fecf_model.train(save_model=save_model)
        metrics['fecf'] = fecf_metrics
        
        # Train NCF
        logger.info("Training Neural CF component with optimized parameters")
        
        # Update NCF params dengan nilai yang lebih baik untuk data sparse
        optimized_ncf_params = {
            "val_ratio": 0.15,              # Porsi validasi lebih kecil
            "batch_size": 256,              # Batch size lebih besar untuk stabilitas
            "num_epochs": 20,               # Epoch cukup
            "learning_rate": 0.0005         # Learning rate lebih kecil
        }
        
        # Gabungkan dengan ncf_params yang disediakan
        if ncf_params:
            optimized_ncf_params.update(ncf_params)
            
        ncf_metrics = self.ncf_model.train(save_model=save_model, **optimized_ncf_params)
        metrics['ncf'] = ncf_metrics
        
        # Save hybrid model weights if requested
        if save_model:
            self.save_model()
        
        return metrics
    
    def save_model(self, filepath: Optional[str] = None) -> str:
        """
        Save hybrid model weights and references to component models
        
        Args:
            filepath: Path to save model, if None will use default path
            
        Returns:
            str: Path where model was saved
        """
        if filepath is None:
            # Create default path
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filepath = os.path.join(MODELS_DIR, f"enhanced_hybrid_model_{timestamp}.pkl")
            
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
        """
        Load model components
        
        Args:
            hybrid_filepath: Path to hybrid model file
            fecf_filepath: Path to FECF model file
            ncf_filepath: Path to NCF model file
            
        Returns:
            bool: Success status
        """
        # Load hybrid weights if provided
        if hybrid_filepath and os.path.exists(hybrid_filepath):
            try:
                logger.info(f"Loading enhanced hybrid model from {hybrid_filepath}")
                with open(hybrid_filepath, 'rb') as f:
                    model_state = pickle.load(f)
                    
                self.params = model_state.get('params', self.params)
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
        
        # Model dianggap sukses jika minimal salah satu komponen berhasil dimuat
        return fecf_success or ncf_success
    
    def is_trained(self) -> bool:
        """
        Check if model is trained and ready for predictions
        
        Returns:
            bool: True if model is trained, False otherwise
        """
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
        """
        Normalisasi skor rekomendasi dengan berbagai metode
        
        Args:
            recommendations: List of (item_id, score) tuples
            method: Normalization method ("linear", "sigmoid", "rank", "none")
            
        Returns:
            list: List of (item_id, normalized_score) tuples
        """
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
            # Sigmoid normalization - centers around 0.5 with smoother transition
            mean = scores.mean()
            std = max(scores.std(), 1e-5)  # Avoid division by zero
            z_scores = (scores - mean) / std
            normalized = expit(z_scores)  # Apply sigmoid function
            
        elif method == 'rank':
            # Rank-based normalization
            ranks = np.argsort(np.argsort(scores)[::-1]) + 1
            normalized = 1 - (ranks / len(ranks))
        else:
            # Fallback to original scores
            normalized = scores
            
        # Create normalized recommendations
        normalized_recs = list(zip(items, normalized))
        
        # Store in cache
        self._normalization_cache[cache_key] = normalized_recs
        
        return normalized_recs
    
    def get_effective_weights(self, user_id: str) -> Tuple[float, float, float]:
        """
        Determine model weights based on user interactions and model health
        
        Args:
            user_id: User ID
            
        Returns:
            tuple: (fecf_weight, ncf_weight, diversity_weight)
        """
        # Check model health
        fecf_health = 1.0  # Default full health
        ncf_health = 1.0   # Default full health
        
        if self.fecf_model is None or not hasattr(self.fecf_model, 'model') or self.fecf_model.model is None:
            logger.warning("FECF model not available, falling back to NCF only")
            fecf_health = 0.0
        
        if self.ncf_model is None or not hasattr(self.ncf_model, 'model') or self.ncf_model.model is None:
            logger.warning("NCF model not available, falling back to FECF only")
            ncf_health = 0.0
            
        # Get base weights from params
        base_fecf_weight = self.params.get('fecf_weight', 0.7)
        base_ncf_weight = self.params.get('ncf_weight', 0.3)
        diversity_factor = self.params.get('diversity_factor', 0.2)
        
        # Count user interactions if available
        user_interaction_count = 0
        if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
            user_interactions = self.user_item_matrix.loc[user_id]
            user_interaction_count = (user_interactions > 0).sum()
            
        # Get thresholds
        interaction_threshold_low = self.params.get('interaction_threshold_low', 3)
        interaction_threshold_high = self.params.get('interaction_threshold_high', 10)
        
        # Determine effective weights based on interaction count and model health
        if fecf_health == 0.0:
            # No FECF model available, use only NCF
            effective_fecf_weight = 0.0
            effective_ncf_weight = 1.0
            effective_diversity_weight = diversity_factor * 0.5  # Reduce diversity without FECF
        elif ncf_health == 0.0:
            # No NCF model available, use only FECF
            effective_fecf_weight = 1.0
            effective_ncf_weight = 0.0
            effective_diversity_weight = diversity_factor * 1.2  # Increase diversity with just FECF
        else:
            # Both models available, apply adaptive weighting based on interaction count
            if user_interaction_count < interaction_threshold_low:
                # For cold-start users, rely more on FECF
                effective_fecf_weight = 0.85 * fecf_health  # Increase from 0.75 to 0.85
                effective_ncf_weight = 0.15 * ncf_health    # Decrease from 0.25 to 0.15
                effective_diversity_weight = diversity_factor * 0.7  # Slightly lower diversity for cold-start
            elif user_interaction_count < interaction_threshold_high:
                # Linear interpolation between thresholds
                ratio = (user_interaction_count - interaction_threshold_low) / (interaction_threshold_high - interaction_threshold_low)
                fecf_low = 0.85
                fecf_high = base_fecf_weight
                effective_fecf_weight = (fecf_low - (fecf_low - fecf_high) * ratio) * fecf_health
                effective_ncf_weight = (0.15 + (base_ncf_weight - 0.15) * ratio) * ncf_health
                effective_diversity_weight = diversity_factor * (0.7 + ratio * 0.5)
            else:
                # For active users, use configured weights
                effective_fecf_weight = base_fecf_weight * fecf_health
                effective_ncf_weight = base_ncf_weight * ncf_health
                effective_diversity_weight = diversity_factor
        
        # Normalize weights to sum to 1.0
        total_weight = effective_fecf_weight + effective_ncf_weight
        if total_weight > 0:
            effective_fecf_weight /= total_weight
            effective_ncf_weight /= total_weight
            
        return effective_fecf_weight, effective_ncf_weight, effective_diversity_weight
    
    def get_ensemble_recommendations(self, 
                                   fecf_recs: List[Tuple[str, float]], 
                                   ncf_recs: List[Tuple[str, float]],
                                   fecf_weight: float = 0.7, 
                                   ncf_weight: float = 0.3,
                                   ensemble_method: Optional[str] = None) -> List[Tuple[str, float]]:
        """
        Implement advanced ensemble methods untuk menggabungkan rekomendasi
        
        Args:
            fecf_recs: FECF recommendations as (item_id, score) tuples
            ncf_recs: NCF recommendations as (item_id, score) tuples
            fecf_weight: Weight for FECF model
            ncf_weight: Weight for NCF model
            ensemble_method: Ensemble method ("weighted_avg", "max", "rank_fusion")
            
        Returns:
            list: Combined recommendations as (item_id, score) tuples
        """
        if not fecf_recs and not ncf_recs:
            return []
            
        # Use specified method or default from params
        ensemble_method = ensemble_method or self.params.get('ensemble_method', 'weighted_avg')
            
        # Quick return if only one model's recommendations are available
        if not fecf_recs:
            return ncf_recs
        if not ncf_recs:
            return fecf_recs
            
        # Normalize scores first
        fecf_normalized = self.normalize_scores(fecf_recs, method=self.params.get('normalization', 'sigmoid'))
        ncf_normalized = self.normalize_scores(ncf_recs, method=self.params.get('normalization', 'sigmoid'))
        
        # Create dictionaries for lookup
        fecf_dict = dict(fecf_normalized)
        ncf_dict = dict(ncf_normalized)
        
        # Get all unique items
        all_items = set(fecf_dict.keys()) | set(ncf_dict.keys())
        
        if ensemble_method == 'max':
            # Maximum score ensemble - ambil nilai tertinggi dari kedua model
            results = {}
            for item in all_items:
                fecf_score = fecf_dict.get(item, 0)
                ncf_score = ncf_dict.get(item, 0)
                results[item] = max(fecf_score, ncf_score)
                
        elif ensemble_method == 'rank_fusion':
            # Reciprocal Rank Fusion - menggabungkan berdasarkan posisi (rank) item
            # Hitung rank untuk setiap model
            fecf_ranks = {item: i+1 for i, (item, _) in enumerate(sorted(fecf_dict.items(), key=lambda x: x[1], reverse=True))}
            ncf_ranks = {item: i+1 for i, (item, _) in enumerate(sorted(ncf_dict.items(), key=lambda x: x[1], reverse=True))}
            
            # Konstanta k untuk RRF (biasanya 60)
            k = 60
            
            # Hitung RRF score
            results = {}
            for item in all_items:
                # Gunakan rank maksimum jika item tidak ada di salah satu model
                fecf_rank = fecf_ranks.get(item, len(fecf_ranks) + 1)
                ncf_rank = ncf_ranks.get(item, len(ncf_ranks) + 1)
                
                # RRF formula: sum(1 / (k + rank_i))
                fecf_score = 1.0 / (k + fecf_rank)
                ncf_score = 1.0 / (k + ncf_rank)
                
                # Weighted sum
                results[item] = fecf_weight * fecf_score + ncf_weight * ncf_score
                
        else:  # default to weighted_avg
            # Weighted average ensemble
            results = {}
            for item in all_items:
                fecf_score = fecf_dict.get(item, 0)
                ncf_score = ncf_dict.get(item, 0)
                
                # Weighted average core - lebih sophisticated dari naive weighted sum
                if item in fecf_dict and item in ncf_dict:
                    # Jika item direkomendasikan oleh kedua model, gunakan weighted average
                    results[item] = fecf_score * fecf_weight + ncf_score * ncf_weight
                elif item in fecf_dict:
                    # Jika hanya FECF merekomendasikan, kurangi confidence sedikit
                    results[item] = fecf_score * fecf_weight * 0.9  # Slight confidence reduction
                else:
                    # Jika hanya NCF merekomendasikan, kurangi confidence lebih banyak
                    results[item] = ncf_score * ncf_weight * 0.8  # More confidence reduction
        
        # Convert to list of tuples and sort
        combined_recs = [(item, score) for item, score in results.items()]
        combined_recs.sort(key=lambda x: x[1], reverse=True)
        
        return combined_recs
    
    def apply_diversity(self, recommendations: List[Tuple[str, float]], 
                    n: int, diversity_weight: float = 0.2) -> List[Tuple[str, float]]:
        """
        Apply category and chain diversity with improved algorithm
        
        Args:
            recommendations: List of (item_id, score) tuples
            n: Number of results to return
            diversity_weight: Weight of diversity factors
            
        Returns:
            list: Diversified recommendations
        """
        if not recommendations or len(recommendations) <= n:
            return recommendations
            
        # Prepare item metadata
        item_categories = {}
        item_chains = {}
        
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
        
        # If no category/chain data available, just return top-n
        if not item_categories and not item_chains:
            return recommendations[:n]
            
        # Select top items without diversity first (guaranteed selection)
        top_count = max(n // 5, 1)  # ~20% by pure score
        result = recommendations[:top_count]
        
        # Track selected categories and chains
        selected_categories = {}
        selected_chains = {}
        
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
        
        # Calculate diversity limits
        max_per_category = max(2, int(n * 0.3))  # Maximum ~30% per category
        max_per_chain = max(3, int(n * 0.4))     # Maximum ~40% per chain
        
        # Process remaining candidates
        remaining = recommendations[top_count:]
        
        # Calculate diversity adjusted scores for all remaining items
        diversity_adjusted = []
        
        for item_id, score in remaining:
            category_adjustment = 0
            chain_adjustment = 0
            
            # Category diversity adjustment
            if item_id in item_categories:
                # Periksa semua kategori item
                cat_adjustments = []
                item_cats = item_categories[item_id]
                
                for category in item_cats:
                    cat_count = selected_categories.get(category, 0)
                    
                    if cat_count >= max_per_category:
                        # Heavy penalty for overrepresented category
                        cat_adjustments.append(-0.4)
                    elif cat_count == 0:
                        # Strong boost for new category
                        cat_adjustments.append(0.2) 
                    else:
                        # Smaller adjustment based on count
                        adjustment = 0.1 * (1 - cat_count / max_per_category)
                        cat_adjustments.append(adjustment)
                
                # Use worst adjustment as the primary signal
                # This avoids selecting items from overrepresented categories
                # even if they have other categories that are underrepresented
                if cat_adjustments:
                    category_adjustment = min(cat_adjustments)
            
            # Chain diversity adjustment (simpler)
            if item_id in item_chains:
                chain = item_chains[item_id]
                chain_count = selected_chains.get(chain, 0)
                
                if chain_count >= max_per_chain:
                    chain_adjustment = -0.3  # Penalty for overrepresented chain
                elif chain_count == 0:
                    chain_adjustment = 0.15  # Bonus for new chain
                else:
                    chain_adjustment = 0.05 * (1 - chain_count / max_per_chain)
            
            # Apply diversity weight
            diversity_score = (category_adjustment + chain_adjustment * 0.5) * diversity_weight
            adjusted_score = score + diversity_score
            
            # Store original item, score, and adjusted score
            diversity_adjusted.append((item_id, score, adjusted_score))
        
        # Sort by adjusted score
        diversity_adjusted.sort(key=lambda x: x[2], reverse=True)
        
        # Select remaining items
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
            
            # Add to result with original score
            result.append((item_id, original_score))
        
        return result
    
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user using ensemble approach
        
        Args:
            user_id: User ID
            n: Number of recommendations
            exclude_known: Whether to exclude already interacted items
            
        Returns:
            list: List of (project_id, score) tuples
        """
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
        
        # Can't do anything if neither model is available
        if fecf_weight == 0 and ncf_weight == 0:
            logger.error("No models available for recommendations")
            return []
            
        # Determine number of candidates to get from each model
        n_candidates = min(n * self.params.get('n_candidates_factor', 3), 100)
        
        # Get FECF recommendations if available
        fecf_recs = []
        if fecf_weight > 0:
            try:
                fecf_recs = self.fecf_model.recommend_for_user(
                    user_id, 
                    n=n_candidates,
                    exclude_known=exclude_known
                )
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
                ncf_recs = self.ncf_model.recommend_for_user(
                    user_id, 
                    n=n_candidates,
                    exclude_known=exclude_known
                )
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
        combined_recs = self.get_ensemble_recommendations(
            fecf_recs=fecf_recs,
            ncf_recs=ncf_recs,
            fecf_weight=fecf_weight,
            ncf_weight=ncf_weight,
            ensemble_method=self.params.get('ensemble_method', 'weighted_avg')
        )
        
        # Apply diversity
        diversified_recs = self.apply_diversity(
            combined_recs, 
            n=n, 
            diversity_weight=diversity_weight
        )
        
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
        """
        Get enhanced cold-start recommendations with improved category handling
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # Use optimized weights for cold-start
        fecf_weight = self.params.get('cold_start_fecf_weight', 0.9)
        ncf_weight = 1.0 - fecf_weight
        
        # Get FECF cold-start recommendations
        fecf_recs = []
        if fecf_weight > 0 and self.fecf_model is not None:
            try:
                fecf_cold_start = self.fecf_model.get_cold_start_recommendations(n=n*2)
                fecf_recs = [(rec['id'], rec['recommendation_score']) 
                           for rec in fecf_cold_start if 'id' in rec]
            except Exception as e:
                logger.warning(f"Error getting FECF cold-start recommendations: {e}")
                
        # Get NCF cold-start recommendations (popularity-based)
        ncf_recs = []
        if ncf_weight > 0 and self.ncf_model is not None:
            try:
                ncf_cold_start = self.ncf_model.get_popular_projects(n=n*2)
                ncf_recs = [(rec['id'], rec['recommendation_score']) 
                          for rec in ncf_cold_start if 'id' in rec]
            except Exception as e:
                logger.warning(f"Error getting NCF cold-start recommendations: {e}")
                
        # If both models failed, return trending projects
        if not fecf_recs and not ncf_recs:
            trending_projects = []
            if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
                trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*2)
                trending_projects = [(row['id'], row['trend_score']/100) 
                                  for _, row in trending.iterrows()]
                return trending_projects[:n]
            else:
                # Last resort: just return random projects
                if hasattr(self, 'projects_df'):
                    random_projects = self.projects_df.sample(min(n, len(self.projects_df)))
                    return [(row['id'], 0.5) for _, row in random_projects.iterrows()]
                return []
                
        # Also get trending projects for cold-start
        trending_recs = []
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n)
            trending_recs = [(row['id'], row['trend_score']/100) 
                           for _, row in trending.iterrows()]
            
        # Use a 3-way ensemble for cold-start
        # 70% from model recommendations (weighted between FECF and NCF)
        # 30% from trending for discovery
        
        # Get model recommendations using ensemble
        model_recs = self.get_ensemble_recommendations(
            fecf_recs=fecf_recs,
            ncf_recs=ncf_recs,
            fecf_weight=fecf_weight,
            ncf_weight=ncf_weight,
            ensemble_method='weighted_avg'  # Simplified for cold-start
        )
        
        # Calculate counts for each source
        model_count = int(n * 0.7)  # 70% from models
        trend_count = n - model_count  # 30% from trending
        
        # Make sure counts are at least 1 if we have recommendations
        model_count = max(1, model_count) if model_recs else 0
        trend_count = max(1, trend_count) if trending_recs else 0
        
        # Adjust if either source is empty
        if not model_recs:
            trend_count = n
        elif not trending_recs:
            model_count = n
        else:
            # Make sure they sum to n
            while model_count + trend_count > n:
                if model_count > trend_count:
                    model_count -= 1
                else:
                    trend_count -= 1
                    
            while model_count + trend_count < n:
                if model_count < trend_count:
                    model_count += 1
                else:
                    trend_count += 1
        
        # Get recommendations from each source
        selected_model_recs = model_recs[:model_count]
        
        # Filter out trending items that are already in model recs
        model_items = {item_id for item_id, _ in selected_model_recs}
        filtered_trending = [(item_id, score) for item_id, score in trending_recs 
                          if item_id not in model_items]
        selected_trending_recs = filtered_trending[:trend_count]
        
        # Combine all sources
        combined = selected_model_recs + selected_trending_recs
        
        # Apply diversity
        diversified = self.apply_diversity(
            combined, 
            n=n, 
            diversity_weight=self.params.get('category_diversity_weight', 0.15)
        )
        
        return diversified[:n]
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Generate project recommendations with full details
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of project dictionaries with recommendation scores
        """
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
                'timestamp': datetime.now().isoformat()
            }
            
            detailed_recommendations[0]['recommendation_metadata'] = recommendation_metadata
        
        return detailed_recommendations
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get trending projects
        
        Args:
            n: Number of trending projects to return
            
        Returns:
            list: List of trending project dictionaries
        """
        # Delegate to FECF
        if self.fecf_model is not None:
            return self.fecf_model.get_trending_projects(n)
        
        # Fallback if FECF not available
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n)
            
            # Prepare result
            result = []
            for _, project in trending.iterrows():
                project_dict = project.to_dict()
                
                # Add recommendation score from trend score
                project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0)) / 100
                
                # Add to results
                result.append(project_dict)
                
            return result
        
        # Last resort
        return self.get_popular_projects(n)
    
    def get_popular_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get popular projects
        
        Args:
            n: Number of popular projects to return
            
        Returns:
            list: List of popular project dictionaries
        """
        # Delegate to FECF
        if self.fecf_model is not None:
            return self.fecf_model.get_popular_projects(n)
        
        # Fallback if FECF not available
        if hasattr(self, 'projects_df'):
            if 'popularity_score' in self.projects_df.columns:
                popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n)
            elif 'market_cap' in self.projects_df.columns:
                # Use market cap as a proxy for popularity
                popular = self.projects_df.sort_values('market_cap', ascending=False).head(n)
            else:
                # Random selection
                popular = self.projects_df.sample(min(n, len(self.projects_df)))
            
            # Prepare result
            result = []
            for _, project in popular.iterrows():
                project_dict = project.to_dict()
                
                # Add recommendation score
                if 'popularity_score' in project_dict:
                    project_dict['recommendation_score'] = float(project_dict['popularity_score']) / 100
                elif 'market_cap' in project_dict:
                    max_cap = self.projects_df['market_cap'].max()
                    if max_cap > 0:
                        project_dict['recommendation_score'] = float(project_dict['market_cap']) / max_cap
                    else:
                        project_dict['recommendation_score'] = 0.5
                else:
                    project_dict['recommendation_score'] = 0.5
                
                # Add to results
                result.append(project_dict)
                
            return result
        
        # Should never reach here
        return []
    
    def get_similar_projects(self, project_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Find similar projects
        
        Args:
            project_id: Project ID
            n: Number of similar projects to return
            
        Returns:
            list: List of similar project dictionaries
        """
        # Delegate to FECF
        if self.fecf_model is not None:
            return self.fecf_model.get_similar_projects(project_id, n)
        
        # Fallback using category similarity if FECF not available
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
                
                # Filter by category and chain
                if categories and chain != 'unknown':
                    # Look for projects in same category AND chain first
                    similar_by_both = self.projects_df[
                        (self.projects_df['id'] != project_id) &
                        (self.projects_df['chain'] == chain)
                    ]
                    
                    if 'categories_list' in self.projects_df.columns:
                        # Filter by category lists
                        similar_by_both = similar_by_both[
                            similar_by_both['categories_list'].apply(
                                lambda x: any(cat in categories for cat in x)
                            )
                        ]
                    elif 'primary_category' in self.projects_df.columns:
                        # Filter by primary_category
                        similar_by_both = similar_by_both[
                            similar_by_both['primary_category'].apply(
                                lambda x: x in categories if isinstance(x, str) else False
                            )
                        ]
                    
                    if len(similar_by_both) >= n:
                        # Enough results, add similarity score and return
                        result = []
                        for _, similar in similar_by_both.head(n).iterrows():
                            sim_dict = similar.to_dict()
                            sim_dict['similarity_score'] = 0.85  # High similarity
                            result.append(sim_dict)
                        return result
                
                # If not enough by both, try just category
                if categories:
                    similar_by_category = self.projects_df[
                        self.projects_df['id'] != project_id
                    ]
                    
                    if 'categories_list' in self.projects_df.columns:
                        similar_by_category = similar_by_category[
                            similar_by_category['categories_list'].apply(
                                lambda x: any(cat in categories for cat in x)
                            )
                        ]
                    elif 'primary_category' in self.projects_df.columns:
                        similar_by_category = similar_by_category[
                            similar_by_category['primary_category'].apply(
                                lambda x: x in categories if isinstance(x, str) else False
                            )
                        ]
                    
                    if len(similar_by_category) >= n:
                        # Enough results, add similarity score and return
                        result = []
                        for _, similar in similar_by_category.head(n).iterrows():
                            sim_dict = similar.to_dict()
                            sim_dict['similarity_score'] = 0.75  # Medium similarity
                            result.append(sim_dict)
                        return result
                
                # If still not enough, try chain
                if chain != 'unknown':
                    similar_by_chain = self.projects_df[
                        (self.projects_df['id'] != project_id) &
                        (self.projects_df['chain'] == chain)
                    ]
                    
                    if len(similar_by_chain) >= n:
                        # Enough results, add similarity score and return
                        result = []
                        for _, similar in similar_by_chain.head(n).iterrows():
                            sim_dict = similar.to_dict()
                            sim_dict['similarity_score'] = 0.65  # Lower similarity
                            result.append(sim_dict)
                        return result
            
            # Last resort - just return popular projects
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