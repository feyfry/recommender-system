"""
Alternative Feature-Enhanced CF menggunakan scikit-learn Matrix Factorization
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
from config import FECF_PARAMS, MODELS_DIR, PROCESSED_DIR

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class FeatureEnhancedCF:
    """
    Implementation of Feature-Enhanced CF using scikit-learn
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
        Create item features matrix from features dataframe
        
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
        
        # CRITICAL FIX: Ensure all items in user_item_matrix are in the features
        all_items = set(self.user_item_matrix.columns)
        available_items = set(item_features.index)
        
        # Check for missing items - this is where the issue happens
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
        
        # Convert to sparse matrix
        features_matrix = csr_matrix(item_features.values)
        
        logger.info(f"Created item features matrix with shape {features_matrix.shape}")
        return features_matrix
    
    def train(self, save_model: bool = True) -> Dict[str, float]:
        """
        Train the Feature-Enhanced CF model using SVD
        
        Args:
            save_model: Whether to save the model after training
            
        Returns:
            dict: Training metrics
        """
        start_time = time.time()
        logger.info("Training Alternative Feature-Enhanced CF model with SVD")
        
        try:
            # Convert user-item matrix to numpy array
            user_item_array = self.user_item_matrix.values
            
            # Apply SVD
            n_components = self.params.get('no_components', 64)
            logger.info(f"Applying SVD with {n_components} components")
            
            self.model = TruncatedSVD(n_components=n_components, random_state=42)
            item_factors = self.model.fit_transform(user_item_array.T)  # Transpose for item factors
            
            # Compute item-item similarity matrix using item factors
            self.item_similarity_matrix = cosine_similarity(item_factors)
            
            # Optionally enhance with content features
            if self._item_features is not None:
                logger.info("Enhancing with content features")
                content_similarity = cosine_similarity(self._item_features)
                
                # Combine collaborative and content similarities
                alpha = 0.7  # Weight for collaborative filtering
                self.item_similarity_matrix = (
                    alpha * self.item_similarity_matrix + 
                    (1 - alpha) * content_similarity
                )
            
            training_time = time.time() - start_time
            metrics = {"training_time": training_time}
            
            logger.info(f"Model training completed in {training_time:.2f} seconds")
            
            # Save model if requested
            if save_model:
                self.save_model()
                
            return metrics
        except Exception as e:
            logger.error(f"Error during training: {str(e)}")
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
    
    def recommend_for_user(self, user_id: str, n: int = 10, 
                 exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user with improved debugging
        and score normalization
        
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
            
        # Get user's ratings
        user_ratings = self.user_item_matrix.loc[user_id]
        
        # Get known items to exclude
        known_items = set()
        if exclude_known:
            known_items = set(user_ratings[user_ratings > 0].index)
            logger.debug(f"User {user_id} has {len(known_items)} known items to exclude")
            
        # Get all items
        all_items = list(self.user_item_matrix.columns)
        
        # Calculate scores for all items
        scores = []
        for item_id in all_items:
            if item_id in known_items and exclude_known:
                continue
                
            # Find item index
            if item_id not in self._item_mapping:
                continue
                
            item_idx = self._item_mapping[item_id]
            
            # Calculate weighted sum of similarities with user's rated items
            score = 0
            for rated_item, rating in user_ratings[user_ratings > 0].items():
                if rated_item not in self._item_mapping:
                    continue
                    
                rated_idx = self._item_mapping[rated_item]
                similarity = self.item_similarity_matrix[item_idx, rated_idx]
                score += similarity * rating
                
            scores.append((item_id, score))
            
        # Sort by score
        scores.sort(key=lambda x: x[1], reverse=True)
        
        # No scores generated
        if not scores:
            logger.warning(f"No recommendations generated for user {user_id}")
            return []
            
        logger.debug(f"Generated {len(scores)} candidates for user {user_id}")
        
        # NORMALISASI: Map the raw scores to a 0-1 range
        top_scores = scores[:n*3]  # Take 3x needed to ensure sufficient variety
        
        # Find min and max scores for normalization
        min_score = min([s for _, s in top_scores])
        max_score = max([s for _, s in top_scores])
        score_range = max(0.001, max_score - min_score)  # Avoid division by zero
        
        # Apply min-max normalization and sigmoid transformation
        normalized_scores = []
        for item_id, raw_score in top_scores:
            # Min-max normalization to 0-1 scale
            norm_score = (raw_score - min_score) / score_range
            
            # Apply sigmoid-like transformation to enhance differences between scores
            # This creates better separation between good and great recommendations
            sigmoid_score = 1.0 / (1.0 + np.exp(-5 * (norm_score - 0.5)))
            
            normalized_scores.append((item_id, sigmoid_score))
        
        # Re-sort with normalized scores
        normalized_scores.sort(key=lambda x: x[1], reverse=True)
        
        # Return top n with normalized scores
        return normalized_scores[:n]
    
    def _get_cold_start_recommendations(self, n: int = 10) -> List[Tuple[str, float]]:
        """
        Get recommendations for cold-start users
        
        Args:
            n: Number of recommendations
            
        Returns:
            list: List of (project_id, score) tuples
        """
        # Return popular items
        if 'popularity_score' in self.projects_df.columns:
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n)
            return [(row['id'], row['popularity_score']) for _, row in popular.iterrows()]
        else:
            # Return random items
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
                    # Try to find projects in the same category
                    target_project = self.projects_df[self.projects_df['id'] == project_id].iloc[0]
                    if 'primary_category' in target_project:
                        category = target_project['primary_category']
                        logger.info(f"Finding projects in the same category: {category}")
                        category_projects = self.projects_df[self.projects_df['primary_category'] == category]
                        if len(category_projects) > 0:
                            result = []
                            for _, project in category_projects.head(n).iterrows():
                                project_dict = project.to_dict()
                                project_dict['similarity_score'] = 0.8  # Reasonable default
                                project_dict['recommendation_score'] = 0.8  # For API consistency
                                result.append(project_dict)
                            return result
                
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
        
        # Get similarity scores for all items
        item_similarities = []
        for other_id, other_idx in self._item_mapping.items():
            if other_id == project_id:
                continue
                
            similarity = self.item_similarity_matrix[item_idx, other_idx]
            item_similarities.append((other_id, similarity))
            
        # Sort by similarity
        item_similarities.sort(key=lambda x: x[1], reverse=True)
        
        # Return top n
        similar_projects = []
        for other_id, similarity in item_similarities[:n]:
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == other_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add similarity score
                project_dict['similarity_score'] = float(similarity)
                project_dict['recommendation_score'] = float(similarity)  # For API consistency
                
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
                similar_projects.append(project_dict)
                    
        return similar_projects
    
    def get_cold_start_recommendations(self, 
                                 user_interests: Optional[List[str]] = None,
                                 n: int = 10) -> List[Dict[str, Any]]:
        """
        Get recommendations for cold-start users based on interests
        
        Args:
            user_interests: List of categories/interests
            n: Number of recommendations
            
        Returns:
            list: List of project dictionaries with recommendation scores
        """
        # Filter projects by categories if interests are provided
        if user_interests and 'primary_category' in self.projects_df.columns:
            # Filter projects by category
            filtered_projects = self.projects_df[
                self.projects_df['primary_category'].isin(user_interests)
            ]
            
            # If no projects match the interests, use all projects
            if len(filtered_projects) < n:
                filtered_projects = self.projects_df
        else:
            # Use popularity for cold-start
            filtered_projects = self.projects_df
        
        # Sort by popularity and trend scores
        if 'popularity_score' in filtered_projects.columns and 'trend_score' in filtered_projects.columns:
            # Combine popularity and trend for ranking
            filtered_projects['combined_score'] = (
                filtered_projects['popularity_score'] * 0.7 + 
                filtered_projects['trend_score'] * 0.3
            )
            
            # Sort by combined score
            recommendations = filtered_projects.sort_values('combined_score', ascending=False).head(n)
            
            # Create list of dictionaries with recommendation scores
            result = []
            for _, project in recommendations.iterrows():
                project_dict = project.to_dict()
                project_dict['recommendation_score'] = float(project_dict.get('combined_score', 0))
                
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
            # Just return top n projects with minimal fields if no scores available
            projects = filtered_projects.head(n)
            result = []
            
            for _, project in projects.iterrows():
                project_dict = project.to_dict()
                project_dict['recommendation_score'] = 0.5  # Default neutral score
                
                # Ensure image field exists
                if 'image' not in project_dict:
                    project_dict['image'] = None
                    
                result.append(project_dict)
                
            return result
    
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
        Get popular projects based on popularity score
        
        Args:
            n: Number of popular projects to return
            
        Returns:
            list: List of popular project dictionaries
        """
        if 'popularity_score' in self.projects_df.columns:
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n)
            result = []
            
            for _, project in popular.iterrows():
                # Get complete project data
                project_dict = project.to_dict()
                
                # Ensure we add recommendation score for consistency
                project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
                
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