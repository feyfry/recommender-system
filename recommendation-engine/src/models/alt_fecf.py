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
    Optimized for cryptocurrency domain with enhanced cold-start strategies
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
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
        
        # Cache untuk mempercepat rekomendasi
        self._recommendation_cache = {}
        self._cold_start_cache = {}
        
        # Metadata untuk optimasi cold-start
        self._popular_items = None
        self._trending_items = None
        self._category_distributions = None
        self._category_item_mapping = None
    
    def load_data(self, projects_path: Optional[str] = None, interactions_path: Optional[str] = None,features_path: Optional[str] = None) -> bool:
        
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
                
            # Precompute popular and trending items untuk cold-start
            self._precompute_cold_start_candidates()
            
            # Precompute category distributions
            self._precompute_category_info()
            
            return True
            
        except Exception as e:
            logger.error(f"Error loading data: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
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
    
    def _precompute_cold_start_candidates(self):
        """Precompute popular and trending items for faster cold-start recommendations"""
        if self.projects_df is None:
            return
            
        # Calculate item popularity from interaction matrix
        item_interactions = (self.user_item_matrix > 0).sum()
        item_popularity = pd.Series(0, index=self.projects_df['id'])
        item_popularity.update(item_interactions)
        
        # Get top 20% popular items by interaction count
        popular_threshold = item_popularity.quantile(0.8) if len(item_popularity) > 5 else 1
        popular_items = item_popularity[item_popularity >= popular_threshold].index.tolist()
        
        # Store popular items with scores
        self._popular_items = []
        for item_id in popular_items:
            popularity_score = item_popularity.get(item_id, 0)
            # Find project data
            project_row = self.projects_df[self.projects_df['id'] == item_id]
            if not project_row.empty:
                # Normalize popularity score (max 100)
                normalized_score = min(100, (popularity_score / popular_threshold) * 60)
                self._popular_items.append((item_id, normalized_score / 100))
        
        # Sort by score
        self._popular_items.sort(key=lambda x: x[1], reverse=True)
        
        # Get trending items 
        if 'trend_score' in self.projects_df.columns:
            trending_items = self.projects_df.sort_values('trend_score', ascending=False)
            
            # Get top 15% trending items
            top_n = max(5, int(len(self.projects_df) * 0.15))
            trending_df = trending_items.head(top_n)
            
            # Store trending items with normalized scores
            self._trending_items = []
            for _, row in trending_df.iterrows():
                trend_score = row.get('trend_score', 0)
                # Higher weight for trend score in cold-start
                normalized_score = trend_score / 100 if trend_score <= 100 else 1.0
                self._trending_items.append((row['id'], normalized_score))
            
            logger.info(f"Precomputed {len(self._trending_items)} trending items for cold-start")
        
        logger.info(f"Precomputed {len(self._popular_items)} popular items for cold-start")
    
    def _precompute_category_info(self):
        """Precompute category distributions and mappings for better diversity"""
        if self.projects_df is None or 'primary_category' not in self.projects_df.columns:
            return
            
        # Get all categories
        categories = []
        
        # Try different category fields
        if 'categories_list' in self.projects_df.columns:
            # If we have a list of categories, use that
            for cats in self.projects_df['categories_list']:
                if isinstance(cats, list):
                    categories.extend(cats)
                else:
                    # Try to parse if it's a string representation of list
                    try:
                        if isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                            parsed_cats = eval(cats)
                            if isinstance(parsed_cats, list):
                                categories.extend(parsed_cats)
                    except:
                        pass
        else:
            # Otherwise, use primary_category
            categories = self.projects_df['primary_category'].tolist()
        
        # Calculate category distribution
        self._category_distributions = {}
        for category in categories:
            if category and category != 'unknown':
                self._category_distributions[category] = self._category_distributions.get(category, 0) + 1
        
        # Normalize distribution
        total = sum(self._category_distributions.values())
        if total > 0:
            self._category_distributions = {k: v/total for k, v in self._category_distributions.items()}
        
        # Create mapping from category to items
        self._category_item_mapping = {}
        
        # Check if categories_list column exists
        if 'categories_list' in self.projects_df.columns:
            # Process categorized items
            for _, row in self.projects_df.iterrows():
                if 'id' not in row:
                    continue
                    
                item_id = row['id']
                cats = row['categories_list']
                
                # Parse categories if needed
                if isinstance(cats, str):
                    try:
                        cats = eval(cats)
                    except:
                        cats = [cats]
                elif not isinstance(cats, list):
                    cats = [str(cats)]
                
                # Add to category mapping
                for category in cats:
                    if category and category != 'unknown':
                        if category not in self._category_item_mapping:
                            self._category_item_mapping[category] = []
                        self._category_item_mapping[category].append(item_id)
        else:
            # Use primary_category instead
            for _, row in self.projects_df.iterrows():
                if 'id' not in row or 'primary_category' not in row:
                    continue
                    
                item_id = row['id']
                category = row['primary_category']
                
                if category and category != 'unknown':
                    if category not in self._category_item_mapping:
                        self._category_item_mapping[category] = []
                    self._category_item_mapping[category].append(item_id)
        
        logger.info(f"Precomputed distribution for {len(self._category_distributions)} categories")
        logger.info(f"Created mapping for {len(self._category_item_mapping)} categories to items")
    
    def train(self, save_model: bool = True) -> Dict[str, float]:
        start_time = time.time()
        logger.info("Training Feature-Enhanced CF model with SVD")
        
        try:
            # Convert user-item matrix to numpy array
            user_item_array = self.user_item_matrix.values
            
            # Tentukan jumlah komponen yang optimal
            # Avoid exceeding 10% of the minimum dimension
            min_dimension = min(user_item_array.shape)
            n_components = self.params.get('no_components', 0)
            if n_components <= 0:
                # Auto-sizing based on data
                n_components = int(min(min_dimension * 0.1, 100))
                # Ensure at least 16 components
                n_components = max(16, n_components)
                self.params['no_components'] = n_components
                logger.info(f"Using {n_components} components (auto-sized based on data)")
            
            # Parameter untuk SVD yang lebih robust
            self.model = TruncatedSVD(
                n_components=n_components,
                random_state=42,
                n_iter=10,  # Lebih banyak iterasi untuk konvergensi
                algorithm='randomized'  # Randomized for better performance
            )
            
            # Fit SVD model
            item_factors = self.model.fit_transform(user_item_array.T)  # Transpose for item factors
            
            # Hitung total explained variance
            explained_variance = self.model.explained_variance_ratio_.sum()
            logger.info(f"SVD explained variance: {explained_variance:.4f}")
            
            # Compute item-item similarity matrix using item factors
            self.item_similarity_matrix = cosine_similarity(item_factors)
            
            # Enhanced content feature weighting for crypto domain
            if self._item_features is not None:
                logger.info("Enhancing with content features (domain-optimized weighting)")
                content_similarity = cosine_similarity(self._item_features)
                
                # Get content alpha from params
                alpha = max(0.3, min(0.7, self.params.get('content_alpha', 0.65)))
                
                # Apply domain-specific adjustments
                category_importance = self.crypto_weights.get("category_correlation", 0.6)
                
                # Adjust alpha if categories are more important in this domain
                if category_importance > 0.5:
                    # Reduce alpha to give more weight to content features
                    alpha = alpha * 0.9
                
                # Blend both similarity matrices
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
        try:
            logger.info(f"Loading FECF model from {filepath}")
            
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
            
            # Precompute cold-start candidates if not already done
            if self._popular_items is None or self._trending_items is None:
                self._precompute_cold_start_candidates()
                
            # Precompute category info if not already done
            if self._category_distributions is None or self._category_item_mapping is None:
                self._precompute_category_info()
                
            logger.info(f"FECF model successfully loaded from {filepath}")
            return True
                
        except Exception as e:
            logger.error(f"Error loading model: {str(e)}")
            # Log traceback untuk debugging
            import traceback
            logger.error(traceback.format_exc())
            return False
        
    def is_trained(self) -> bool:
        if self.model is None:
            return False
        return True
    
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user with enhanced diversity and performance 
        """
        # Check cache for performance
        cache_key = f"{user_id}_{n}_{exclude_known}"
        if cache_key in self._recommendation_cache:
            # Check if cache is still valid (< 1 hour old)
            cache_time, cache_results = self._recommendation_cache[cache_key]
            if time.time() - cache_time < 3600:  # 1 hour in seconds
                return cache_results
        
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
            logger.warning("No items available for recommendation after excluding known items")
            return []
        
        # Get similarity scores in a vectorized way
        item_scores = np.zeros(len(all_items))
        
        # OPTIMIZATION: Pre-calculate all required indices
        try:
            rated_indices = [self._item_mapping[item] for item in positive_indices if item in self._item_mapping]
            candidate_indices = [self._item_mapping[item] for item in all_items if item in self._item_mapping]
            
            # Use vectorized operations for similarity calculation
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
        candidate_size = min(n * 3, 100)
        top_candidates = scores[:candidate_size]
        
        # Ensure category diversity with item metadata
        if hasattr(self, 'projects_df') and 'primary_category' in self.projects_df.columns:
            diversified_results = self._apply_diversity_with_metadata(top_candidates, n)
            
            # Store in cache
            self._recommendation_cache[cache_key] = (time.time(), diversified_results)
            
            return diversified_results
        
        # If no category/chain data available, just return top-n
        return top_candidates[:n]
    
    def _apply_diversity_with_metadata(self, candidates: List[Tuple[str, float]], n: int) -> List[Tuple[str, float]]:
        """Apply diversity to recommendations using project metadata"""
        
        # OPTIMIZATION: Create item metadata lookup only once
        item_metadata = {}
        for _, row in self.projects_df.iterrows():
            if 'id' in row:
                item_id = row['id']
                
                # Extract categories
                categories = []
                if 'categories_list' in row:
                    cats = row['categories_list']
                    if isinstance(cats, list):
                        categories = cats
                    elif isinstance(cats, str) and cats.startswith('[') and cats.endswith(']'):
                        try:
                            categories = eval(cats)
                        except:
                            categories = [cats]
                    else:
                        categories = [cats]
                elif 'primary_category' in row:
                    categories = [row['primary_category']]
                
                # Extract chain and market cap
                chain = row.get('chain', 'unknown')
                market_cap = row.get('market_cap', 0)
                
                # Store metadata
                item_metadata[item_id] = {
                    'categories': categories,
                    'chain': chain,
                    'market_cap': market_cap
                }
        
        # OPTIMIZATION: Select top items unconditionally first
        top_count = max(n // 5, 1)  # ~20% by pure score
        result_items = [item for item, _ in candidates[:top_count]]
        result_scores = [score for _, score in candidates[:top_count]]
        
        # Track selected categories and chains for diversity
        category_counts = {}
        chain_counts = {}
        market_cap_tiers = {'high': 0, 'medium': 0, 'low': 0}
        
        # Market cap thresholds
        high_cap_threshold = 1e9  # $1B
        medium_cap_threshold = 1e8  # $100M
        
        # Initialize tracking
        for item in result_items:
            if item in item_metadata:
                meta = item_metadata[item]
                categories = meta['categories']
                chain = meta['chain']
                market_cap = meta['market_cap']
                
                # Update category counts
                for category in categories:
                    category_counts[category] = category_counts.get(category, 0) + 1
                
                # Update chain counts
                chain_counts[chain] = chain_counts.get(chain, 0) + 1
                
                # Update market cap tier counts
                if market_cap >= high_cap_threshold:
                    market_cap_tiers['high'] += 1
                elif market_cap >= medium_cap_threshold:
                    market_cap_tiers['medium'] += 1
                else:
                    market_cap_tiers['low'] += 1
        
        # Diversity limits - more balanced for crypto
        max_per_category = max(2, int(n * 0.3))  # Max 30% from any category
        max_per_chain = max(3, int(n * 0.4))     # Max 40% from any chain
        
        # Desired distribution for market cap tiers
        target_market_cap_distribution = {
            'high': int(n * 0.4),    # 40% high cap for stability
            'medium': int(n * 0.4),  # 40% medium cap for growth
            'low': int(n * 0.2)      # 20% low cap for speculation
        }
        
        # Remaining candidates
        remaining = candidates[top_count:]
        
        # Calculate diversity adjustments for remaining items
        diversity_adjustments = []
        
        for item_id, score in remaining:
            if item_id in item_metadata:
                meta = item_metadata[item_id]
                categories = meta['categories']
                chain = meta['chain']
                market_cap = meta['market_cap']
                
                # Calculate category adjustment
                category_adjustment = 0
                has_overrepresented_category = False
                has_new_category = True
                
                for category in categories:
                    cat_count = category_counts.get(category, 0)
                    
                    if cat_count >= max_per_category:
                        # Category overrepresented
                        has_overrepresented_category = True
                    elif cat_count == 0:
                        # New category bonus
                        has_new_category = True
                
                if has_overrepresented_category:
                    category_adjustment -= 0.3
                elif has_new_category:
                    category_adjustment += 0.2
                
                # Chain adjustment
                chain_adjustment = 0
                chain_count = chain_counts.get(chain, 0)
                
                if chain_count >= max_per_chain:
                    chain_adjustment -= 0.2
                elif chain_count == 0:
                    chain_adjustment += 0.1
                
                # Market cap adjustment based on tier targets
                market_cap_adjustment = 0
                
                if market_cap >= high_cap_threshold:
                    tier = 'high'
                elif market_cap >= medium_cap_threshold:
                    tier = 'medium'
                else:
                    tier = 'low'
                
                current_count = market_cap_tiers[tier]
                target_count = target_market_cap_distribution[tier]
                
                if current_count >= target_count:
                    # This tier is overrepresented
                    market_cap_adjustment -= 0.2
                else:
                    # This tier is underrepresented
                    fill_ratio = 1.0 - (current_count / target_count if target_count > 0 else 0)
                    market_cap_adjustment += 0.2 * fill_ratio
                
                # Combine all adjustments
                total_adjustment = (
                    category_adjustment * 0.6 +   # Categories most important
                    chain_adjustment * 0.25 +     # Chain secondary importance
                    market_cap_adjustment * 0.15  # Market cap tertiary importance
                )
                
                # Add to diversity adjustments
                diversity_adjustments.append(
                    (item_id, score, score + total_adjustment * 0.5)  # Apply half of adjustment for balance
                )
            else:
                # No metadata, just use original score
                diversity_adjustments.append((item_id, score, score))
        
        # Sort by adjusted score
        diversity_adjustments.sort(key=lambda x: x[2], reverse=True)
        
        # Add remaining items based on adjusted scores
        for item_id, original_score, _ in diversity_adjustments:
            if len(result_items) >= n:
                break
                
            # Skip if already selected
            if item_id in result_items:
                continue
                
            # Add to results
            result_items.append(item_id)
            result_scores.append(original_score)  # Use original score for consistency
            
            # Update tracking
            if item_id in item_metadata:
                meta = item_metadata[item_id]
                categories = meta['categories']
                chain = meta['chain']
                market_cap = meta['market_cap']
                
                # Update category counts
                for category in categories:
                    category_counts[category] = category_counts.get(category, 0) + 1
                
                # Update chain counts
                chain_counts[chain] = chain_counts.get(chain, 0) + 1
                
                # Update market cap tier counts
                if market_cap >= high_cap_threshold:
                    market_cap_tiers['high'] += 1
                elif market_cap >= medium_cap_threshold:
                    market_cap_tiers['medium'] += 1
                else:
                    market_cap_tiers['low'] += 1
        
        # Build final result
        result = list(zip(result_items, result_scores))
        return result
    
    def _get_cold_start_recommendations(self, n: int = 10) -> List[Tuple[str, float]]:
        """
        Enhanced multi-strategy cold-start approach for cryptocurrency domain
        """
        # Check cache for performance
        cache_key = f"cold_start_{n}"
        if cache_key in self._cold_start_cache:
            # Check if cache is still valid (< 1 hour old)
            cache_time, cache_results = self._cold_start_cache[cache_key]
            if time.time() - cache_time < 3600:  # 1 hour in seconds
                return cache_results[:n]  # Return subset if needed
        
        # STRATEGY 1: Use precomputed popular and trending items
        recommendations = []
        
        # IMPROVED: Use balanced approach for crypto cold-start with multiple sources
        
        # 1. Get top trending items (highest priority for crypto)
        if self._trending_items:
            # Get top 40% from trending
            trend_count = max(3, int(n * 0.4))
            
            # Apply additional boost for very trending items
            boosted_trending = []
            for item_id, score in self._trending_items:
                if score > 0.8:  # Very trending (>80)
                    # Apply 20% boost for hot items
                    boosted_trending.append((item_id, min(1.0, score * 1.2)))
                else:
                    boosted_trending.append((item_id, score))
            
            recommendations.extend(boosted_trending[:trend_count])
        
        # 2. Get established projects by market cap (for stability)
        if 'market_cap' in self.projects_df.columns:
            # Get top projects by market cap
            market_cap_df = self.projects_df.sort_values('market_cap', ascending=False)
            
            # Get top 30% by market cap
            cap_count = max(3, int(n * 0.3))
            
            # Only include items not already in recommendations
            recommended_ids = {item[0] for item in recommendations}
            
            for _, row in market_cap_df.iterrows():
                if len(recommendations) >= trend_count + cap_count:
                    break
                    
                item_id = row['id']
                
                if item_id not in recommended_ids:
                    # Normalized score based on market cap position
                    market_cap_score = 0.7  # Slightly lower than trending
                    
                    # Add to recommendations
                    recommendations.append((item_id, market_cap_score))
                    recommended_ids.add(item_id)
        
        # 3. Add category-diverse popular items
        if self._popular_items:
            # Get remaining slots
            remaining_slots = n - len(recommendations)
            
            if remaining_slots > 0:
                # Keep track of categories we've already included
                category_counts = {}
                recommended_ids = {item[0] for item in recommendations}
                
                # First pass: collect category information for current recommendations
                for item_id, _ in recommendations:
                    project = self.projects_df[self.projects_df['id'] == item_id]
                    
                    if not project.empty and 'primary_category' in project.columns:
                        category = project.iloc[0]['primary_category']
                        category_counts[category] = category_counts.get(category, 0) + 1
                
                # Second pass: add diverse popular items
                for item_id, score in self._popular_items:
                    if len(recommendations) >= n:
                        break
                        
                    if item_id not in recommended_ids:
                        project = self.projects_df[self.projects_df['id'] == item_id]
                        
                        if not project.empty and 'primary_category' in project.columns:
                            category = project.iloc[0]['primary_category']
                            
                            # Skip if we already have enough from this category
                            if category_counts.get(category, 0) >= 2 and len(category_counts) > 2:
                                continue
                            
                            # Add to counts
                            category_counts[category] = category_counts.get(category, 0) + 1
                        
                        # Add to recommendations
                        recommendations.append((item_id, score * 0.9))  # Slightly reduce score for diversity
                        recommended_ids.add(item_id)
        
        # Ensure we have enough recommendations
        remaining_slots = n - len(recommendations)
        if remaining_slots > 0 and self.projects_df is not None:
            # Use high popularity as fallback if we still need more
            if 'popularity_score' in self.projects_df.columns:
                popular_df = self.projects_df.sort_values('popularity_score', ascending=False)
                recommended_ids = {item[0] for item in recommendations}
                
                for _, row in popular_df.iterrows():
                    if len(recommendations) >= n:
                        break
                        
                    item_id = row['id']
                    if item_id not in recommended_ids:
                        pop_score = row['popularity_score'] / 100 if row['popularity_score'] <= 100 else 1.0
                        recommendations.append((item_id, pop_score * 0.8))  # Lower priority
                        recommended_ids.add(item_id)
            else:
                # Last resort: use random popular items
                remaining_popular = [item for item in self._popular_items if item[0] not in {rec[0] for rec in recommendations}]
                recommendations.extend(remaining_popular[:remaining_slots])
        
        # Sort by score
        recommendations.sort(key=lambda x: x[1], reverse=True)
        
        # Apply diversity if we have more than needed
        if len(recommendations) > n and 'primary_category' in self.projects_df.columns:
            final_recommendations = self._apply_diversity_with_metadata(recommendations, n)
        else:
            final_recommendations = recommendations[:n]
        
        # Store in cache
        self._cold_start_cache[cache_key] = (time.time(), final_recommendations)
        
        return final_recommendations
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
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
                required_fields = ['id', 'name', 'symbol', 'image', 'current_price', 'market_cap', 
                                'total_volume', 'price_change_24h', 'price_change_percentage_7d_in_currency', 
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
    
    def get_recommendations_by_category(self, user_id: str, category: str, n: int = 10, 
                                  chain: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi yang difilter berdasarkan kategori dengan opsional filter chain
        """
        logger.info(f"Getting category-filtered recommendations for user {user_id}, category={category}, chain={chain}, strict={strict}")
        
        # Get initial recommendations with increased count for filtering
        multiplier = 3  # Get 3x more recommendations to allow for filtering
        recommendations = self.recommend_for_user(user_id, n=n*multiplier)
        
        # Filter projects by category (and chain if provided)
        filtered_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_df_row = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_df_row.empty:
                # Check category match
                category_match = False
                
                # Check different category fields for match
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
                    filtered_recommendations.append((project_id, score))
        
        # Apply diversity with metadata if we have more than needed
        if len(filtered_recommendations) > n:
            filtered_recommendations = self._apply_diversity_with_metadata(filtered_recommendations, n=n)
        
        filtered_count = len(filtered_recommendations)
        logger.info(f"Found {filtered_count} recommendations matching category '{category}'{' and chain ' + chain if chain else ''}")
        
        # If strict mode is enabled, only return exact matches
        if strict:
            # Convert to detailed recommendations
            detailed_recommendations = []
            
            for project_id, score in filtered_recommendations[:n]:
                # Find project data
                project_data = self.projects_df[self.projects_df['id'] == project_id]
                
                if not project_data.empty:
                    # Convert to dictionary
                    project_dict = project_data.iloc[0].to_dict()
                    
                    # Add recommendation score
                    project_dict['recommendation_score'] = float(score)
                    
                    # Add to results
                    detailed_recommendations.append(project_dict)
            
            return detailed_recommendations
        
        # If we have too few recommendations, add some category-based recommendations
        if len(filtered_recommendations) < n // 2:
            logger.warning(f"Too few category-filtered recommendations, adding category-based recommendations")
            
            # Get IDs of already added recommendations
            existing_ids = {rec[0] for rec in filtered_recommendations}
            
            # Find projects with matching category without personalization
            category_projects = []
            
            for _, row in self.projects_df.iterrows():
                category_match = False
                if 'categories_list' in self.projects_df.columns and row['id'] not in existing_ids:
                    cats = row['categories_list']
                    categories_list = self.process_categories(cats)
                    
                    for cat in categories_list:
                        if isinstance(cat, str) and (category.lower() in cat.lower() or cat.lower() in category.lower()):
                            category_match = True
                            break
                
                if not category_match and 'primary_category' in self.projects_df.columns and row['id'] not in existing_ids:
                    if isinstance(row['primary_category'], str) and (
                        category.lower() in row['primary_category'].lower() or 
                        row['primary_category'].lower() in category.lower()
                    ):
                        category_match = True
                
                # Apply chain filter if provided
                chain_match = True  # Default to True if no chain filter
                if chain and 'chain' in self.projects_df.columns:
                    chain_match = row['chain'] and chain.lower() in str(row['chain']).lower()
                
                if category_match and chain_match:
                    # Add some trending bias
                    score = 0.7  # Base score for category match
                    if 'trend_score' in row:
                        score = min(0.9, 0.7 + row['trend_score'] / 200)  # Boost trending items
                    
                    category_projects.append((row['id'], score))
            
            # Sort by score
            category_projects.sort(key=lambda x: x[1], reverse=True)
            
            # Add to recommendations
            for project_id, score in category_projects:
                if len(filtered_recommendations) >= n:
                    break
                    
                if project_id not in existing_ids:
                    filtered_recommendations.append((project_id, score))
                    existing_ids.add(project_id)
        
        # Convert to detailed recommendations
        detailed_recommendations = []
        
        for project_id, score in filtered_recommendations[:n]:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        return detailed_recommendations
    
    def get_recommendations_by_chain(self, user_id: str, chain: str, n: int = 10, category: Optional[str] = None) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi yang difilter berdasarkan blockchain dengan opsional filter kategori
        """
        logger.info(f"Getting chain-filtered recommendations for user {user_id}, chain={chain}, category={category}")
        
        # Get initial recommendations with increased count for filtering
        multiplier = 3  # Get 3x more recommendations to allow for filtering
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
                    
                    # Check different category fields
                    if 'categories_list' in project_df_row.columns:
                        for _, row in project_df_row.iterrows():
                            cats = row['categories_list']
                            categories_list = self.process_categories(cats)
                            
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
                
                # Add to filtered recommendations if both chain and category match
                if chain_match and category_match:
                    filtered_recommendations.append((project_id, score))
        
        # Apply diversity with metadata if we have more than needed
        if len(filtered_recommendations) > n:
            filtered_recommendations = self._apply_diversity_with_metadata(filtered_recommendations, n=n)
        
        # Convert to detailed recommendations
        detailed_recommendations = []
        
        for project_id, score in filtered_recommendations[:n]:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = float(score)
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        logger.info(f"Found {len(detailed_recommendations)} recommendations matching chain '{chain}'{' and category ' + category if category else ''}")
        
        # If we have too few recommendations, add some chain-based recommendations
        if len(detailed_recommendations) < n // 2:
            logger.warning("Too few chain-filtered recommendations, adding chain-based recommendations")
            
            # Get IDs of already added recommendations
            existing_ids = {rec['id'] for rec in detailed_recommendations}
            
            # Find projects with matching chain without personalization
            chain_projects = []
            
            for _, row in self.projects_df.iterrows():
                if row['id'] not in existing_ids and 'chain' in self.projects_df.columns:
                    # Check chain match
                    chain_match = row['chain'] and chain.lower() in str(row['chain']).lower()
                    
                    # Apply category filter if provided
                    category_match = True  # Default to True if no category filter
                    if category:
                        category_match = False
                        
                        if 'categories_list' in self.projects_df.columns:
                            cats = row['categories_list']
                            categories_list = self.process_categories(cats)
                            
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
                    
                    if chain_match and category_match:
                        # Add some popularity/trending bias
                        score = 0.7  # Base score for chain match
                        
                        if 'trend_score' in row:
                            score = min(0.9, 0.7 + row['trend_score'] / 200)  # Boost trending items
                        elif 'popularity_score' in row:
                            score = min(0.85, 0.7 + row['popularity_score'] / 300)  # Lesser boost for popular items
                        
                        chain_projects.append((row['id'], score))
            
            # Sort by score
            chain_projects.sort(key=lambda x: x[1], reverse=True)
            
            # Add to recommendations
            for project_id, score in chain_projects:
                if len(detailed_recommendations) >= n:
                    break
                    
                # Find project data
                project_data = self.projects_df[self.projects_df['id'] == project_id]
                
                if not project_data.empty:
                    # Convert to dictionary
                    project_dict = project_data.iloc[0].to_dict()
                    
                    # Add recommendation score
                    project_dict['recommendation_score'] = float(score)
                    
                    # Mark as chain-based recommendation
                    project_dict['recommendation_source'] = 'chain-based'
                    
                    # Add to results
                    detailed_recommendations.append(project_dict)
        
        return detailed_recommendations
    
    def get_recommendations_by_category_and_chain(self, user_id: str, category: str, chain: str, n: int = 10, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi berdasarkan kategori dan chain secara bersamaan
        """
        logger.info(f"Getting recommendations filtered by both category '{category}' and chain '{chain}' for user {user_id}")
        
        # Using category filter with chain parameter is more efficient
        return self.get_recommendations_by_category(user_id, category, n=n, chain=chain, strict=strict)
    
    def get_similar_projects(self, project_id: str, n: int = 10) -> List[Dict[str, Any]]:
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
                required_fields = ['id', 'name', 'symbol', 'image', 'current_price', 'market_cap', 
                                'total_volume', 'price_change_24h', 'price_change_percentage_7d_in_currency', 
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
    
    def get_cold_start_recommendations(self, user_interests: Optional[List[str]] = None, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get cold-start recommendations with optional user interests
        """
        # Mendapatkan rekomendasi dalam bentuk (project_id, score) tuples
        if user_interests:
            # Custom recommendations based on interests
            recommendations = self._get_interest_based_recommendations(user_interests, n)
        else:
            # Generic cold-start recommendations
            recommendations = self._get_cold_start_recommendations(n)
        
        # Konversi ke bentuk dictionary
        detailed_recommendations = []
        for project_id, score in recommendations[:n]:
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
    
    def _get_interest_based_recommendations(self, user_interests: List[str], n: int = 10) -> List[Tuple[str, float]]:
        """
        Generate cold-start recommendations based on user interests
        """
        # Normalize interests to lowercase for better matching
        normalized_interests = [interest.lower() for interest in user_interests]
        
        # Track selected categories to ensure diversity
        selected_categories = {}
        recommendations = []
        
        # Max items per category
        max_per_category = max(2, n // len(normalized_interests) if normalized_interests else n // 3)
        
        # First pass: Find direct matches for each interest
        for interest in normalized_interests:
            match_count = 0
            
            # Try to find exact category matches
            for category, items in self._category_item_mapping.items():
                if interest in category.lower() or category.lower() in interest:
                    # Match found - get items from this category
                    category_items = items[:max_per_category*2]  # Get more for filtering
                    
                    # Get associated projects
                    for item_id in category_items:
                        if item_id not in [rec[0] for rec in recommendations]:
                            # Get project data
                            project_data = self.projects_df[self.projects_df['id'] == item_id]
                            
                            if not project_data.empty and match_count < max_per_category:
                                # Create recommendation with boosted score
                                recommendations.append((item_id, 0.85))  # High score for direct match
                                
                                # Track category to ensure diversity
                                selected_categories[category] = selected_categories.get(category, 0) + 1
                                
                                match_count += 1
                                
                                # Break if we have enough from this category
                                if match_count >= max_per_category:
                                    break
                                    
            # If no direct matches, try partial matches
            if match_count == 0:
                # Try partial matching with a more flexible approach
                potential_categories = []
                
                for category in self._category_distributions.keys():
                    # Check for token overlap in words
                    interest_tokens = set(interest.lower().split())
                    category_tokens = set(category.lower().split('-'))
                    
                    # Check for any overlap
                    if interest_tokens.intersection(category_tokens):
                        potential_categories.append(category)
                
                # Use the potential categories
                for category in potential_categories[:3]:  # Limit to top 3 potential categories
                    if category in self._category_item_mapping:
                        items = self._category_item_mapping[category][:max_per_category]
                        
                        for item_id in items:
                            if item_id not in [rec[0] for rec in recommendations]:
                                # Lower score for partial match
                                recommendations.append((item_id, 0.75))
                                
                                # Track category
                                selected_categories[category] = selected_categories.get(category, 0) + 1
                                
                                match_count += 1
                                
                                # Limit number of partial matches
                                if match_count >= max_per_category // 2:
                                    break
        
        # Second pass: Fill remaining slots with trending and popular items
        remaining_slots = n - len(recommendations)
        
        if remaining_slots > 0:
            # Use trending items first
            if self._trending_items:
                trending_count = min(remaining_slots, max(2, remaining_slots // 2))
                trending_candidates = [item for item in self._trending_items 
                                    if item[0] not in [rec[0] for rec in recommendations]]
                
                recommendations.extend(trending_candidates[:trending_count])
            
            # Then fill with popular items if needed
            remaining_slots = n - len(recommendations)
            if remaining_slots > 0 and self._popular_items:
                popular_candidates = [item for item in self._popular_items 
                                    if item[0] not in [rec[0] for rec in recommendations]]
                
                recommendations.extend(popular_candidates[:remaining_slots])
        
        # Final pass: Ensure category diversity
        if 'primary_category' in self.projects_df.columns and len(recommendations) > n:
            # Apply diversity logic
            diverse_recommendations = self._apply_diversity_with_metadata(recommendations, n)
            return diverse_recommendations
        
        # Sort by score and return
        recommendations.sort(key=lambda x: x[1], reverse=True)
        return recommendations[:n]
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get trending crypto projects with enhanced diversity
        """
        if 'trend_score' in self.projects_df.columns:
            # Sort by trend score
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*2)
            
            # Ensure category diversity
            if 'primary_category' in trending.columns or 'categories_list' in trending.columns:
                # Apply diversity directly
                trend_tuples = [(row['id'], row['trend_score']/100) for _, row in trending.iterrows()]
                diversified = self._apply_diversity_with_metadata(trend_tuples, n)
                
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
        """
        Get popular crypto projects with enhanced diversity and ranking
        """
        if 'popularity_score' in self.projects_df.columns and 'trend_score' in self.projects_df.columns:
            # IMPROVED: Create balanced score with market cap influence
            df = self.projects_df.copy()
            
            # Create normalized scores
            df['popularity_normalized'] = df['popularity_score'] / 100 
            df['trend_normalized'] = df['trend_score'] / 100
            
            # Create combined score with domain-specific weights
            trend_weight = self.crypto_weights.get("trend_importance", 0.7)
            df['combined_score'] = (
                df['popularity_normalized'] * (1 - trend_weight) + 
                df['trend_normalized'] * trend_weight
            ) * 100
            
            # Apply market cap bonus for reliable projects
            if 'market_cap' in df.columns and df['market_cap'].max() > 0:
                # Calculate percentile rank of market cap
                df['market_cap_rank'] = df['market_cap'].rank(pct=True)
                
                # Apply smaller bonus for market cap to avoid over-dominance of large caps
                market_cap_boost = self.crypto_weights.get("market_cap_influence", 0.4)
                df['combined_score'] += df['market_cap_rank'] * 20 * market_cap_boost
            
            # Sort by combined score
            popular = df.sort_values('combined_score', ascending=False).head(n*2)
            
            # Apply diversity to popular projects
            popular_tuples = [(row['id'], row['combined_score']/100) for _, row in popular.iterrows()]
            diversified = self._apply_diversity_with_metadata(popular_tuples, n)
            
            # Convert back to dictionaries
            result = []
            for item_id, score in diversified:
                project_data = df[df['id'] == item_id]
                if not project_data.empty:
                    project_dict = project_data.iloc[0].to_dict()
                    project_dict['recommendation_score'] = float(score)
                    result.append(project_dict)
            
            return result[:n]
        elif 'popularity_score' in self.projects_df.columns:
            # Just use popularity score
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n*2)
            
            # Apply diversity
            popular_tuples = [(row['id'], row['popularity_score']/100) for _, row in popular.iterrows()]
            diversified = self._apply_diversity_with_metadata(popular_tuples, n)
            
            # Convert to dictionaries
            result = []
            for item_id, score in diversified:
                project_data = self.projects_df[self.projects_df['id'] == item_id]
                if not project_data.empty:
                    project_dict = project_data.iloc[0].to_dict()
                    project_dict['recommendation_score'] = float(score)
                    result.append(project_dict)
            
            return result[:n]
        elif 'market_cap' in self.projects_df.columns:
            # Use market cap as fallback
            popular = self.projects_df.sort_values('market_cap', ascending=False).head(n*2)
            
            # Apply diversity
            if popular['market_cap'].max() > 0:
                popular_tuples = [(row['id'], row['market_cap']/popular['market_cap'].max() * 0.9) 
                                for _, row in popular.iterrows()]
            else:
                popular_tuples = [(row['id'], 0.5) for _, row in popular.iterrows()]
            
            diversified = self._apply_diversity_with_metadata(popular_tuples, n)
            
            # Convert to dictionaries
            result = []
            for item_id, score in diversified:
                project_data = self.projects_df[self.projects_df['id'] == item_id]
                if not project_data.empty:
                    project_dict = project_data.iloc[0].to_dict()
                    project_dict['recommendation_score'] = float(score)
                    result.append(project_dict)
            
            return result[:n]
        else:
            # Just return random selection with diversity
            random_tuples = [(row['id'], 0.5) for _, row in self.projects_df.sample(min(n*2, len(self.projects_df))).iterrows()]
            diversified = self._apply_diversity_with_metadata(random_tuples, n)
            
            # Convert to dictionaries
            result = []
            for item_id, score in diversified:
                project_data = self.projects_df[self.projects_df['id'] == item_id]
                if not project_data.empty:
                    project_dict = project_data.iloc[0].to_dict()
                    project_dict['recommendation_score'] = float(score)
                    result.append(project_dict)
            
            return result[:n]


if __name__ == "__main__":
    # Testing the module
    fecf = FeatureEnhancedCF()
    
    # Load data
    if fecf.load_data():
        # Train model
        metrics = fecf.train(save_model=True)
        print(f"Training metrics: {metrics}")
        
        # Test recommendations
        if fecf.user_item_matrix is not None and not fecf.user_item_matrix.empty:
            test_user = fecf.user_item_matrix.index[0]
            print(f"\nFECF recommendations for user {test_user}:")
            recs = fecf.recommend_projects(test_user, n=5)
            
            for i, rec in enumerate(recs, 1):
                print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
                
        # Test cold-start recommendations
        print("\nCold-start recommendations:")
        cold_start = fecf.get_cold_start_recommendations(n=5)
        
        for i, rec in enumerate(cold_start, 1):
            print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
            
        # Test trending projects
        print("\nTrending projects:")
        trending = fecf.get_trending_projects(n=5)
        
        for i, proj in enumerate(trending, 1):
            print(f"{i}. {proj.get('name', proj.get('id'))} - Score: {proj.get('recommendation_score', 0):.4f}")
    else:
        print("Failed to load data")