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
    dengan penyempurnaan strategi penggabungan
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
        
        # PERBAIKAN: Track recommendation sources for analytics
        self.recommendation_sources = {}
        
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
    
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations using filter-then-rerank approach
        """
        # Handle cold-start case
        if self.user_item_matrix is None or user_id not in self.user_item_matrix.index:
            return self._get_cold_start_recommendations(user_id, n)
        
        # Count user interactions
        user_interactions = self.user_item_matrix.loc[user_id]
        user_interaction_count = (user_interactions > 0).sum()
        
        # Get parameters from config
        interaction_threshold_low = self.params.get('interaction_threshold_low', 5)
        interaction_threshold_high = self.params.get('interaction_threshold_high', 20)
        base_fecf_weight = self.params.get('fecf_weight', 0.5)
        base_ncf_weight = self.params.get('ncf_weight', 0.5)
        diversity_factor = self.params.get('diversity_factor', 0.15)
        
        # Adjust weights based on interaction count - dynamic weighting
        if user_interaction_count < interaction_threshold_low:
            # For users with very few interactions, rely heavily on FECF
            effective_fecf_weight = 0.8
            effective_ncf_weight = 0.1
            effective_diversity_weight = 0.1
        elif user_interaction_count < interaction_threshold_high:
            # For users with moderate interactions, use a balanced approach
            # Linear interpolation between thresholds
            ratio = (user_interaction_count - interaction_threshold_low) / (interaction_threshold_high - interaction_threshold_low)
            effective_fecf_weight = base_fecf_weight + (0.8 - base_fecf_weight) * (1 - ratio)
            effective_ncf_weight = base_ncf_weight - (base_ncf_weight - 0.1) * (1 - ratio)
            effective_diversity_weight = diversity_factor
        else:
            # For users with many interactions, use configured weights
            effective_fecf_weight = base_fecf_weight
            effective_ncf_weight = base_ncf_weight
            effective_diversity_weight = diversity_factor
        
        # Log the effective weights being used
        logger.debug(f"User {user_id} ({user_interaction_count} interactions): " +
                    f"FECF={effective_fecf_weight:.2f}, NCF={effective_ncf_weight:.2f}, " +
                    f"Diversity={effective_diversity_weight:.2f}")
        
        # Step 1: Generate candidate pool from FECF
        candidate_size = n * 3
        try:
            fecf_candidates = self.fecf_model.recommend_for_user(user_id, n=candidate_size, exclude_known=exclude_known)
            if not fecf_candidates:
                return []
        except Exception as e:
            logger.warning(f"Error getting FECF recommendations: {e}")
            return []
        
        # Step 2: Calculate diversity score based on categories
        diversity_scores = {}
        if hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            # Create category mapping
            item_to_category = {}
            for _, row in self.projects_df.iterrows():
                if 'id' in row and 'primary_category' in row:
                    item_to_category[row['id']] = row['primary_category']
            
            # Count category occurrences
            category_counts = {}
            for item_id, _ in fecf_candidates:
                if item_id in item_to_category:
                    cat = item_to_category[item_id]
                    category_counts[cat] = category_counts.get(cat, 0) + 1
            
            # Calculate diversity scores - reward items from rare categories
            if category_counts:
                max_count = max(category_counts.values())
                for item_id, _ in fecf_candidates:
                    if item_id in item_to_category:
                        cat = item_to_category[item_id]
                        cat_count = category_counts.get(cat, 0)
                        # Reward items from less represented categories
                        diversity_scores[item_id] = 1.0 - (cat_count / (max_count + 1))
        
        # Step 3: Use NCF to re-rank candidates only if user has enough history
        reranked_candidates = []
        
        if user_interaction_count >= interaction_threshold_low and self.ncf_model and hasattr(self.ncf_model, 'model') and self.ncf_model.model:
            try:
                # Get NCF scores for candidates
                ncf_scores = {}
                for item_id, _ in fecf_candidates:
                    # Get prediction directly from NCF
                    score = self.ncf_model.predict(user_id, item_id)
                    if score > 0:  # Only consider positive scores
                        ncf_scores[item_id] = score
                
                # If we got valid NCF scores, use them for re-ranking
                if ncf_scores:
                    # Combine FECF scores, NCF scores and diversity
                    for item_id, fecf_score in fecf_candidates:
                        # Use dynamic weights from above
                        final_score = effective_fecf_weight * fecf_score
                        
                        # Add NCF contribution if available
                        if item_id in ncf_scores:
                            final_score += effective_ncf_weight * ncf_scores[item_id]
                        
                        # Add diversity bonus if available
                        if item_id in diversity_scores:
                            final_score += effective_diversity_weight * diversity_scores[item_id]
                        
                        reranked_candidates.append((item_id, final_score))
            except Exception as e:
                logger.warning(f"Error in NCF re-ranking: {e}")
                # Fallback to original candidates with diversity
                reranked_candidates = [(item_id, score + effective_diversity_weight * diversity_scores.get(item_id, 0)) 
                                for item_id, score in fecf_candidates]
        else:
            # No NCF re-ranking, just use FECF with diversity bonus
            reranked_candidates = [(item_id, score + effective_diversity_weight * diversity_scores.get(item_id, 0)) 
                            for item_id, score in fecf_candidates]
        
        # Sort by final score and return top n
        reranked_candidates.sort(key=lambda x: x[1], reverse=True)
        return reranked_candidates[:n]
    
    def _get_cold_start_recommendations_by_interest(self, user_id: str, n: int = 10) -> List[Tuple[str, float]]:
        """
        Generate recommendations for cold-start-like exploration based on user's general interests
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # Get user's top categories from existing interactions
        user_categories = []
        
        if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
            try:
                if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                    # Get user's interacted items
                    user_interactions = self.user_item_matrix.loc[user_id]
                    interacted_items = user_interactions[user_interactions > 0].index.tolist()
                    
                    # Map items to categories
                    item_to_category = dict(zip(self.projects_df['id'], self.projects_df['primary_category']))
                    
                    # Count categories
                    category_counts = {}
                    for item in interacted_items:
                        category = item_to_category.get(item)
                        if category:
                            category_counts[category] = category_counts.get(category, 0) + 1
                    
                    # Get top categories
                    if category_counts:
                        sorted_categories = sorted(category_counts.items(), key=lambda x: x[1], reverse=True)
                        user_categories = [cat for cat, _ in sorted_categories[:3]]
            except Exception as e:
                logger.warning(f"Error inferring user categories: {e}")
        
        # Get cold-start-like recommendations using inferred interests
        if user_categories:
            # PERBAIKAN: Get recommendations for both similar and contrasting categories
            # First, get similar category recommendations
            similar_recs = self.fecf_model.get_cold_start_recommendations(
                user_interests=user_categories,
                n=n
            )
            similar_tuples = [(rec['id'], rec['recommendation_score']) for rec in similar_recs]
            
            # Next, find some contrasting categories for diversity
            all_categories = set()
            if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                all_categories = set(self.projects_df['primary_category'].dropna().unique())
            
            # Find categories dissimilar to user's interests
            contrasting_categories = []
            for cat in all_categories:
                if cat not in user_categories and len(contrasting_categories) < 2:  # Get up to 2 contrasting categories
                    contrasting_categories.append(cat)
            
            # Get recommendations from contrasting categories
            contrasting_recs = []
            if contrasting_categories:
                contrast_results = self.fecf_model.get_cold_start_recommendations(
                    user_interests=contrasting_categories,
                    n=n//2
                )
                contrasting_recs = [(rec['id'], rec['recommendation_score'] * 0.8) for rec in contrast_results]  # Slightly lower scores
            
            # Combine and sort
            combined_recs = similar_tuples + contrasting_recs
            combined_recs.sort(key=lambda x: x[1], reverse=True)
            
            # Check for known items to exclude
            known_items = set()
            if self.user_item_matrix is not None and user_id in self.user_item_matrix.index:
                user_interactions = self.user_item_matrix.loc[user_id]
                known_items = set(user_interactions[user_interactions > 0].index)
            
            # Filter and return
            filtered_recs = [(item, score) for item, score in combined_recs if item not in known_items]
            return filtered_recs[:n]
        else:
            # Fallback to standard cold-start if no categories can be inferred
            cold_start_recs = self.fecf_model.get_cold_start_recommendations(n=n)
            return [(rec['id'], rec['recommendation_score']) for rec in cold_start_recs]
    
    def _get_cold_start_recommendations(self, user_id: str, n: int = 10) -> List[Tuple[str, float]]:
        """
        Generate recommendations for cold-start users dengan peningkatan keragaman kategori
        """
        # Dapatkan rekomendasi dari FECF sebagai baseline
        fecf_recs = self.fecf_model.get_cold_start_recommendations(n=n*2)
        
        # Convert to (project_id, score) format untuk konsistensi
        fecf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                    for rec in fecf_recs if 'id' in rec]
        
        # Dapatkan rekomendasi dari model NCF (jika tersedia)
        try:
            ncf_recs = self.ncf_model.get_popular_projects(n=n*2)
            ncf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                        for rec in ncf_recs if 'id' in rec]
        except Exception as e:
            logger.warning(f"Error getting NCF recommendations for cold-start: {str(e)}")
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
        
        # Dapatkan informasi kategori
        item_categories = {}
        category_popularity = {}
        
        try:
            if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                # Count overall category popularity
                category_popularity = self.projects_df['primary_category'].value_counts(normalize=True).to_dict()
                
                # Map item to category
                for _, row in self.projects_df.iterrows():
                    if 'id' in row and 'primary_category' in row:
                        item_categories[row['id']] = row['primary_category']
        except Exception as e:
            logger.warning(f"Error getting category information: {str(e)}")
        
        # Get parameters from config
        cold_start_fecf_weight = self.params.get('cold_start_fecf_weight', 0.9)
        cold_start_ncf_weight = 1.0 - cold_start_fecf_weight  # Derived parameter
        diversity_factor = self.params.get('diversity_factor', 0.15)
        
        # Hitung skor gabungan dengan bobot hybrid
        weighted_scores = []
        
        for item_id, scores in all_recs.items():
            # Hitung skor gabungan dengan parameter dari config
            weighted_score = (scores['fecf_score'] * cold_start_fecf_weight + 
                            scores['ncf_score'] * cold_start_ncf_weight)
            
            # Add diversity boost for rare categories
            if item_categories and item_id in item_categories:
                category = item_categories[item_id]
                cat_popularity = category_popularity.get(category, 0.01)
                
                # Inverse popularity boost - more rare categories get larger boost
                rarity_boost = max(0, diversity_factor * (1 - cat_popularity / max(0.01, max(category_popularity.values()))))
                weighted_score += rarity_boost
            
            weighted_scores.append((item_id, weighted_score))
        
        # Sort by weighted score
        weighted_scores.sort(key=lambda x: x[1], reverse=True)
        
        # Implementasi diversifikasi kategori
        if item_categories:
            # Get top candidates
            candidates = weighted_scores[:min(len(weighted_scores), n*3)]
            
            # First, select some top items unconditionally
            top_count = max(n // 4, 2)  # 25% of recommendations by pure score
            result = candidates[:top_count]
            
            # Track selected categories
            selected_categories = {}
            for item_id, _ in result:
                if item_id in item_categories:
                    category = item_categories[item_id]
                    selected_categories[category] = selected_categories.get(category, 0) + 1
            
            # Maximum items allowed per category (30% of total)
            max_per_category = max(2, int(n * 0.3))
            
            # Remaining candidates
            remaining = candidates[top_count:]
            
            # Select remaining items with diversity boost
            while len(result) < n and remaining:
                best_idx = -1
                best_adjusted_score = -float('inf')
                
                for idx, (item_id, score) in enumerate(remaining):
                    if item_id in item_categories:
                        category = item_categories[item_id]
                        current_count = selected_categories.get(category, 0)
                        
                        # Calculate diversity adjustment
                        if current_count >= max_per_category:
                            # Heavy penalty if we've reached the max for this category
                            diversity_adjustment = -0.5
                        elif current_count == 0:
                            # Strong boost for new categories
                            diversity_adjustment = 0.3
                        else:
                            # Small adjustment based on how close we are to ideal count
                            diversity_adjustment = 0.1 * (1 - current_count / max(2, n // len(category_popularity)))
                        
                        adjusted_score = score + diversity_adjustment
                    else:
                        adjusted_score = score
                    
                    if adjusted_score > best_adjusted_score:
                        best_idx = idx
                        best_adjusted_score = adjusted_score
                
                if best_idx >= 0:
                    item_id, score = remaining.pop(best_idx)
                    result.append((item_id, score))
                    
                    # Update category tracking
                    if item_id in item_categories:
                        category = item_categories[item_id]
                        selected_categories[category] = selected_categories.get(category, 0) + 1
                else:
                    break
            
            # Sort by score for final ranking
            result.sort(key=lambda x: x[1], reverse=True)
            return result[:n]
        
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