"""
Hybrid Recommendation System yang mengkombinasikan 
Feature-Enhanced CF dengan Neural CF (Dioptimalkan untuk cryptocurrency)
"""

import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
import time
import pickle
import random
from datetime import datetime
from pathlib import Path

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import HYBRID_PARAMS, MODELS_DIR, PROCESSED_DIR, CRYPTO_DOMAIN_WEIGHTS

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
    dengan optimasi khusus untuk domain cryptocurrency
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
        
        # Track recommendation sources for analytics
        self.recommendation_sources = {}
        
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
        Generate recommendations using improved crypto-specific approach
        with dynamic weighting based on user behavior and market trends
        
        Args:
            user_id: User ID
            n: Number of recommendations
            exclude_known: Whether to exclude already interacted items
            
        Returns:
            list: List of (project_id, score) tuples
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
        
        # IMPROVED: Analyze if user follows popular/trending items
        follows_trends = False
        try:
            if hasattr(self, 'projects_df') and 'popularity_score' in self.projects_df.columns:
                # Get interacted items
                interacted_items = user_interactions[user_interactions > 0].index.tolist()
                # Get popularity scores for interacted items
                popularity_scores = []
                
                for item in interacted_items:
                    item_df = self.projects_df[self.projects_df['id'] == item]
                    if not item_df.empty and 'popularity_score' in item_df.columns:
                        popularity_scores.append(item_df['popularity_score'].values[0])
                        
                # If user mainly interacts with popular items, they follow trends
                if popularity_scores and np.mean(popularity_scores) > 70:  # threshold for "popular"
                    follows_trends = True
                    logger.debug(f"User {user_id} follows popular trends")
        except Exception as e:
            logger.debug(f"Error analyzing user trends: {e}")
        
        # IMPROVED: Dynamic weighting with crypto-specific adaptations
        if user_interaction_count < interaction_threshold_low:
            # For users with very few interactions, rely heavily on FECF and trending
            effective_fecf_weight = 0.85  # Increased from 0.8
            effective_ncf_weight = 0.05  # Reduced from 0.1
            effective_diversity_weight = 0.10
        elif user_interaction_count < interaction_threshold_high:
            # Linear interpolation between thresholds
            ratio = (user_interaction_count - interaction_threshold_low) / (interaction_threshold_high - interaction_threshold_low)
            
            # If user follows trends, give more weight to FECF (which captures popularity better)
            if follows_trends:
                # More weight to FECF for trend followers
                effective_fecf_weight = base_fecf_weight + (0.85 - base_fecf_weight) * (1 - ratio) * 0.8
                effective_ncf_weight = base_ncf_weight - (base_ncf_weight - 0.05) * (1 - ratio) * 0.8
                # Lower diversity for trend followers who want what's popular
                effective_diversity_weight = diversity_factor * 0.7
            else:
                # More balanced approach for non-trend followers
                effective_fecf_weight = base_fecf_weight + (0.85 - base_fecf_weight) * (1 - ratio)
                effective_ncf_weight = base_ncf_weight - (base_ncf_weight - 0.05) * (1 - ratio)
                # Higher diversity for explorers
                effective_diversity_weight = diversity_factor * (1 + ratio * 0.5)
        else:
            # For users with many interactions, use personalized weights
            if follows_trends:
                # Trend followers get more weight on FECF
                effective_fecf_weight = base_fecf_weight + 0.15
                effective_ncf_weight = base_ncf_weight - 0.15
                effective_diversity_weight = diversity_factor * 0.8
            else:
                # Non-trend followers get the normal configured weights
                effective_fecf_weight = base_fecf_weight
                effective_ncf_weight = base_ncf_weight
                effective_diversity_weight = diversity_factor * 1.1
        
        # Apply domain-specific trend importance modifier
        trend_importance = self.crypto_weights.get("trend_importance", 0.7)
        if trend_importance > 0.6:  # If trend is very important in this domain
            effective_fecf_weight = effective_fecf_weight * 1.1  # Boost FECF more
            effective_ncf_weight = effective_ncf_weight * 0.9  # Reduce NCF
        
        # Log the effective weights being used
        logger.debug(f"User {user_id} ({user_interaction_count} interactions): " +
                    f"FECF={effective_fecf_weight:.2f}, NCF={effective_ncf_weight:.2f}, " +
                    f"Diversity={effective_diversity_weight:.2f}")
        
        # Step 1: Generate larger candidate pool from FECF
        candidate_size = n * 4  # Increased from n*3 for more options
        try:
            fecf_candidates = self.fecf_model.recommend_for_user(user_id, n=candidate_size, exclude_known=exclude_known)
            if not fecf_candidates:
                return []
        except Exception as e:
            logger.warning(f"Error getting FECF recommendations: {e}")
            return []
        
        # Step 2: Enhanced diversity calculation based on multiple factors
        diversity_scores = {}
        if hasattr(self, 'projects_df'):
            # Create mapping dictionaries for various attributes
            item_to_category = {}
            item_to_chain = {}
            item_to_market_cap = {}
            item_to_trend = {}
            
            for _, row in self.projects_df.iterrows():
                if 'id' in row:
                    if 'primary_category' in row:
                        item_to_category[row['id']] = row['primary_category']
                    if 'chain' in row:
                        item_to_chain[row['id']] = row['chain']
                    if 'market_cap' in row:
                        item_to_market_cap[row['id']] = row['market_cap']
                    if 'trend_score' in row:
                        item_to_trend[row['id']] = row['trend_score']
            
            # Count category and chain occurrences in candidates
            category_counts = {}
            chain_counts = {}
            
            for item_id, _ in fecf_candidates:
                if item_id in item_to_category:
                    cat = item_to_category[item_id]
                    category_counts[cat] = category_counts.get(cat, 0) + 1
                
                if item_id in item_to_chain:
                    chain = item_to_chain[item_id]
                    chain_counts[chain] = chain_counts.get(chain, 0) + 1
            
            # Calculate diversity scores based on all factors
            category_importance = self.crypto_weights.get("category_correlation", 0.6)
            chain_importance = self.crypto_weights.get("chain_importance", 0.3)
            market_cap_influence = self.crypto_weights.get("market_cap_influence", 0.4)
            
            if category_counts and chain_counts:
                max_cat_count = max(category_counts.values()) if category_counts else 1
                max_chain_count = max(chain_counts.values()) if chain_counts else 1
                
                for item_id, _ in fecf_candidates:
                    # Start with base diversity score
                    diversity_score = 0.5
                    
                    # Factor 1: Category rarity (weighted by domain importance)
                    if item_id in item_to_category:
                        cat = item_to_category[item_id]
                        cat_count = category_counts.get(cat, 0)
                        cat_diversity = 1.0 - (cat_count / (max_cat_count + 1))
                        diversity_score += category_importance * 0.4 * cat_diversity
                    
                    # Factor 2: Chain rarity (weighted by domain importance)
                    if item_id in item_to_chain:
                        chain = item_to_chain[item_id]
                        chain_count = chain_counts.get(chain, 0)
                        chain_diversity = 1.0 - (chain_count / (max_chain_count + 1))
                        diversity_score += chain_importance * 0.5 * chain_diversity
                    
                    # Factor 3: Market cap diversity (prefer some smaller caps)
                    if item_id in item_to_market_cap and sum(item_to_market_cap.values()) > 0:
                        market_cap = item_to_market_cap[item_id]
                        market_cap_percentile = market_cap / max(item_to_market_cap.values())
                        
                        # For crypto, we want a mix but more bias towards higher cap items for safety
                        if market_cap_percentile > 0.7:  # High market cap
                            diversity_score += market_cap_influence * 0.1
                        elif 0.3 <= market_cap_percentile <= 0.7:  # Mid market cap
                            diversity_score += market_cap_influence * 0.2
                        else:  # Low market cap (smaller bonus as higher risk)
                            diversity_score += market_cap_influence * 0.05
                    
                    # Factor 4: Trend bonus for highly trending items
                    if item_id in item_to_trend:
                        trend_score = item_to_trend[item_id]
                        if trend_score > 75:  # Highly trending
                            diversity_score += 0.15  # Significant boost for trending items
                        elif trend_score > 60:  # Moderately trending
                            diversity_score += 0.08
                    
                    diversity_scores[item_id] = diversity_score
        
        # Step 3: Use NCF to re-rank candidates with improved adaptation
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
                    # IMPROVED: Multi-factor scoring with domain-specific weights
                    for item_id, fecf_score in fecf_candidates:
                        # Initialize with FECF score (weighted)
                        final_score = effective_fecf_weight * fecf_score
                        
                        # Add NCF contribution if available
                        if item_id in ncf_scores:
                            ncf_contribution = effective_ncf_weight * ncf_scores[item_id]
                            # Amplify strong signals, dampen weak signals
                            if ncf_scores[item_id] > 0.8:  # Strong signal
                                ncf_contribution *= 1.3
                            elif ncf_scores[item_id] < 0.3:  # Weak signal
                                ncf_contribution *= 0.7
                            final_score += ncf_contribution
                        
                        # Add diversity bonus if available
                        if item_id in diversity_scores:
                            final_score += effective_diversity_weight * diversity_scores[item_id]
                        
                        # IMPROVED: Add trend bonus for cryptocurrency
                        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
                            try:
                                project_row = self.projects_df[self.projects_df['id'] == item_id]
                                if not project_row.empty:
                                    trend_score = project_row['trend_score'].values[0]
                                    # Cryptocurrency is highly trend-sensitive
                                    if trend_score > 80:  # Extremely trending
                                        final_score += 0.15
                                    elif trend_score > 65:  # Highly trending
                                        final_score += 0.08
                                    elif trend_score > 50:  # Moderately trending
                                        final_score += 0.03
                            except Exception as e:
                                logger.debug(f"Error retrieving trend score: {e}")
                        
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
        
        # Sort by final score
        reranked_candidates.sort(key=lambda x: x[1], reverse=True)
        
        # IMPROVED: Ensure category and chain diversity in final selection
        if hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            # Create mapping of item to category and chain
            item_categories = {}
            item_chains = {}
            
            for _, row in self.projects_df.iterrows():
                if 'id' in row:
                    if 'primary_category' in row:
                        item_categories[row['id']] = row['primary_category']
                    if 'chain' in row:
                        item_chains[row['id']] = row['chain']
            
            # First, select some top items unconditionally
            top_count = max(n // 5, 1)  # 20% of recommendations by pure score
            result = reranked_candidates[:top_count]
            
            # Track selected categories and chains
            selected_categories = {}
            selected_chains = {}
            
            for item_id, _ in result:
                if item_id in item_categories:
                    category = item_categories[item_id]
                    selected_categories[category] = selected_categories.get(category, 0) + 1
                if item_id in item_chains:
                    chain = item_chains[item_id]
                    selected_chains[chain] = selected_chains.get(chain, 0) + 1
            
            # Maximum items allowed per category (30% of total)
            max_per_category = max(2, int(n * 0.3))
            # Maximum items allowed per chain (40% of total)
            max_per_chain = max(3, int(n * 0.4))
            
            # Remaining candidates
            remaining = reranked_candidates[top_count:]
            
            # Select remaining items with diversity considerations
            while len(result) < n and remaining:
                best_idx = -1
                best_adjusted_score = -float('inf')
                
                for idx, (item_id, score) in enumerate(remaining):
                    category_adjustment = 0
                    chain_adjustment = 0
                    
                    # Category diversity adjustment
                    if item_id in item_categories:
                        category = item_categories[item_id]
                        current_cat_count = selected_categories.get(category, 0)
                        
                        if current_cat_count >= max_per_category:
                            # Heavy penalty for exceeding max per category
                            category_adjustment = -0.5
                        elif current_cat_count == 0:
                            # Strong boost for new categories
                            category_adjustment = 0.3
                        else:
                            # Small adjustment based on representation
                            category_adjustment = 0.1 * (1 - current_cat_count / max_per_category)
                    
                    # Chain diversity adjustment
                    if item_id in item_chains:
                        chain = item_chains[item_id]
                        current_chain_count = selected_chains.get(chain, 0)
                        
                        if current_chain_count >= max_per_chain:
                            # Penalty for exceeding max per chain
                            chain_adjustment = -0.3
                        elif current_chain_count == 0:
                            # Boost for new chains
                            chain_adjustment = 0.2
                        else:
                            # Small adjustment based on representation
                            chain_adjustment = 0.05 * (1 - current_chain_count / max_per_chain)
                    
                    # Combined adjustment weighted by domain importance
                    diversity_adjustment = (
                        category_adjustment * self.crypto_weights.get("category_correlation", 0.6) +
                        chain_adjustment * self.crypto_weights.get("chain_importance", 0.3)
                    )
                    
                    adjusted_score = score + diversity_adjustment
                    
                    if adjusted_score > best_adjusted_score:
                        best_idx = idx
                        best_adjusted_score = adjusted_score
                
                if best_idx >= 0:
                    item_id, score = remaining.pop(best_idx)
                    result.append((item_id, score))
                    
                    # Update category and chain tracking
                    if item_id in item_categories:
                        category = item_categories[item_id]
                        selected_categories[category] = selected_categories.get(category, 0) + 1
                    if item_id in item_chains:
                        chain = item_chains[item_id]
                        selected_chains[chain] = selected_chains.get(chain, 0) + 1
                else:
                    # If no item found with our diversity criteria, just take next best
                    if remaining:
                        item_id, score = remaining.pop(0)
                        result.append((item_id, score))
                        
                        # Update tracking
                        if item_id in item_categories:
                            category = item_categories[item_id]
                            selected_categories[category] = selected_categories.get(category, 0) + 1
                        if item_id in item_chains:
                            chain = item_chains[item_id]
                            selected_chains[chain] = selected_chains.get(chain, 0) + 1
                    else:
                        break
            
            # Sort final results by score
            result.sort(key=lambda x: x[1], reverse=True)
            return result[:n]
        
        # If no category/chain information, return top candidates
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
            # IMPROVED: Get recommendations for both similar and contrasting categories
            # First, get similar category recommendations
            similar_recs = self.fecf_model.get_cold_start_recommendations(
                user_interests=user_categories,
                n=n
            )
            similar_tuples = [(rec['id'], rec['recommendation_score'] * 1.2) for rec in similar_recs]  # Boost for core interests
            
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
                    n=n//3  # Fewer recommendations from contrasting categories
                )
                contrasting_recs = [(rec['id'], rec['recommendation_score'] * 0.7) for rec in contrast_results]  # Lower scores
            
            # Get trending items for novelty
            trending_recs = []
            if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
                trending_df = self.projects_df.sort_values('trend_score', ascending=False).head(n//4)
                trending_recs = [(row['id'], row['trend_score']/100 * 0.9) for _, row in trending_df.iterrows()]
            
            # Combine and sort
            combined_recs = similar_tuples + contrasting_recs + trending_recs
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
        Generate optimized recommendations for cold-start users for cryptocurrency domain
        """
        # 1. First try to get recommendations based on any partial history if available
        partial_history_recs = []
        if hasattr(self, 'user_item_matrix') and user_id in self.user_item_matrix.index:
            user_interactions = self.user_item_matrix.loc[user_id]
            interaction_count = (user_interactions > 0).sum()
            
            if interaction_count > 0:  # User has some history, but may not have enough for full recommendation
                # Try to get recommendations based on those few interactions
                partial_history_recs = self._get_cold_start_recommendations_by_interest(user_id, n*2)
        
        # 2. Get baseline recommendations from FECF for robust coverage
        fecf_recs = self.fecf_model.get_cold_start_recommendations(n=n*2)
        fecf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                    for rec in fecf_recs if 'id' in rec]
        
        # 3. Get popularity-based recommendations from NCF
        try:
            ncf_recs = self.ncf_model.get_popular_projects(n=n*2)
            ncf_scores = [(rec.get('id'), rec.get('recommendation_score', 0.5)) 
                        for rec in ncf_recs if 'id' in rec]
        except Exception as e:
            logger.warning(f"Error getting NCF recommendations for cold-start: {str(e)}")
            ncf_scores = []
        
        # 4. Add trending items (critical for crypto cold-start)
        trending_scores = []
        if hasattr(self, 'projects_df') and 'trend_score' in self.projects_df.columns:
            trending_df = self.projects_df.sort_values('trend_score', ascending=False).head(n)
            trending_scores = [(row['id'], row['trend_score']/100) for _, row in trending_df.iterrows()]
        
        # Combine all recommendation sources
        all_recs = {}
        
        # Add scores with source tracking for better weighting
        # Crypto-specific: More weight on trending and FECF for cold-start
        source_weights = {
            'partial': 0.9,       # Highest weight for partial history (if available)
            'trending': 0.85,     # Very high for trending (crypto-specific)
            'fecf': 0.75,         # High weight for FECF
            'ncf': 0.4            # Lower weight for general popularity
        }
        
        # Get cold-start parameters
        cold_start_fecf_weight = self.params.get('cold_start_fecf_weight', 0.9)
        explore_ratio = self.params.get('explore_ratio', 0.2)
        diversity_factor = self.params.get('diversity_factor', 0.15)
        
        # Add partial history recommendations (highest weight)
        for item_id, score in partial_history_recs:
            all_recs[item_id] = {'score': score * source_weights['partial'], 'source': 'partial'}
        
        # Add trending recommendations (very important for crypto)
        for item_id, score in trending_scores:
            if item_id in all_recs:
                all_recs[item_id]['score'] = max(all_recs[item_id]['score'], score * source_weights['trending'])
                all_recs[item_id]['source'] = 'trending+' + all_recs[item_id]['source']
            else:
                all_recs[item_id] = {'score': score * source_weights['trending'], 'source': 'trending'}
        
        # Add FECF recommendations
        for item_id, score in fecf_scores:
            if item_id in all_recs:
                all_recs[item_id]['score'] = max(all_recs[item_id]['score'], score * source_weights['fecf'])
                if 'fecf' not in all_recs[item_id]['source']:
                    all_recs[item_id]['source'] += '+fecf'
            else:
                all_recs[item_id] = {'score': score * source_weights['fecf'], 'source': 'fecf'}
        
        # Add NCF recommendations
        for item_id, score in ncf_scores:
            if item_id in all_recs:
                all_recs[item_id]['score'] = max(all_recs[item_id]['score'], score * source_weights['ncf'])
                if 'ncf' not in all_recs[item_id]['source']:
                    all_recs[item_id]['source'] += '+ncf'
            else:
                all_recs[item_id] = {'score': score * source_weights['ncf'], 'source': 'ncf'}
        
        # Get category and chain information for diversity
        item_categories = {}
        item_chains = {}
        market_caps = {}
        
        try:
            if hasattr(self, 'projects_df'):
                # Map items to categories and chains
                for _, row in self.projects_df.iterrows():
                    if 'id' in row:
                        if 'primary_category' in row:
                            item_categories[row['id']] = row['primary_category']
                        if 'chain' in row:
                            item_chains[row['id']] = row['chain'] 
                        if 'market_cap' in row:
                            market_caps[row['id']] = row['market_cap']
        except Exception as e:
            logger.warning(f"Error getting category/chain info: {e}")
        
        # Calculate diversity factors and apply to scores
        scored_candidates = []
        
        # For cold-start in crypto, we want:
        # 1. A good mix of established projects (higher market cap)
        # 2. Some exposure to trending projects
        # 3. Diversity across categories and chains
        for item_id, data in all_recs.items():
            base_score = data['score']
            source = data['source']
            final_score = base_score
            
            # Market cap factor - for cold-start crypto, balance is key
            if item_id in market_caps:
                market_cap = market_caps[item_id]
                # Find percentile within available projects
                if market_caps:
                    max_market_cap = max(market_caps.values())
                    if max_market_cap > 0:
                        market_cap_percentile = market_cap / max_market_cap
                        
                        # For cold-start in crypto, prefer established projects but include some smaller ones
                        if market_cap_percentile > 0.75:  # Big established projects
                            final_score += 0.15  # Strong boost
                        elif market_cap_percentile > 0.5:  # Mid-size projects
                            final_score += 0.1   # Medium boost
                        elif market_cap_percentile > 0.25:  # Smaller but not tiny
                            final_score += 0.05  # Small boost
                        # No boost for very small projects (higher risk for cold-start)
            
            # Source-based adjustments for cryptocurrency domain
            if 'trending' in source:
                final_score += 0.1  # Additional boost for trending (very important in crypto)
            if 'partial' in source:
                final_score += 0.05  # Slight boost for recommendations based on partial history
            
            # Track category and chain for diversity balancing
            category = item_categories.get(item_id, 'unknown')
            chain = item_chains.get(item_id, 'unknown')
            
            scored_candidates.append((item_id, final_score, category, chain))
        
        # Sort by score and prepare for diversity filtering
        scored_candidates.sort(key=lambda x: x[1], reverse=True)
        
        # Ensure good diversity across category and chains 
        # First, select some top items unconditionally
        top_count = max(n // 4, 1)  # 25% by pure score
        result = scored_candidates[:top_count]
        
        # Track categories and chains
        selected_categories = {}
        selected_chains = {}
        
        for item_id, _, category, chain in result:
            selected_categories[category] = selected_categories.get(category, 0) + 1
            selected_chains[chain] = selected_chains.get(chain, 0) + 1
        
        # Max per category (25% of total)
        max_per_category = max(1, int(n * 0.25))
        # Max per chain (35% of total)
        max_per_chain = max(2, int(n * 0.35))
        
        # Remaining candidates
        remaining = scored_candidates[top_count:]
        
        # Select remaining with diversity in mind
        while len(result) < n and remaining:
            best_idx = -1
            best_score = -float('inf')
            
            for idx, (item_id, score, category, chain) in enumerate(remaining):
                # Calculate diversity adjustment
                diversity_adjustment = 0
                
                # Category diversity
                cat_count = selected_categories.get(category, 0)
                if cat_count >= max_per_category:
                    diversity_adjustment -= 0.5  # Big penalty if already at max
                elif cat_count == 0:
                    diversity_adjustment += 0.2  # Bonus for new category
                
                # Chain diversity
                chain_count = selected_chains.get(chain, 0)
                if chain_count >= max_per_chain:
                    diversity_adjustment -= 0.4  # Penalty if chain overrepresented
                elif chain_count == 0:
                    diversity_adjustment += 0.15  # Bonus for new chain
                
                adjusted_score = score + diversity_adjustment
                
                if adjusted_score > best_score:
                    best_idx = idx
                    best_score = adjusted_score
            
            if best_idx >= 0:
                item_id, score, category, chain = remaining.pop(best_idx)
                result.append((item_id, score, category, chain))
                
                # Update tracking
                selected_categories[category] = selected_categories.get(category, 0) + 1
                selected_chains[chain] = selected_chains.get(chain, 0) + 1
            else:
                # If no suitable candidate found, take next best by raw score
                if remaining:
                    item_id, score, category, chain = remaining.pop(0)
                    result.append((item_id, score, category, chain))
                    
                    # Update tracking
                    selected_categories[category] = selected_categories.get(category, 0) + 1
                    selected_chains[chain] = selected_chains.get(chain, 0) + 1
                else:
                    break
        
        # Convert back to simple (item_id, score) format and sort by final score
        final_results = [(item_id, score) for item_id, score, _, _ in result]
        final_results.sort(key=lambda x: x[1], reverse=True)
        
        return final_results[:n]
    
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