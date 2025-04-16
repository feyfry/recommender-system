"""
Feature-Enhanced Collaborative Filtering menggunakan LightFM
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

from lightfm import LightFM
from lightfm.data import Dataset
from scipy.sparse import csr_matrix, dok_matrix
from sklearn.metrics.pairwise import cosine_similarity

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
    Implementation of Feature-Enhanced Collaborative Filtering using LightFM
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
        self.dataset = None
        self._user_features = None
        self._item_features = None
        self._user_mapping = None
        self._item_mapping = None
        self._reverse_user_mapping = None
        self._reverse_item_mapping = None
        
        # Project data
        self.projects_df = None
        self.user_item_matrix = None
        self.interactions_df = None
        self.features_df = None
    
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
            else:
                logger.error(f"Interactions file not found: {interactions_path}")
                return False
                
            # Load features data
            if os.path.exists(features_path):
                self.features_df = pd.read_csv(features_path)
                logger.info(f"Loaded features with shape {self.features_df.shape} from {features_path}")
            else:
                logger.warning(f"Features file not found: {features_path}. Will use limited features.")
                # Create simple features from projects data
                self.features_df = self.projects_df[['id']].copy()
                
            return True
            
        except Exception as e:
            logger.error(f"Error loading data: {str(e)}")
            return False
    
    def _prepare_dataset(self) -> bool:
        """
        Prepare LightFM dataset with user and item features
        
        Returns:
            bool: Success status
        """
        try:
            logger.info("Preparing LightFM dataset")
            
            # Create LightFM dataset
            self.dataset = Dataset()
            
            # Pastikan user_item_matrix ada dan tidak kosong
            if self.user_item_matrix is None or len(self.user_item_matrix) == 0:
                logger.error("User-item matrix is empty or not loaded")
                return False
            
            # PENTING: Pastikan daftar user dan item dalam format string
            users = [str(user) for user in self.user_item_matrix.index]
            items = [str(item) for item in self.user_item_matrix.columns]
            
            # Check if empty
            if not users or not items:
                logger.error("No users or items found in user-item matrix")
                return False
            
            # Fit dataset dengan user dan item
            self.dataset.fit(
                users=users,
                items=items
            )

            # Cetak beberapa sampel user untuk debugging
            logger.info(f"Sample users: {users[:5]}")
            logger.info(f"Sample items: {items[:5]}")
            
            # Extract feature names for items
            item_features = []
            
            # Add project categories as features
            if 'primary_category' in self.projects_df.columns:
                categories = self.projects_df['primary_category'].dropna().unique()
                item_features.extend([f"category:{cat}" for cat in categories if pd.notna(cat)])
                
            # Add project blockchains as features
            if 'chain' in self.projects_df.columns:
                chains = self.projects_df['chain'].dropna().unique()
                item_features.extend([f"chain:{chain}" for chain in chains if pd.notna(chain)])
                
            # Add price range features
            if 'price_usd' in self.projects_df.columns:
                price_ranges = ["low_price", "medium_price", "high_price", "very_high_price"]
                item_features.extend([f"price:{price}" for price in price_ranges])
                
            # Add market cap features
            if 'market_cap' in self.projects_df.columns:
                cap_ranges = ["micro_cap", "small_cap", "mid_cap", "large_cap", "mega_cap"]
                item_features.extend([f"cap:{cap}" for cap in cap_ranges])
                
            # Add trend features
            if 'trend_score' in self.projects_df.columns:
                trend_ranges = ["bearish", "neutral", "bullish", "strong_bullish"]
                item_features.extend([f"trend:{trend}" for trend in trend_ranges])
                
            # Pastikan ada features
            if not item_features:
                logger.warning("No item features found, using basic features only")
                item_features = ["default_feature"]
            
            # Fit features dengan eksplisit fitting untuk semua items
            try:
                self.dataset.fit_partial(
                    items=items,
                    item_features=item_features
                )
            except ValueError as e:
                logger.error(f"Error fitting features: {e}")
                # Coba lagi dengan feature yang aman
                self.dataset = Dataset()
                self.dataset.fit(users=users, items=items)
                self.dataset.fit_partial(
                    items=items,
                    item_features=["default_feature"]
                )
            
            # Get mapping dictionaries
            mappings = self.dataset.mapping()
            if len(mappings) >= 4:
                self._user_mapping = mappings[0]
                self._item_mapping = mappings[2]
                
                # Create reverse mappings for convenience
                self._reverse_user_mapping = {v: k for k, v in self._user_mapping.items()}
                self._reverse_item_mapping = {v: k for k, v in self._item_mapping.items()}
                
                logger.info(f"Prepared dataset with {len(users)} users and {len(items)} items")
                logger.info(f"Using {len(item_features)} item features")
                
                return True
            else:
                logger.error("Invalid mappings from dataset")
                return False
                
        except Exception as e:
            logger.error(f"Error preparing dataset: {str(e)}")
            return False
    
    def _build_interaction_matrix(self) -> csr_matrix:
        """
        Build sparse interaction matrix
        
        Returns:
            csr_matrix: Sparse interaction matrix
        """
        logger.info("Building interaction matrix")
        
        # Log statistik untuk debugging
        total_interactions = len(self.interactions_df)
        valid_interactions = 0
        skipped_interactions = 0
        
        # Build interactions from interactions_df, skip any user/item not in mapping
        interaction_tuples = []
        
        # Proses setiap interaksi
        for _, row in self.interactions_df.iterrows():
            user_id = str(row['user_id'])  # Pastikan string
            project_id = str(row['project_id'])  # Pastikan string
            weight = float(row['weight'])
            
            # Hanya tambahkan jika user dan project ada dalam mapping
            if user_id in self._user_mapping and project_id in self._item_mapping:
                user_idx = self._user_mapping[user_id]
                item_idx = self._item_mapping[project_id]
                interaction_tuples.append((user_idx, item_idx, weight))
                valid_interactions += 1
            else:
                skipped_interactions += 1
                # Skip tapi jangan log semua untuk menghindari spam
                if skipped_interactions < 10:
                    if user_id not in self._user_mapping:
                        logger.warning(f"User ID {user_id} tidak ditemukan dalam mapping")
                    if project_id not in self._item_mapping:
                        logger.warning(f"Project ID {project_id} tidak ditemukan dalam mapping")
        
        # Log statistik
        logger.info(f"Total interactions: {total_interactions}")
        logger.info(f"Valid interactions: {valid_interactions}")
        logger.info(f"Skipped interactions: {skipped_interactions}")
        
        # Check if we have any valid interactions
        if not interaction_tuples:
            raise ValueError("No valid interactions found after mapping. Check your data.")
        
        # Build interactions using dataset
        interactions = self.dataset.build_interactions(interaction_tuples)
        
        return interactions
    
    def _build_item_features(self) -> csr_matrix:
        """
        Build item features matrix
        
        Returns:
            csr_matrix: Item features
        """
        # Initialize item features dict
        item_features_dict = {}
        
        # Process each project
        for _, project in self.projects_df.iterrows():
            project_id = project['id']
            
            # Skip if project not in mapping
            if project_id not in self._item_mapping:
                continue
                
            # Get internal item ID
            item_idx = self._item_mapping[project_id]
            
            # Add category feature
            if 'primary_category' in project and pd.notna(project['primary_category']):
                category = project['primary_category']
                feature_name = f"category:{category}"
                item_features_dict[(item_idx, feature_name)] = 1.0
                
            # Add chain feature
            if 'chain' in project and pd.notna(project['chain']):
                chain = project['chain']
                feature_name = f"chain:{chain}"
                item_features_dict[(item_idx, feature_name)] = 1.0
                
            # Add price feature
            if 'price_usd' in project and pd.notna(project['price_usd']):
                price = project['price_usd']
                if price < 1:
                    price_range = "low_price"
                elif price < 100:
                    price_range = "medium_price"
                elif price < 1000:
                    price_range = "high_price"
                else:
                    price_range = "very_high_price"
                    
                feature_name = f"price:{price_range}"
                item_features_dict[(item_idx, feature_name)] = 1.0
                
            # Add market cap feature
            if 'market_cap' in project and pd.notna(project['market_cap']):
                market_cap = project['market_cap']
                if market_cap < 10_000_000:  # $10M
                    cap_range = "micro_cap"
                elif market_cap < 100_000_000:  # $100M
                    cap_range = "small_cap"
                elif market_cap < 1_000_000_000:  # $1B
                    cap_range = "mid_cap"
                elif market_cap < 10_000_000_000:  # $10B
                    cap_range = "large_cap"
                else:
                    cap_range = "mega_cap"
                    
                feature_name = f"cap:{cap_range}"
                item_features_dict[(item_idx, feature_name)] = 1.0
                
            # Add trend feature
            if 'trend_score' in project and pd.notna(project['trend_score']):
                trend = project['trend_score']
                if trend < 30:
                    trend_range = "bearish"
                elif trend < 50:
                    trend_range = "neutral"
                elif trend < 70:
                    trend_range = "bullish"
                else:
                    trend_range = "strong_bullish"
                    
                feature_name = f"trend:{trend_range}"
                item_features_dict[(item_idx, feature_name)] = 1.0
        
        # Build item features
        item_features = self.dataset.build_item_features(
            item_features_dict, normalize=True
        )
        
        return item_features
    
    def train(self, save_model: bool = True) -> Dict[str, float]:
        """
        Train the Feature-Enhanced CF model
        
        Args:
            save_model: Whether to save the model after training
            
        Returns:
            dict: Training metrics
        """
        start_time = time.time()
        logger.info("Training Feature-Enhanced CF model")
        
        # Prepare dataset
        dataset_prepared = self._prepare_dataset()
        if not dataset_prepared:
            logger.error("Failed to prepare dataset for training")
            # Return empty metrics instead of crashing
            return {"error": "dataset_preparation_failed", "training_time": 0.0}
        
        try:
            # Build interaction matrix and features
            interactions, user_map, item_map = self._build_interaction_matrix()
            item_features = self._build_item_features()
            
            # Save item features for prediction
            self._item_features = item_features
            
            # Ensure there are interactions to train on
            if interactions.data.shape[0] == 0:
                logger.error("No interactions found for training")
                return {"error": "no_interactions", "training_time": 0.0}
            
            # Initialize model
            self.model = LightFM(
                no_components=self.params['no_components'],
                learning_rate=self.params['learning_rate'],
                loss=self.params['loss'],
                max_sampled=self.params['max_sampled']
            )
            
            # Train model
            logger.info(f"Training LightFM with {self.params['epochs']} epochs")
            self.model.fit(
                interactions=interactions,
                item_features=item_features,
                epochs=self.params['epochs'],
                verbose=True
            )
            
            training_time = time.time() - start_time
            metrics = {"training_time": training_time}
            
            logger.info(f"Model training completed in {training_time:.2f} seconds")
            
            # Save model if requested
            if save_model:
                self.save_model()
                
            return metrics
        except Exception as e:
            logger.error(f"Error during FECF training: {str(e)}")
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
            'dataset': self.dataset,
            'user_mapping': self._user_mapping,
            'item_mapping': self._item_mapping,
            'reverse_user_mapping': self._reverse_user_mapping,
            'reverse_item_mapping': self._reverse_item_mapping,
            'item_features': self._item_features,
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
            with open(filepath, 'rb') as f:
                model_state = pickle.load(f)
                
            self.model = model_state['model']
            self.dataset = model_state['dataset'] 
            self._user_mapping = model_state['user_mapping']
            self._item_mapping = model_state['item_mapping']
            self._reverse_user_mapping = model_state['reverse_user_mapping']
            self._reverse_item_mapping = model_state['reverse_item_mapping']
            self._item_features = model_state.get('item_features', None)
            self.params = model_state.get('params', self.params)
            
            logger.info(f"Model loaded from {filepath}")
            return True
            
        except Exception as e:
            logger.error(f"Error loading model: {str(e)}")
            return False
    
    def recommend_for_user(self, user_id: str, n: int = 10, 
                         exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        Generate recommendations for a user
        
        Args:
            user_id: User ID
            n: Number of recommendations
            exclude_known: Whether to exclude already interacted items
            
        Returns:
            list: List of (project_id, score) tuples
        """
        if self.model is None:
            logger.error("Model not trained or loaded")
            return []
            
        # Check if user exists in the dataset
        if user_id not in self._user_mapping:
            logger.warning(f"User {user_id} not found in the model")
            return []
            
        # Get internal user ID
        user_idx = self._user_mapping[user_id]
        
        # Get known items to exclude
        known_items = set()
        if exclude_known and self.user_item_matrix is not None:
            if user_id in self.user_item_matrix.index:
                user_interactions = self.user_item_matrix.loc[user_id]
                known_items = set(user_interactions[user_interactions > 0].index)
                
        # Generate predictions
        n_items = len(self._item_mapping)
        item_ids = np.arange(n_items)
        
        # Get scores
        scores = self.model.predict(
            user_ids=[user_idx] * n_items,
            item_ids=item_ids,
            item_features=self._item_features
        )
        
        # Create (internal_item_id, score) pairs and sort by score
        item_scores = list(zip(item_ids, scores))
        item_scores.sort(key=lambda x: x[1], reverse=True)
        
        # Convert back to original item IDs and filter known items
        recommendations = []
        for item_idx, score in item_scores:
            if len(recommendations) >= n:
                break
                
            item_id = self._reverse_item_mapping[item_idx]
            
            if item_id in known_items:
                continue
                
            recommendations.append((item_id, float(score)))
            
        return recommendations
    
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
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add recommendation score
                project_dict['recommendation_score'] = score
                
                # Add to results
                detailed_recommendations.append(project_dict)
                
        return detailed_recommendations
    
    def get_similar_projects(self, project_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Find similar projects based on features
        
        Args:
            project_id: Project ID
            n: Number of similar projects to return
            
        Returns:
            list: List of similar project dictionaries with similarity scores
        """
        if self.model is None:
            logger.error("Model not trained or loaded")
            return []
            
        # Check if project exists in the dataset
        if project_id not in self._item_mapping:
            logger.warning(f"Project {project_id} not found in the model")
            return []
            
        # Get internal item ID
        item_idx = self._item_mapping[project_id]
        
        # Get item factors
        item_biases, item_factors = self.model.get_item_representations(features=self._item_features)
        
        # Get the factors for the target project
        target_factors = item_factors[item_idx]
        
        # Compute similarity with all other items
        similarities = cosine_similarity([target_factors], item_factors)[0]
        
        # Create (internal_item_id, similarity) pairs and sort by similarity
        item_similarities = list(zip(range(len(similarities)), similarities))
        item_similarities.sort(key=lambda x: x[1], reverse=True)
        
        # Skip the first one (it's the item itself)
        item_similarities = item_similarities[1:n+1]
        
        # Convert to original item IDs and get project data
        similar_projects = []
        
        for item_idx, similarity in item_similarities:
            item_id = self._reverse_item_mapping[item_idx]
            
            # Find project data
            project_data = self.projects_df[self.projects_df['id'] == item_id]
            
            if not project_data.empty:
                # Convert to dictionary
                project_dict = project_data.iloc[0].to_dict()
                
                # Add similarity score
                project_dict['similarity_score'] = float(similarity)
                
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
                result.append(project_dict)
                
            return result
        else:
            # Just return top n projects if no scores available
            return filtered_projects.head(n).to_dict('records')
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get trending projects based on trend score
        
        Args:
            n: Number of trending projects to return
            
        Returns:
            list: List of trending project dictionaries
        """
        if 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n)
            result = []
            
            for _, project in trending.iterrows():
                project_dict = project.to_dict()
                project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0))
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
                project_dict = project.to_dict()
                project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
                result.append(project_dict)
                
            return result
        elif 'market_cap' in self.projects_df.columns:
            # Use market cap as fallback
            popular = self.projects_df.sort_values('market_cap', ascending=False).head(n)
            return popular.to_dict('records')
        else:
            # Just return first n projects
            return self.projects_df.head(n).to_dict('records')


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
            print(f"\nRecommendations for user {test_user}:")
            recs = fecf.recommend_projects(test_user, n=5)
            
            for i, rec in enumerate(recs, 1):
                print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
                
        # Test similar projects
        if fecf.projects_df is not None and not fecf.projects_df.empty:
            test_project = fecf.projects_df.iloc[0]['id']
            print(f"\nSimilar projects to {test_project}:")
            similar = fecf.get_similar_projects(test_project, n=5)
            
            for i, project in enumerate(similar, 1):
                print(f"{i}. {project.get('name', project.get('id'))} - Similarity: {project.get('similarity_score', 0):.4f}")
    else:
        print("Failed to load data for FECF model")