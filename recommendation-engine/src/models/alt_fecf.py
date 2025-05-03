"""
Alternative Feature-Enhanced CF menggunakan scikit-learn Matrix Factorization
yang dioptimalkan untuk domain cryptocurrency
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

from sklearn.decomposition import TruncatedSVD
from sklearn.metrics.pairwise import cosine_similarity
from scipy.sparse import csr_matrix

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import FECF_PARAMS, MODELS_DIR, PROCESSED_DIR, CRYPTO_DOMAIN_WEIGHTS

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class FeatureEnhancedCF:
    """
    Implementation of Feature-Enhanced CF using scikit-learn
    Optimized for cryptocurrency domain
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
        """
        Initialize Feature-Enhanced CF model
        
        Args:
            params: Model parameters (overwrites defaults from config)
        """
        # Model parameters
        self.params = params or FECF_PARAMS
        
        # Initialize model
        self.model = None
        self._user_mapping = {}
        self._item_mapping = {}
        self._reverse_user_mapping = {}
        self._reverse_item_mapping = {}
        self._item_features = None
        
        # Project data
        self.projects_df = None
        self.user_item_matrix = None
        self.interactions_df = None
        self.features_df = None
        
        # Item similarities
        self.item_similarity_matrix = None
        
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
        # Use default paths if not specified
        if projects_path is None:
            projects_path = os.path.join(PROCESSED_DIR, "projects.csv")
        if interactions_path is None:
            interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        if features_path is None:
            features_path = os.path.join(PROCESSED_DIR, "features.csv")
            
        try:
            # Load projects data
            if os.path.exists(projects_path):
                self.projects_df = pd.read_csv(projects_path)
                logger.info(f"Loaded {len(self.projects_df)} projects from {projects_path}")
            else:
                logger.error(f"Projects file not found: {projects_path}")
                return False
                
            # Load interactions data
            if os.path.exists(interactions_path):
                self.interactions_df = pd.read_csv(interactions_path)
                logger.info(f"Loaded {len(self.interactions_df)} interactions from {interactions_path}")
                
                # Create user-item matrix
                self.user_item_matrix = pd.pivot_table(
                    self.interactions_df,
                    values='weight',
                    index='user_id',
                    columns='project_id',
                    fill_value=0
                )
                logger.info(f"Created user-item matrix with shape {self.user_item_matrix.shape}")
                
                # Create user and item mappings
                self._create_mappings()
            else:
                logger.error(f"Interactions file not found: {interactions_path}")
                return False
                
            # Load features data
            if os.path.exists(features_path):
                self.features_df = pd.read_csv(features_path)
                logger.info(f"Loaded features with shape {self.features_df.shape} from {features_path}")
                
                # Convert features to item-feature matrix
                self._item_features = self._create_item_features()
            else:
                logger.warning(f"Features file not found: {features_path}. Will use limited features.")
                # Create simple features from projects data
                self.features_df = self.projects_df[['id']].copy()
                
            return True
            
        except Exception as e:
            logger.error(f"Error loading data: {str(e)}")
            return False
    
    def _create_mappings(self):
        """Create user and item ID mappings"""
        # Get unique users and items
        users = list(self.user_item_matrix.index)
        items = list(self.user_item_matrix.columns)
        
        # Create mappings
        self._user_mapping = {user: idx for idx, user in enumerate(users)}
        self._item_mapping = {item: idx for idx, item in enumerate(items)}
        
        # Create reverse mappings
        self._reverse_user_mapping = {idx: user for user, idx in self._user_mapping.items()}
        self._reverse_item_mapping = {idx: item for item, idx in self._item_mapping.items()}
        
        logger.info(f"Created mappings for {len(users)} users and {len(items)} items")
    
    def _create_item_features(self) -> csr_matrix:
        """
        Create item features matrix dengan normalisasi yang lebih baik
        
        Returns:
            csr_matrix: Item features matrix
        """
        # Extract relevant columns - exclude ID and numeric metrics
        exclude_cols = ['id', 'market_cap', 'total_volume', 'current_price', 
                    'price_change_percentage_24h', 'price_change_percentage_7d_in_currency',
                    'popularity_score', 'trend_score', 'developer_activity_score', 
                    'social_engagement_score']
        
        feature_cols = [col for col in self.features_df.columns if col not in exclude_cols]
        
        # Convert to sparse matrix
        item_features = self.features_df.set_index('id')[feature_cols]
        
        # Pastikan semua items di user_item_matrix ada di features
        all_items = set(self.user_item_matrix.columns)
        available_items = set(item_features.index)
        
        # Check for missing items
        missing_items = all_items - available_items
        
        if missing_items:
            logger.warning(f"{len(missing_items)} projects missing from features data")
            # Add empty rows for missing projects
            for project in missing_items:
                # Create a DataFrame with zeros for missing items
                zeros_df = pd.DataFrame(
                    {col: [0] for col in feature_cols},
                    index=[project]
                )
                # Append to item_features using concat
                item_features = pd.concat([item_features, zeros_df])
        
        # Ensure only items in user-item matrix are included
        item_features = item_features.reindex(index=self.user_item_matrix.columns, fill_value=0)
        
        # L2 normalisasi fitur untuk membuat skala yang lebih seragam
        from sklearn.preprocessing import normalize
        norm_features = normalize(item_features.values, norm='l2', axis=0)
        
        # Convert to sparse matrix dengan format yang optimal
        features_matrix = csr_matrix(norm_features)
        
        logger.info(f"Created item features matrix with shape {features_matrix.shape}")
        return features_matrix
    
    def train(self, save_model: bool = True) -> Dict[str, float]:
        """
        Train the Feature-Enhanced CF model menggunakan SVD dengan parameter optimal
        
        Args:
            save_model: Whether to save the model after training
            
        Returns:
            dict: Training metrics
        """
        start_time = time.time()
        logger.info("Training Feature-Enhanced CF model with SVD")
        
        try:
            # Convert user-item matrix to numpy array
            user_item_array = self.user_item_matrix.values
            
            # Tentukan jumlah komponen yang optimal
            # Recommend tidak melebihi 10% dari dimensi terkecil
            min_dimension = min(user_item_array.shape)
            n_components = self.params.get('no_components', 0)
            if n_components <= 0:
                # Jika n_components <= 0, gunakan auto-sizing berdasarkan data
                n_components = min(user_item_array.shape[0], user_item_array.shape[1])
                # Pastikan minimal 1
                n_components = max(1, n_components)
                self.params['no_components'] = n_components
                logger.info(f"Applying SVD with {n_components} components (auto-sized based on data)")
            
            # Gunakan TruncatedSVD dengan parameter yang lebih baik
            self.model = TruncatedSVD(
                n_components=n_components,
                random_state=42,
                n_iter=10  # Menambahkan iterasi untuk memastikan konvergensi
            )
            
            # Fit SVD model
            item_factors = self.model.fit_transform(user_item_array.T)  # Transpose for item factors
            
            # Hitung total explained variance
            explained_variance = self.model.explained_variance_ratio_.sum()
            logger.info(f"SVD explained variance: {explained_variance:.4f}")
            
            # Berhenti jika variance terlalu rendah - menunjukkan data tidak cocok untuk SVD
            if explained_variance < 0.3:  # Minimal 30% variance harus bisa dijelaskan
                logger.warning(f"Low explained variance ({explained_variance:.4f}), SVD might not be suitable for this data")
            
            # Compute item-item similarity matrix using item factors
            self.item_similarity_matrix = cosine_similarity(item_factors)
            
            # IMPROVED: Enhanced content feature weighting for crypto domain
            if self._item_features is not None:
                logger.info("Enhancing with content features (domain-optimized weighting)")
                content_similarity = cosine_similarity(self._item_features)
                
                # Get content alpha from params, dengan pembatasan nilai
                alpha = max(0.3, min(0.7, self.params.get('content_alpha', 0.65)))
                
                # Apply domain-specific adjustments
                category_importance = self.crypto_weights.get("category_correlation", 0.6)
                
                # Jika categories lebih penting dalam domain ini, berikan weight lebih
                if category_importance > 0.5:
                    # Reducer alpha untuk memberikan bobot lebih ke content features
                    alpha = alpha * 0.9
                
                # Blend kedua similarity matrices dengan mempertahankan sparsity
                # Untuk items dengan content similarity = 0, gunakan CF similarity saja
                for i in range(self.item_similarity_matrix.shape[0]):
                    for j in range(self.item_similarity_matrix.shape[1]):
                        if content_similarity[i, j] > 0:
                            self.item_similarity_matrix[i, j] = (
                                alpha * self.item_similarity_matrix[i, j] + 
                                (1 - alpha) * content_similarity[i, j]
                            )
                
                logger.info(f"Content features blended with CF with alpha={alpha:.2f}")
            
            training_time = time.time() - start_time
            metrics = {
                "training_time": training_time,
                "n_components": n_components,
                "explained_variance": explained_variance
            }
            
            logger.info(f"Model training completed in {training_time:.2f} seconds")
            
            # Save model if requested
            if save_model:
                self.save_model()
                    
            return metrics
        except Exception as e:
            logger.error(f"Error during training: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
            return {"error": str(e), "training_time": time.time() - start_time}
    
    def save_model(self, filepath: Optional[str] = None) -> str:
        """
        Save model to file
        
        Args:
            filepath: Path to save model, if None will use default path
            
        Returns:
            str: Path where model was saved
        """
        if filepath is None:
            # Create default path
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filepath = os.path.join(MODELS_DIR, f"fecf_model_{timestamp}.pkl")
            
        # Create directory if it doesn't exist
        os.makedirs(os.path.dirname(filepath), exist_ok=True)
        
        # Save model
        model_state = {
            'model': self.model,
            'user_mapping': self._user_mapping,
            'item_mapping': self._item_mapping,
            'reverse_user_mapping': self._reverse_user_mapping,
            'reverse_item_mapping': self._reverse_item_mapping,
            'item_similarity_matrix': self.item_similarity_matrix,
            'params': self.params,
            'timestamp': datetime.now().isoformat()
        }
        
        with open(filepath, 'wb') as f:
            pickle.dump(model_state, f)
            
        logger.info(f"Model saved to {filepath}")
        return filepath
    
    def load_model(self, filepath: str) -> bool:
        """
        Load model from file
        
        Args:
            filepath: Path to model file
            
        Returns:
            bool: Success status
        """
        try:
            logger.info(f"Attempting to load FECF model from {filepath}")
            
            if not os.path.exists(filepath):
                logger.error(f"Model file not found: {filepath}")
                return False
                
            with open(filepath, 'rb') as f:
                model_state = pickle.load(f)
                
            # Log model state keys for debugging
            logger.info(f"Model state contains keys: {list(model_state.keys())}")
                
            self.model = model_state.get('model')
            if self.model is None:
                logger.error("No 'model' key in loaded state")
                return False
                
            self._user_mapping = model_state.get('user_mapping', {})
            self._item_mapping = model_state.get('item_mapping', {})
            self._reverse_user_mapping = model_state.get('reverse_user_mapping', {})
            self._reverse_item_mapping = model_state.get('reverse_item_mapping', {})
            self.item_similarity_matrix = model_state.get('item_similarity_matrix')
            self.params = model_state.get('params', self.params)
            
            logger.info(f"FECF model successfully loaded from {filepath}")
            return True
                
        except Exception as e:
            logger.error(f"Error loading model: {str(e)}")
            # Log traceback untuk debugging
            import traceback
            logger.error(traceback.format_exc())
            return False
        
    def is_trained(self) -> bool:
        """
        Check if model is trained and ready for predictions
        
        Returns:
            bool: True if model is trained, False otherwise
        """
        if self.model is None:
            return False
        return True
    
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user with improved performance and vectorization
        
        Args:
            user_id: User ID
            n: Number of recommendations
            exclude_known: Whether to exclude already interacted items
            
        Returns:
            list: List of (project_id, score) tuples
        """
        if self.model is None or self.item_similarity_matrix is None:
            logger.error("Model not trained or loaded")
            return []
            
        # Check if user exists
        if user_id not in self.user_item_matrix.index:
            logger.warning(f"User {user_id} not found in the user-item matrix")
            return self._get_cold_start_recommendations(n)
            
        # Get user's ratings - use copy to avoid modifying original
        user_ratings = self.user_item_matrix.loc[user_id].copy()
        
        # Get positive interactions once
        positive_indices = user_ratings.index[user_ratings > 0].tolist()
        positive_weights = user_ratings[positive_indices].values
        
        # Get known items to exclude - do this once
        known_items = set(positive_indices) if exclude_known else set()
        
        # Prepare items for vectorized computation
        # Only consider items not in known_items
        all_items = [item for item in self.user_item_matrix.columns if item not in known_items]
        if not all_items:
            logger.warning(f"No items available for recommendation after excluding known items")
            return []
        
        # Get similarity scores in a vectorized way
        item_scores = np.zeros(len(all_items))
        
        # OPTIMIZATION: Pre-calculate all required indices
        try:
            rated_indices = [self._item_mapping[item] for item in positive_indices if item in self._item_mapping]
            candidate_indices = [self._item_mapping[item] for item in all_items if item in self._item_mapping]
            
            # OPTIMIZATION: Use vectorized operations for similarity calculation
            for i, item_idx in enumerate(candidate_indices):
                # Use numpy's vectorized operations
                similarities = np.array([self.item_similarity_matrix[item_idx, rated_idx] for rated_idx in rated_indices])
                weights = np.array(positive_weights[:len(rated_indices)])
                
                # Weighted sum with normalization
                if len(similarities) > 0 and weights.sum() > 0:
                    item_scores[i] = np.sum(similarities * weights) / weights.sum()
        except Exception as e:
            logger.error(f"Error in similarity calculation: {e}")
        
        # Create list of (item_id, score) tuples
        scores = list(zip(all_items, item_scores))
        
        # Sort by score
        scores.sort(key=lambda x: x[1], reverse=True)
        
        # OPTIMIZATION: Get more candidates for diversity but limit to a reasonable number
        candidate_size = min(n * 3, 100)  # Don't get too many candidates
        top_candidates = scores[:candidate_size]
        
        # Check if we have category and chain data for diversity
        if hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            # OPTIMIZATION: Create item metadata lookup only once
            item_metadata = {}
            for _, row in self.projects_df.iterrows():
                if 'id' in row:
                    item_data = {
                        'category': row.get('primary_category', 'unknown'),
                        'chain': row.get('chain', 'unknown')
                    }
                    item_metadata[row['id']] = item_data
            
            # OPTIMIZATION: More efficient diversification
            # First, select some top items unconditionally
            top_count = max(n // 5, 1)  # ~20% by pure score
            result_items = [item for item, _ in top_candidates[:top_count]]
            result_scores = [score for _, score in top_candidates[:top_count]]
            
            # Track selected categories and chains for diversity
            category_counts = {}
            chain_counts = {}
            
            # Initialize tracking
            for item in result_items:
                if item in item_metadata:
                    meta = item_metadata[item]
                    category = meta['category']
                    chain = meta['chain']
                    
                    category_counts[category] = category_counts.get(category, 0) + 1
                    chain_counts[chain] = chain_counts.get(chain, 0) + 1
            
            # Diversity limits
            max_per_category = max(2, int(n * 0.3))
            max_per_chain = max(3, int(n * 0.4))
            
            # Pool remaining candidates
            remaining = top_candidates[top_count:]
            
            # OPTIMIZATION: Pre-calculate diversity adjustments for all remaining items
            diversity_adjustments = []
            for item_id, score in remaining:
                adjustment = 0
                if item_id in item_metadata:
                    meta = item_metadata[item_id]
                    category = meta['category']
                    chain = meta['chain']
                    
                    # Category adjustment
                    cat_count = category_counts.get(category, 0)
                    if cat_count >= max_per_category:
                        adjustment -= 0.5
                    elif cat_count == 0:
                        adjustment += 0.3
                    else:
                        adjustment += 0.1 * (1 - cat_count / max_per_category)
                    
                    # Chain adjustment
                    chain_count = chain_counts.get(chain, 0)
                    if chain_count >= max_per_chain:
                        adjustment -= 0.3
                    elif chain_count == 0:
                        adjustment += 0.2
                    else:
                        adjustment += 0.05 * (1 - chain_count / max_per_chain)
                
                diversity_adjustments.append((item_id, score, adjustment, score + adjustment))
            
            # Sort by adjusted score once
            diversity_adjustments.sort(key=lambda x: x[3], reverse=True)
            
            # Select remaining items efficiently
            for item_id, score, _, _ in diversity_adjustments:
                if len(result_items) >= n:
                    break
                    
                # Only update tracking if we select this item
                if item_id in item_metadata:
                    meta = item_metadata[item_id]
                    category = meta['category']
                    chain = meta['chain']
                    
                    # Update category and chain counts
                    category_counts[category] = category_counts.get(category, 0) + 1
                    chain_counts[chain] = chain_counts.get(chain, 0) + 1
                
                # Add to results
                result_items.append(item_id)
                result_scores.append(score)
            
            # Create final result tuples and sort by original score
            result = list(zip(result_items, result_scores))
            result.sort(key=lambda x: x[1], reverse=True)
            return result[:n]
        
        # If no category/chain information, return top candidates
        return top_candidates[:n]
    
    def _get_cold_start_recommendations(self, n: int = 10) -> List[Tuple[str, float]]:
        """
        Get recommendations for cold-start users with improved category diversity and
        cryptocurrency-specific optimizations
        
        Args:
            n: Number of recommendations
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # IMPROVED: Multi-factor cryptocurrency cold-start approach
        if 'primary_category' in self.projects_df.columns:
            # 1. Get category distribution
            category_counts = self.projects_df['primary_category'].value_counts()
            
            # Identify major categories with at least 3 projects
            major_categories = category_counts[category_counts >= 3].index.tolist()
            
            # 2. Identify trending projects across categories (vital for crypto)
            trending_projects = []
            if 'trend_score' in self.projects_df.columns:
                # Get top trending projects overall
                trending_df = self.projects_df.sort_values('trend_score', ascending=False)
                # Take top 20% of trending projects, but at least 5
                top_trending_count = max(5, int(len(self.projects_df) * 0.2))
                trending_projects = trending_df.head(top_trending_count)['id'].tolist()
            
            # 3. Identify established projects by market cap (for stability)
            established_projects = []
            if 'market_cap' in self.projects_df.columns:
                # Get top projects by market cap
                market_cap_df = self.projects_df.sort_values('market_cap', ascending=False)
                # Take top 15% by market cap, but at least 5
                top_cap_count = max(5, int(len(self.projects_df) * 0.15))
                established_projects = market_cap_df.head(top_cap_count)['id'].tolist()
            
            # 4. Calculate how many projects to take from each source
            projects_per_category = max(1, int(n * 0.6 / len(major_categories)))  # 60% from categories
            trending_count = max(1, int(n * 0.25))  # 25% from trending
            established_count = max(1, int(n * 0.15))  # 15% from established
            
            # Adjust if we don't have trending or established projects
            if not trending_projects:
                established_count += trending_count
                trending_count = 0
            if not established_projects:
                trending_count += established_count
                established_count = 0
            if not trending_projects and not established_projects:
                projects_per_category = max(1, int(n / len(major_categories)))
            
            # 5. Collect diverse recommendations
            diversified_recommendations = []
            
            # 5a. Add category-based recommendations
            for category in major_categories:
                # Get projects from this category
                category_projects = self.projects_df[self.projects_df['primary_category'] == category]
                
                # Further sort by a blend of popularity and trend for better cold-start
                if 'popularity_score' in category_projects.columns and 'trend_score' in category_projects.columns:
                    # For crypto: Trend has higher weight than general popularity
                    category_projects = category_projects.copy()
                    category_projects['combined_score'] = (
                        category_projects['popularity_score'] * 0.3 + 
                        category_projects['trend_score'] * 0.6
                    )
                    category_projects = category_projects.sort_values('combined_score', ascending=False)
                elif 'popularity_score' in category_projects.columns:
                    category_projects = category_projects.sort_values('popularity_score', ascending=False)
                
                # Get top projects from this category
                top_category_projects = category_projects.head(projects_per_category)
                
                # Add to recommendations with score
                for _, project in top_category_projects.iterrows():
                    # Use combined score if available, otherwise popularity or default 0.8
                    if 'combined_score' in project:
                        score = project['combined_score'] / 100  # Normalize to 0-1
                    elif 'popularity_score' in project:
                        score = project['popularity_score'] / 100
                    else:
                        score = 0.8
                    
                    # Add moderate boost for established projects for stability
                    if project['id'] in established_projects:
                        score += 0.05
                    
                    # Add stronger boost for trending projects
                    if project['id'] in trending_projects:
                        score += 0.1
                    
                    diversified_recommendations.append((project['id'], float(score)))
            
            # 5b. Add trending recommendations not already included
            added_trends = set(item_id for item_id, _ in diversified_recommendations if item_id in trending_projects)
            trending_to_add = [item for item in trending_projects if item not in added_trends]
            
            for trend_item in trending_to_add[:trending_count]:
                project_row = self.projects_df[self.projects_df['id'] == trend_item]
                if not project_row.empty:
                    # Use trend score with a boost
                    if 'trend_score' in project_row.columns:
                        score = float(project_row['trend_score'].values[0]) / 100 + 0.1  # Added boost
                    else:
                        score = 0.85  # Default high score for trending
                    
                    diversified_recommendations.append((trend_item, score))
            
            # 5c. Add established recommendations not already included
            added_established = set(item_id for item_id, _ in diversified_recommendations if item_id in established_projects)
            established_to_add = [item for item in established_projects if item not in added_established]
            
            for estab_item in established_to_add[:established_count]:
                project_row = self.projects_df[self.projects_df['id'] == estab_item]
                if not project_row.empty:
                    # Use market cap as basis with moderate score
                    score = 0.75  # Slightly lower than trending
                    
                    diversified_recommendations.append((estab_item, score))
            
            # 6. Ensure we have enough recommendations
            if len(diversified_recommendations) < n:
                # Add top popularity projects not already included
                already_added = set(item_id for item_id, _ in diversified_recommendations)
                
                if 'popularity_score' in self.projects_df.columns:
                    popular_df = self.projects_df.sort_values('popularity_score', ascending=False)
                    for _, row in popular_df.iterrows():
                        if row['id'] not in already_added:
                            score = float(row['popularity_score']) / 100
                            diversified_recommendations.append((row['id'], score))
                            already_added.add(row['id'])
                            
                            if len(diversified_recommendations) >= n:
                                break
            
            # 7. Sort by score and return top n
            diversified_recommendations.sort(key=lambda x: x[1], reverse=True)
            return diversified_recommendations[:n]
        
        # Fallback to popularity-based approach if categories not available
        if 'popularity_score' in self.projects_df.columns:
            # IMPROVED: Calculate factor diversification penalty for crypto
            if 'primary_category' in self.projects_df.columns:
                # Calculate category frequency
                category_counts = self.projects_df['primary_category'].value_counts()
                max_count = category_counts.max()
                
                # Create adjusted DataFrame for scoring
                df_with_adjusted_scores = self.projects_df.copy()
                
                # Calculate score with category penalty and market cap bonus
                def adjust_score_by_crypto_factors(row):
                    # Start with base popularity score
                    popularity = row.get('popularity_score', 0)
                    
                    # 1. Category diversification
                    if 'primary_category' in row:
                        category = row['primary_category']
                        count = category_counts.get(category, 0)
                        
                        # Penalty for over-represented categories
                        category_penalty = 0.15 * (count / max_count) if max_count > 0 else 0
                        popularity = popularity * (1 - category_penalty)
                    
                    # 2. Market cap consideration - slight boost for established
                    if 'market_cap' in row and self.projects_df['market_cap'].max() > 0:
                        market_cap = row['market_cap']
                        market_percentile = market_cap / self.projects_df['market_cap'].max()
                        
                        if market_percentile > 0.7:  # Large cap
                            popularity += 5  # Small boost
                    
                    # 3. Trend boost for crypto projects
                    if 'trend_score' in row:
                        trend_score = row['trend_score']
                        if trend_score > 70:  # Highly trending
                            popularity += 15  # Significant boost
                        elif trend_score > 55:  # Moderately trending
                            popularity += 8   # Moderate boost
                    
                    return popularity
                
                # Apply the scoring function
                df_with_adjusted_scores['adjusted_score'] = df_with_adjusted_scores.apply(
                    adjust_score_by_crypto_factors, axis=1
                )
                
                # Sort by adjusted score
                popular = df_with_adjusted_scores.sort_values('adjusted_score', ascending=False).head(n*2)
                
                # Apply diversity constraints
                max_per_category = max(2, n // 3)
                selected = []
                category_counts_selected = {}
                
                # Select with category constraints
                for _, row in popular.iterrows():
                    category = row['primary_category']
                    current_count = category_counts_selected.get(category, 0)
                    
                    if current_count < max_per_category:
                        selected.append((row['id'], float(row['adjusted_score'])))
                        category_counts_selected[category] = current_count + 1
                    
                    if len(selected) >= n:
                        break
                
                # If still not enough, add remaining popular items
                if len(selected) < n:
                    remaining = [
                        (row['id'], float(row['adjusted_score']))
                        for _, row in popular.iterrows() 
                        if row['id'] not in [item[0] for item in selected]
                    ]
                    selected.extend(remaining[:n - len(selected)])
                
                return selected
            
            # Simple popularity-based fallback
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n)
            return [(row['id'], float(row['popularity_score'])) for _, row in popular.iterrows()]
        else:
            # Return random items with default score
            projects = self.projects_df.sample(n=min(n, len(self.projects_df)))
            return [(row['id'], 1.0) for _, row in projects.iterrows()]
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Generate project recommendations with full details
        
        Args:
            user_id: User ID
            n: Number of recommendations
            
        Returns:
            list: List of project dictionaries with recommendation scores
        """
        # Get recommendations as (project_id, score) tuples
        recommendations = self.recommend_for_user(user_id, n)
        
        # Convert to detailed project dictionaries
        detailed_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary with all available fields
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # Ensure critical fields are available (even if null)
                required_fields = ['id', 'name', 'symbol', 'image', 'price_usd', 'market_cap', 
                                'volume_24h', 'price_change_24h', 'price_change_7d', 
                                'popularity_score', 'trend_score', 'primary_category', 'chain']
                
                for field in required_fields:
                    if field not in project_dict:
                        # Try alternate field names
                        if field == 'price_usd' and 'current_price' in project_dict:
                            project_dict['price_usd'] = project_dict['current_price']
                        elif field == 'volume_24h' and 'total_volume' in project_dict:
                            project_dict['volume_24h'] = project_dict['total_volume']
                        elif field == 'price_change_7d' and 'price_change_percentage_7d_in_currency' in project_dict:
                            project_dict['price_change_7d'] = project_dict['price_change_percentage_7d_in_currency']
                        elif field == 'category' and 'primary_category' in project_dict:
                            project_dict['category'] = project_dict['primary_category']
                        else:
                            # Default to None
                            project_dict[field] = None
                
                # Add to results
                detailed_recommendations.append(project_dict)
                
        return detailed_recommendations
    
    def get_similar_projects(self, project_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Find similar projects based on features and collaborative data
        with cryptocurrency-specific enhancements
        
        Args:
            project_id: Project ID
            n: Number of similar projects to return
            
        Returns:
            list: List of similar project dictionaries with similarity scores
        """
        if self.model is None or self.item_similarity_matrix is None:
            logger.error("Model not trained or loaded")
            # Fallback to popular projects
            return self.get_popular_projects(n)
            
        # Check if project exists
        if project_id not in self._item_mapping:
            logger.warning(f"Project {project_id} not found in the model")
            
            # First try case-insensitive search
            for item_id in self._item_mapping.keys():
                if item_id.lower() == project_id.lower():
                    logger.info(f"Found case-insensitive match: {item_id}")
                    project_id = item_id
                    break
            
            # If still not found, see if it exists in projects_df but not in the model
            if project_id not in self._item_mapping and self.projects_df is not None:
                project_exists = project_id in self.projects_df['id'].values
                if project_exists:
                    logger.warning(f"Project {project_id} exists in dataset but not in trained model")
                    # Try to find projects in the same category or chain
                    target_project = self.projects_df[self.projects_df['id'] == project_id].iloc[0]
                    
                    similar_by_factors = []
                    
                    # Try to find similar by category
                    if 'primary_category' in target_project:
                        category = target_project['primary_category']
                        logger.info(f"Finding projects in the same category: {category}")
                        category_projects = self.projects_df[self.projects_df['primary_category'] == category]
                        
                        if len(category_projects) > 0:
                            for _, project in category_projects.head(n).iterrows():
                                similar_by_factors.append({
                                    **project.to_dict(),
                                    'similarity_score': 0.75,      # Default score for category match
                                    'recommendation_score': 0.75   # For API consistency
                                })
                    
                    # If not enough by category, try by chain
                    if len(similar_by_factors) < n and 'chain' in target_project:
                        chain = target_project['chain']
                        logger.info(f"Finding projects on the same chain: {chain}")
                        
                        # Exclude projects we already added
                        already_added = set(item['id'] for item in similar_by_factors)
                        chain_projects = self.projects_df[
                            (self.projects_df['chain'] == chain) & 
                            (~self.projects_df['id'].isin(already_added))
                        ]
                        
                        if len(chain_projects) > 0:
                            for _, project in chain_projects.head(n - len(similar_by_factors)).iterrows():
                                similar_by_factors.append({
                                    **project.to_dict(),
                                    'similarity_score': 0.65,      # Slightly lower for chain match
                                    'recommendation_score': 0.65   # For API consistency
                                })
                    
                    # If we found any, return them
                    if similar_by_factors:
                        return similar_by_factors[:n]
                
                # Final fallback to popular projects
                logger.warning(f"No match found for {project_id}, returning popular projects")
                popular = self.get_popular_projects(n)
                # Add similarity scores to make the output format consistent
                for project in popular:
                    project['similarity_score'] = project.get('recommendation_score', 0.5)
                return popular
                
        # Continue with original implementation if project is found
        # Get item index
        item_idx = self._item_mapping[project_id]
        
        # Get all similarities
        item_similarities = []
        for other_id, other_idx in self._item_mapping.items():
            if other_id == project_id:
                continue
                
            similarity = self.item_similarity_matrix[item_idx, other_idx]
            item_similarities.append((other_id, similarity))
            
        # Sort by similarity
        item_similarities.sort(key=lambda x: x[1], reverse=True)
        
        # IMPROVED: Get project details for enhancing similarity scores
        target_project = self.projects_df[self.projects_df['id'] == project_id].iloc[0]
        target_details = {}
        
        # Extract key details for similarity enhancement
        if 'primary_category' in target_project:
            target_details['category'] = target_project['primary_category']
        if 'chain' in target_project:
            target_details['chain'] = target_project['chain']
        if 'market_cap' in target_project:
            target_details['market_cap'] = target_project['market_cap']
        
        # Enhanced similarities with cryptocurrency-specific factors
        enhanced_similarities = []
        
        # Process top candidates (3x needed)
        for other_id, similarity in item_similarities[:n*3]:
            project_data = self.projects_df[self.projects_df['id'] == other_id]
            
            if not project_data.empty:
                # Get project details
                project = project_data.iloc[0]
                
                # Start with base similarity score
                enhanced_score = similarity
                
                # Apply cryptocurrency-specific enhancements
                # 1. Category match bonus - similar categories are critical in crypto
                if 'primary_category' in project and 'category' in target_details:
                    if project['primary_category'] == target_details['category']:
                        enhanced_score += 0.1  # Significant boost for exact category match
                
                # 2. Chain match bonus - projects on same chain often correlate in crypto
                if 'chain' in project and 'chain' in target_details:
                    if project['chain'] == target_details['chain']:
                        enhanced_score += 0.05  # Moderate boost for chain match
                
                # 3. Market cap similarity - projects with similar cap often move together
                if 'market_cap' in project and 'market_cap' in target_details and target_details['market_cap'] > 0:
                    market_cap_ratio = min(
                        project['market_cap'] / target_details['market_cap'],
                        target_details['market_cap'] / project['market_cap']
                    ) if project['market_cap'] > 0 else 0
                    
                    # Boost for similar market cap
                    if market_cap_ratio > 0.7:  # Very similar market cap
                        enhanced_score += 0.03
                
                # Create project dictionary with all details
                project_dict = project.to_dict()
                
                # Add similarity score
                project_dict['similarity_score'] = float(enhanced_score)
                project_dict['recommendation_score'] = float(enhanced_score)  # For API consistency
                
                # Ensure critical fields are available (even if null)
                required_fields = ['id', 'name', 'symbol', 'image', 'price_usd', 'market_cap', 
                                'volume_24h', 'price_change_24h', 'price_change_7d', 
                                'popularity_score', 'trend_score', 'primary_category', 'chain']
                
                for field in required_fields:
                    if field not in project_dict:
                        # Try alternate field names
                        if field == 'price_usd' and 'current_price' in project_dict:
                            project_dict['price_usd'] = project_dict['current_price']
                        elif field == 'volume_24h' and 'total_volume' in project_dict:
                            project_dict['volume_24h'] = project_dict['total_volume']
                        elif field == 'price_change_7d' and 'price_change_percentage_7d_in_currency' in project_dict:
                            project_dict['price_change_7d'] = project_dict['price_change_percentage_7d_in_currency']
                        elif field == 'category' and 'primary_category' in project_dict:
                            project_dict['category'] = project_dict['primary_category']
                        else:
                            # Default to None
                            project_dict[field] = None
                
                enhanced_similarities.append(project_dict)
        
        # Sort by enhanced similarity score
        enhanced_similarities.sort(key=lambda x: x['similarity_score'], reverse=True)
        
        # Apply diversity - avoid too many from same category/chain
        diversified_results = []
        category_counts = {}
        chain_counts = {}
        
        # Select top results with diversity
        for project in enhanced_similarities:
            category = project.get('primary_category', 'unknown')
            chain = project.get('chain', 'unknown')
            
            # Get current counts
            cat_count = category_counts.get(category, 0)
            chain_count = chain_counts.get(chain, 0)
            
            # Check for diversity constraints
            if cat_count >= n//3 and chain_count >= n//3:
                # Skip if both category and chain are over-represented
                continue
            elif cat_count >= n//2:
                # Skip if category is heavily over-represented
                continue
            elif chain_count >= n//2:
                # Skip if chain is heavily over-represented
                continue
            
            # Add to results
            diversified_results.append(project)
            category_counts[category] = cat_count + 1
            chain_counts[chain] = chain_count + 1
            
            # Stop when we have enough
            if len(diversified_results) >= n:
                break
        
        # If we don't have enough with diversity, add remaining by raw score
        if len(diversified_results) < n:
            remaining = [p for p in enhanced_similarities if p not in diversified_results]
            diversified_results.extend(remaining[:n - len(diversified_results)])
        
        return diversified_results[:n]
    
    def get_cold_start_recommendations(self, 
                          user_interests: Optional[List[str]] = None,
                          n: int = 10) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi untuk cold-start user
        
        Args:
            user_interests: Daftar kategori/minat pengguna (opsional)
            n: Jumlah rekomendasi yang diinginkan
            
        Returns:
            list: Daftar objek proyek yang direkomendasikan
        """
        # Mendapatkan rekomendasi dalam bentuk (project_id, score) tuples
        recommendations = self._get_cold_start_recommendations(n=n)
        
        # Membuat filter berdasarkan kategori jika user_interests disediakan
        filtered_recommendations = []
        if user_interests and hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            interest_set = set(user_interests)
            
            # Filter rekomendasi berdasarkan minat pengguna
            for project_id, score in recommendations:
                project_data = self.projects_df[self.projects_df['id'] == project_id]
                if not project_data.empty:
                    category = project_data.iloc[0].get('primary_category')
                    if category in interest_set or not interest_set:
                        filtered_recommendations.append((project_id, score))
            
            # Jika hasil filter terlalu sedikit, tambahkan rekomendasi lain
            if len(filtered_recommendations) < n//2:
                for project_id, score in recommendations:
                    if (project_id, score) not in filtered_recommendations and len(filtered_recommendations) < n:
                        filtered_recommendations.append((project_id, score))
        else:
            filtered_recommendations = recommendations
        
        # Konversi ke bentuk dictionary
        detailed_recommendations = []
        for project_id, score in filtered_recommendations[:n]:
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
        """
        Get trending projects based on trend score
        
        Args:
            n: Number of trending projects to return
            
        Returns:
            list: List of trending project dictionaries
        """
        if 'trend_score' in self.projects_df.columns:
            # Sort by trend score
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n)
            result = []
            
            for _, project in trending.iterrows():
                # Get complete project data
                project_dict = project.to_dict()
                
                # Ensure we add recommendation score for consistency
                project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0))
                
                # Ensure critical fields are available (even if null)
                required_fields = ['id', 'name', 'symbol', 'image', 'price_usd', 'market_cap', 
                                'volume_24h', 'price_change_24h', 'price_change_7d', 
                                'popularity_score', 'trend_score', 'primary_category', 'chain']
                
                for field in required_fields:
                    if field not in project_dict:
                        # Try alternate field names
                        if field == 'price_usd' and 'current_price' in project_dict:
                            project_dict['price_usd'] = project_dict['current_price']
                        elif field == 'volume_24h' and 'total_volume' in project_dict:
                            project_dict['volume_24h'] = project_dict['total_volume']
                        elif field == 'price_change_7d' and 'price_change_percentage_7d_in_currency' in project_dict:
                            project_dict['price_change_7d'] = project_dict['price_change_percentage_7d_in_currency']
                        elif field == 'category' and 'primary_category' in project_dict:
                            project_dict['category'] = project_dict['primary_category']
                        else:
                            # Default to None
                            project_dict[field] = None
                
                result.append(project_dict)
                
            return result
        else:
            logger.warning("No trend score available, returning top projects by other metrics")
            return self.get_popular_projects(n)
    
    def get_popular_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get popular projects based on popularity score with
        cryptocurrency-specific enhancements
        
        Args:
            n: Number of popular projects to return
            
        Returns:
            list: List of popular project dictionaries
        """
        if 'popularity_score' in self.projects_df.columns:
            # IMPROVED: Enhanced popularity ranking for crypto
            # In crypto, mix of popularity, market cap, and trend is important
            if 'trend_score' in self.projects_df.columns and 'market_cap' in self.projects_df.columns:
                df = self.projects_df.copy()
                
                # Calculate blended score with domain-specific weights
                trend_weight = self.crypto_weights.get("trend_importance", 0.7)
                market_weight = self.crypto_weights.get("market_cap_influence", 0.4)
                
                # Create blended rank considering both popularity and trend
                df['crypto_score'] = (
                    df['popularity_score'] * (1 - trend_weight) + 
                    df['trend_score'] * trend_weight
                )
                
                # Add market cap bonus for established projects (transformed to avoid domination)
                if df['market_cap'].max() > 0:
                    # Calculate market cap percentile
                    df['market_cap_pct'] = df['market_cap'] / df['market_cap'].max()
                    # Apply moderate bonus for established projects
                    df['crypto_score'] += df['market_cap_pct'] * 10 * market_weight
                
                # Sort by enhanced score
                popular = df.sort_values('crypto_score', ascending=False).head(n)
                
                # Prepare result
                result = []
                for _, project in popular.iterrows():
                    project_dict = project.to_dict()
                    
                    # Use enhanced score for recommendation
                    project_dict['recommendation_score'] = float(project_dict.get('crypto_score', 0))
                    
                    # Ensure all required fields
                    required_fields = ['id', 'name', 'symbol', 'image', 'price_usd', 'market_cap', 
                                'volume_24h', 'price_change_24h', 'price_change_7d']
                    
                    for field in required_fields:
                        if field not in project_dict:
                            if field == 'price_usd' and 'current_price' in project_dict:
                                project_dict['price_usd'] = project_dict['current_price']
                            elif field == 'volume_24h' and 'total_volume' in project_dict:
                                project_dict['volume_24h'] = project_dict['total_volume']
                            else:
                                project_dict[field] = None
                    
                    result.append(project_dict)
                    
                return result
            
            # Standard popularity approach if additional metrics not available
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n)
            result = []
            
            for _, project in popular.iterrows():
                # Get complete project data
                project_dict = project.to_dict()
                
                # Ensure we add recommendation score for consistency
                project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
                
                # Add to results
                result.append(project_dict)
                    
            return result
        elif 'market_cap' in self.projects_df.columns:
            # Use market cap as fallback
            popular = self.projects_df.sort_values('market_cap', ascending=False).head(n)
            
            # Ensure all required fields are present
            result = []
            for _, project in popular.iterrows():
                project_dict = project.to_dict()
                project_dict['recommendation_score'] = float(project_dict.get('market_cap', 0)) / 1e9  # Normalize market cap
                
                # Add missing fields
                if 'image' not in project_dict:
                    project_dict['image'] = None
                
                result.append(project_dict)
                
            return result
        else:
            # Just return first n projects
            projects = self.projects_df.head(n)
            return [
                {**row.to_dict(), 'recommendation_score': 0.5, 'image': row.get('image', None)}
                for _, row in projects.iterrows()
            ]