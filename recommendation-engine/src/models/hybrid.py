"""
Hybrid Recommendation System yang mengkombinasikan 
Feature-Enhanced CF dengan Neural CF
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
import time
import pickle
from datetime import datetime
from pathlib import Path

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import HYBRID_PARAMS, MODELS_DIR, PROCESSED_DIR

# Import model components
# from src.models.fecf import FeatureEnhancedCF
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
    Hybrid Recommender yang menggabungkan Feature-Enhanced CF dan Neural CF
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
        """
        Initialize Hybrid Recommender
        
        Args:
            params: Model parameters (overwrites defaults from config)
        """
        # Model parameters
        self.params = params or HYBRID_PARAMS
        
        # Initialize component models
        self.fecf_model = None
        self.ncf_model = None
        
        # Data
        self.projects_df = None
        self.interactions_df = None
        self.user_item_matrix = None
        
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
                
                logger.info("Data loaded successfully for Hybrid Recommender")
                return True
            else:
                logger.error("Failed to load data for one or more component models")
                return False
                
        except Exception as e:
            logger.error(f"Error loading data for Hybrid Recommender: {str(e)}")
            return False
    
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
        logger.info("Training Neural CF component")
        ncf_metrics = self.ncf_model.train(save_model=save_model)
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
            'timestamp': datetime.now().isoformat()
        }
        
        with open(filepath, 'wb') as f:
            pickle.dump(model_state, f)
            
        logger.info(f"Hybrid model saved to {filepath}")
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
                logger.info(f"Loading hybrid model from {hybrid_filepath}")
                with open(hybrid_filepath, 'rb') as f:
                    model_state = pickle.load(f)
                    
                self.params = model_state.get('params', self.params)
                logger.info(f"Hybrid model weights loaded from {hybrid_filepath}")
                
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
            from src.models.alt_fecf import FeatureEnhancedCF
            self.fecf_model = FeatureEnhancedCF()
            
        if self.ncf_model is None:
            from src.models.ncf import NCFRecommender
            self.ncf_model = NCFRecommender()
        
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
    
    def recommend_for_user(self, user_id: str, n: int = 10, 
                     exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user using hybrid approach with adaptive weighting,
        enhanced category diversity, and score normalization
        
        Args:
            user_id: User ID
            n: Number of recommendations
            exclude_known: Whether to exclude already interacted items
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # Check if user exists in the data
        known_user = (self.user_item_matrix is not None and 
                    user_id in self.user_item_matrix.index)
        
        # Handle cold-start case
        if not known_user:
            logger.info(f"User {user_id} is a cold-start user")
            return self._get_cold_start_recommendations(user_id, n)
        
        # Count user interactions to determine user maturity
        user_interaction_count = 0
        if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
            user_interactions = self.user_item_matrix.loc[user_id]
            user_interaction_count = (user_interactions > 0).sum()
        
        # Adaptive weighting based on user interaction count
        # New users (few interactions) rely more on content-based (FECF)
        # Mature users (many interactions) rely more on collaborative filtering (NCF)
        interaction_threshold_low = self.params.get('interaction_threshold_low', 5)
        interaction_threshold_high = self.params.get('interaction_threshold_high', 20)
        
        if user_interaction_count <= interaction_threshold_low:
            # New user - rely more on FECF
            fecf_weight = 0.8
            ncf_weight = 0.2
            logger.debug(f"User {user_id} has few interactions ({user_interaction_count}), using FECF-heavy weights")
        elif user_interaction_count >= interaction_threshold_high:
            # Mature user - rely more on NCF
            fecf_weight = 0.3
            ncf_weight = 0.7
            logger.debug(f"User {user_id} is a mature user ({user_interaction_count} interactions), using NCF-heavy weights")
        else:
            # In-between user - gradual transition
            # Linear interpolation between low and high thresholds
            maturity_factor = (user_interaction_count - interaction_threshold_low) / (interaction_threshold_high - interaction_threshold_low)
            fecf_weight = 0.8 - (0.5 * maturity_factor)  # 0.8 -> 0.3
            ncf_weight = 0.2 + (0.5 * maturity_factor)   # 0.2 -> 0.7
            logger.debug(f"User {user_id} has {user_interaction_count} interactions, using interpolated weights: FECF={fecf_weight:.2f}, NCF={ncf_weight:.2f}")
        
        # Get recommendations from component models
        fecf_recs = self.fecf_model.recommend_for_user(user_id, n=max(n*2, 50), exclude_known=exclude_known)
        ncf_recs = self.ncf_model.recommend_for_user(user_id, n=max(n*2, 50), exclude_known=exclude_known)
        
        # Convert to dictionaries for easier handling
        fecf_scores = {item_id: score for item_id, score in fecf_recs}
        ncf_scores = {item_id: score for item_id, score in ncf_recs}
        
        # Get all unique items
        all_items = set(fecf_scores.keys()) | set(ncf_scores.keys())
        
        # Find min and max scores for each model for normalization
        fecf_min = min(fecf_scores.values()) if fecf_scores else 0
        fecf_max = max(fecf_scores.values()) if fecf_scores else 1
        fecf_range = max(0.001, fecf_max - fecf_min)  # Avoid division by zero
        
        ncf_min = min(ncf_scores.values()) if ncf_scores else 0
        ncf_max = max(ncf_scores.values()) if ncf_scores else 1
        ncf_range = max(0.001, ncf_max - ncf_min)  # Avoid division by zero
        
        # Calculate weighted scores with normalization
        weighted_scores = []
        for item_id in all_items:
            # Get scores, defaulting to min score if missing
            fecf_raw_score = fecf_scores.get(item_id, fecf_min)
            ncf_raw_score = ncf_scores.get(item_id, ncf_min)
            
            # Normalize scores to 0-1 range
            fecf_normalized = (fecf_raw_score - fecf_min) / fecf_range
            ncf_normalized = (ncf_raw_score - ncf_min) / ncf_range
            
            # Apply weighted combination of normalized scores
            weighted_score = (fecf_normalized * fecf_weight) + (ncf_normalized * ncf_weight)
            
            # Diversity boost - significantly boost items from the model with lower weight
            # PERBAIKAN: Tingkatkan diversity_factor
            diversity_factor = self.params.get('diversity_factor', 0.2)  # Meningkat dari 0.1 ke 0.2
            if fecf_weight < ncf_weight and fecf_normalized > 0.8:
                # Boost high-scoring FECF items when NCF dominates
                diversity_boost = diversity_factor * (1.0 - ncf_weight) * fecf_normalized
                weighted_score += diversity_boost
            elif ncf_weight < fecf_weight and ncf_normalized > 0.8:
                # Boost high-scoring NCF items when FECF dominates
                diversity_boost = diversity_factor * (1.0 - fecf_weight) * ncf_normalized
                weighted_score += diversity_boost
            
            weighted_scores.append((item_id, weighted_score))
        
        # Sort by weighted score
        weighted_scores.sort(key=lambda x: x[1], reverse=True)
        
        # PERBAIKAN: Meningkatkan keragaman kategori
        # Get category information for diversity enhancement
        item_to_category = {}
        try:
            if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                item_to_category = dict(zip(self.projects_df['id'], self.projects_df['primary_category']))
        except Exception as e:
            logger.warning(f"Error getting category information: {str(e)}")
        
        # Apply category diversity enhancement
        if item_to_category:
            # Take more candidates than needed to ensure we have enough diversity options
            candidates = weighted_scores[:min(len(weighted_scores), n*3)]
            
            # First, select a portion of top items by raw score
            top_portion = max(2, n // 4)  # At least 2 items or 25% of total
            final_selections = candidates[:top_portion]
            
            # Track categories we've already included
            selected_categories = {
                item_to_category.get(item_id, 'unknown')
                for item_id, _ in final_selections
            }
            
            # Find most frequent category in top selections
            category_counts = {}
            for item_id, _ in candidates:
                cat = item_to_category.get(item_id, 'unknown')
                category_counts[cat] = category_counts.get(cat, 0) + 1
            
            # Set a maximum number per category based on recommendation size
            max_per_category = max(2, n // 3)  # Maximum 33% from any single category
            category_used_counts = {cat: 0 for cat in category_counts.keys()}
            
            # Count categories in our initial selection
            for item_id, _ in final_selections:
                cat = item_to_category.get(item_id, 'unknown')
                category_used_counts[cat] = category_used_counts.get(cat, 0) + 1
            
            # Process remaining candidates with diversity boost
            remaining_candidates = candidates[top_portion:]
            
            while len(final_selections) < n and remaining_candidates:
                best_item_idx = -1
                best_item_score = -1
                best_item_adjusted_score = -1
                
                for idx, (item_id, score) in enumerate(remaining_candidates):
                    category = item_to_category.get(item_id, 'unknown')
                    category_count = category_used_counts.get(category, 0)
                    
                    # Calculate diversity boost
                    if category_count >= max_per_category:
                        # Penalize if we already have too many from this category
                        diversity_boost = -0.2
                    elif category not in selected_categories:
                        # Big boost for new categories
                        diversity_boost = 0.3
                    else:
                        # Small boost for less represented categories
                        diversity_boost = 0.1 * (1 - (category_count / max_per_category))
                    
                    adjusted_score = score + diversity_boost
                    
                    if adjusted_score > best_item_adjusted_score:
                        best_item_idx = idx
                        best_item_score = score
                        best_item_adjusted_score = adjusted_score
                
                if best_item_idx >= 0:
                    item_id, score = remaining_candidates.pop(best_item_idx)
                    final_selections.append((item_id, score))
                    
                    # Update tracking
                    category = item_to_category.get(item_id, 'unknown')
                    selected_categories.add(category)
                    category_used_counts[category] = category_used_counts.get(category, 0) + 1
                else:
                    break
            
            # Ensure we have enough recommendations by adding any remaining by score
            if len(final_selections) < n:
                for item_id, score in remaining_candidates:
                    if item_id not in [i[0] for i in final_selections]:
                        final_selections.append((item_id, score))
                        if len(final_selections) >= n:
                            break
            
            # Resort by score for final ordering
            final_selections.sort(key=lambda x: x[1], reverse=True)
            
            # Return the diversified recommendations
            return final_selections[:n]
        
        # If no category information, return by score only
        return weighted_scores[:n]
    
    def _get_cold_start_recommendations(self, user_id: str, n: int = 10) -> List[Tuple[str, float]]:
        """
        Generate recommendations for cold-start users dengan peningkatan keragaman kategori
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # Ekstrak user interests dari interaksi pengguna lain jika memungkinkan
        # Ini adalah pendekatan berbeda dari yang ada, yang hanya mengandalkan FECF
        user_interests = None
        
        # Pertama, dapatkan rekomendasi dari FECF sebagai baseline
        fecf_recs = self.fecf_model.get_cold_start_recommendations(n=n*2)
        
        # Convert to (project_id, score) format untuk konsistensi
        fecf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                    for rec in fecf_recs if 'id' in rec]
        
        # Dapatkan juga rekomendasi dari model NCF (jika tersedia)
        try:
            ncf_recs = self.ncf_model.get_popular_projects(n=n*2)
            ncf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                        for rec in ncf_recs if 'id' in rec]
        except:
            ncf_scores = []
        
        # Gabungkan rekomendasi dari kedua model
        all_recs = {}
        
        # Tambahkan skor dari FECF
        for item_id, score in fecf_scores:
            all_recs[item_id] = {'fecf_score': score, 'ncf_score': 0.0}
        
        # Tambahkan atau perbarui skor dari NCF
        for item_id, score in ncf_scores:
            if item_id in all_recs:
                all_recs[item_id]['ncf_score'] = score
            else:
                all_recs[item_id] = {'fecf_score': 0.0, 'ncf_score': score}
        
        # PERBAIKAN: Implementasi diversifikasi kategori
        # Dapatkan informasi kategori
        item_categories = {}
        try:
            if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                for _, row in self.projects_df.iterrows():
                    if 'id' in row and 'primary_category' in row:
                        item_categories[row['id']] = row['primary_category']
        except Exception as e:
            logger.warning(f"Error getting category information: {str(e)}")
        
        # Hitung skor gabungan dengan bobot hybrid
        weighted_scores = []
        
        for item_id, scores in all_recs.items():
            # Gunakan bobot khusus cold-start dari konfigurasi
            cold_start_fecf_weight = self.params.get('cold_start_fecf_weight', 0.9)
            # Nilai NCF lebih rendah untuk cold-start
            cold_start_ncf_weight = 1.0 - cold_start_fecf_weight
            
            # Hitung skor gabungan
            weighted_score = (scores['fecf_score'] * cold_start_fecf_weight + 
                            scores['ncf_score'] * cold_start_ncf_weight)
            
            weighted_scores.append((item_id, weighted_score))
        
        # Sort by weighted score
        weighted_scores.sort(key=lambda x: x[1], reverse=True)
        
        # PERBAIKAN: Implementasi booster kategori untuk diversifikasi
        if item_categories:
            # Ambil kandidat lebih banyak untuk memastikan diversitas
            candidates = weighted_scores[:min(len(weighted_scores), n*3)]
            
            # Identifikasi kategori dari kandidat teratas
            top_categories = {}
            for item_id, _ in candidates[:n]:
                if item_id in item_categories:
                    category = item_categories[item_id]
                    top_categories[category] = top_categories.get(category, 0) + 1
            
            # Tetapkan batas maksimum per kategori (maksimal 33% dari total atau minimal 2)
            max_per_category = max(2, n // 3)
            
            # Pilih sebagian kecil item teratas berdasarkan skor mentah
            top_k = max(1, n // 4)  # 25% teratas berdasarkan skor
            final_selections = candidates[:top_k]
            
            # Lacak kategori yang sudah dipilih
            selected_categories = {}
            for item_id, _ in final_selections:
                if item_id in item_categories:
                    category = item_categories[item_id]
                    selected_categories[category] = selected_categories.get(category, 0) + 1
            
            # Proses kandidat yang tersisa dengan booster diversitas
            remaining_candidates = candidates[top_k:]
            
            while len(final_selections) < n and remaining_candidates:
                next_item = None
                best_score = -1
                
                for idx, (item_id, score) in enumerate(remaining_candidates):
                    category = item_categories.get(item_id, 'unknown')
                    category_count = selected_categories.get(category, 0)
                    
                    # Hitung booster diversitas
                    if category_count >= max_per_category:
                        # Penalti jika sudah terlalu banyak dari kategori ini
                        diversity_boost = -0.2  # Penalti signifikan
                    elif category not in selected_categories:
                        # Booster besar untuk kategori baru
                        diversity_boost = 0.3
                    else:
                        # Booster kecil untuk kategori yang kurang terwakili
                        diversity_boost = 0.1 * (1 - (category_count / max_per_category))
                    
                    adjusted_score = score + diversity_boost
                    
                    if adjusted_score > best_score:
                        next_item = (idx, item_id, score, category)
                        best_score = adjusted_score
                
                if next_item:
                    idx, item_id, original_score, category = next_item
                    final_selections.append((item_id, original_score))
                    
                    # Perbarui pelacakan
                    selected_categories[category] = selected_categories.get(category, 0) + 1
                    remaining_candidates.pop(idx)
                else:
                    break
            
            # Pastikan kita memiliki cukup rekomendasi dengan menambahkan yang tersisa berdasarkan skor
            if len(final_selections) < n:
                for item_id, score in remaining_candidates:
                    if len(final_selections) >= n:
                        break
                    if item_id not in [i[0] for i in final_selections]:
                        final_selections.append((item_id, score))
            
            # Urutkan kembali berdasarkan skor
            final_selections.sort(key=lambda x: x[1], reverse=True)
            
            return final_selections[:n]
        
        # Jika tidak ada informasi kategori, kembalikan berdasarkan skor saja
        return weighted_scores[:n]
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Generate project recommendations with full details and model metadata
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of project dictionaries with recommendation scores and model sources
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
        
        # Get FECF and NCF recommendations separately for analysis
        fecf_recs = self.fecf_model.recommend_for_user(user_id, n=n*2)
        fecf_dict = {item_id: score for item_id, score in fecf_recs}
        
        ncf_recs = self.ncf_model.recommend_for_user(user_id, n=n*2)
        ncf_dict = {item_id: score for item_id, score in ncf_recs}
        
        # Get recommendations as (project_id, score) tuples from the hybrid model
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
                
                # Add model source information
                fecf_score = fecf_dict.get(project_id, 0)
                ncf_score = ncf_dict.get(project_id, 0)
                
                # Determine primary influence based on component scores
                fecf_influence = 0
                ncf_influence = 0
                
                if fecf_score > 0 or ncf_score > 0:
                    total = fecf_score + ncf_score
                    if total > 0:
                        fecf_influence = fecf_score / total
                        ncf_influence = ncf_score / total
                
                # Add model contribution information
                project_dict['model_metadata'] = {
                    'fecf_score': float(fecf_score),
                    'ncf_score': float(ncf_score),
                    'fecf_influence': float(fecf_influence),
                    'ncf_influence': float(ncf_influence),
                    'primary_source': 'fecf' if fecf_influence >= ncf_influence else 'ncf'
                }
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        # Add overall recommendation metadata
        recommendation_metadata = {
            'user_id': user_id,
            'is_cold_start': is_cold_start,
            'interaction_count': user_interaction_count,
            'model_weights': {
                'fecf': self.params.get('fecf_weight', 0.5),
                'ncf': self.params.get('ncf_weight', 0.5)
            },
            'timestamp': datetime.now().isoformat()
        }
        
        # Add metadata to the first recommendation if any
        if detailed_recommendations:
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
        return self.fecf_model.get_trending_projects(n)
    
    def get_popular_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get popular projects
        
        Args:
            n: Number of popular projects to return
            
        Returns:
            list: List of popular project dictionaries
        """
        # Delegate to FECF
        return self.fecf_model.get_popular_projects(n)
    
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
        return self.fecf_model.get_similar_projects(project_id, n)
    
    def get_trading_signals(self, project_id: str) -> Dict[str, Any]:
        """
        Get trading signals for a project
        
        Args:
            project_id: Project ID
            
        Returns:
            dict: Trading signal information
        """
        # This would be implemented with the technical analysis module
        # For now, return a placeholder
        return {
            "project_id": project_id,
            "action": "hold",
            "confidence": 0.5,
            "message": "Trading signals not yet implemented in hybrid model"
        }


if __name__ == "__main__":
    # Testing the module
    hybrid = HybridRecommender()
    
    # Load data
    if hybrid.load_data():
        # Train model (skip for testing to save time)
        print("Skipping training for testing purposes")
        
        # Test recommendations
        if hybrid.user_item_matrix is not None and not hybrid.user_item_matrix.empty:
            test_user = hybrid.user_item_matrix.index[0]
            print(f"\nHybrid recommendations for user {test_user}:")
            recs = hybrid.recommend_projects(test_user, n=5)
            
            for i, rec in enumerate(recs, 1):
                print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
                
        # Test popular projects
        print("\nPopular projects:")
        popular = hybrid.get_popular_projects(n=5)
        
        for i, proj in enumerate(popular, 1):
            print(f"{i}. {proj.get('name', proj.get('id'))} - Score: {proj.get('popularity_score', 0):.4f}")
    else:
        print("Failed to load data for Hybrid Recommender")