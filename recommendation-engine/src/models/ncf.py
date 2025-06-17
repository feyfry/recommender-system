import os
import logging
import numpy as np
import pandas as pd
from typing import Dict, List, Optional, Tuple, Any, Union
import time
import pickle
import random
import copy
from datetime import datetime
from pathlib import Path

import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import Dataset, DataLoader
from sklearn.preprocessing import LabelEncoder
from sklearn.model_selection import train_test_split

# Path handling
import sys
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import NCF_PARAMS, MODELS_DIR, PROCESSED_DIR

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class NCFDataset(Dataset):
    """
    Dataset untuk Neural Collaborative Filtering dengan sampling negatif yang ditingkatkan
    dan strategi pembobotan yang dioptimalkan untuk domain cryptocurrency
    """
    
    def __init__(self, user_indices: np.ndarray, item_indices: np.ndarray, 
                 ratings: np.ndarray, num_negative: int = 4,
                 item_categories: Optional[Dict[int, List[str]]] = None,
                 item_popularity: Optional[Dict[int, float]] = None,
                 item_trend_scores: Optional[Dict[int, float]] = None,
                 all_items: Optional[np.ndarray] = None,
                 seed: int = 42):
        self.user_indices = user_indices
        self.item_indices = item_indices
        self.ratings = ratings
        self.num_negative = num_negative
        self.item_categories = item_categories
        self.item_popularity = item_popularity
        self.item_trend_scores = item_trend_scores
        self.rng = np.random.RandomState(seed)  # Fixed seed for reproducibility
        
        # Konversi ke tensor
        self.user_indices_tensor = torch.LongTensor(user_indices)
        self.item_indices_tensor = torch.LongTensor(item_indices)
        self.ratings_tensor = torch.FloatTensor(ratings)
        
        # Map untuk negative sampling
        self.user_item_map = self._create_user_item_map()
        
        # Tambahkan user-category map untuk sampling yang lebih cerdas
        self.user_category_map = {}
        if self.item_categories:
            self._create_user_category_map()
        
        # Generate semua unique item indices
        if all_items is not None:
            self.all_items = all_items
        else:
            self.all_items = np.unique(item_indices)
        
        # Generate negative samples di awal untuk konsistensi
        self.neg_samples = self._pregenerate_negative_samples()
        
        # Panjang total dataset: sampel positif + sampel negatif
        self.length = len(ratings) + len(self.neg_samples)

    def _create_user_category_map(self):
        """Buat pemetaan minat kategori pengguna berdasarkan histori interaksi"""
        for user_idx, item_idx in zip(self.user_indices, self.item_indices):
            if user_idx not in self.user_category_map:
                self.user_category_map[user_idx] = {}
                
            if self.item_categories and item_idx in self.item_categories:
                categories = self.item_categories[item_idx]
                if not isinstance(categories, list):
                    categories = [categories]
                    
                for category in categories:
                    if category not in self.user_category_map[user_idx]:
                        self.user_category_map[user_idx][category] = 0
                    self.user_category_map[user_idx][category] += 1

    def _pregenerate_negative_samples(self):
        """
        Generate negative samples dengan strategi sampling yang lebih canggih:
        1. Sampling dari kategori yang mirip (in-category negative sampling)
        2. Anti-popularity sampling untuk lebih merata
        3. Hard negative mining (near-miss examples)
        """
        neg_samples = []
        
        # Pertama, hitung popularitas item berdasarkan frekuensi interaksi
        if self.item_popularity is None:
            item_popularity = np.zeros(len(self.all_items))
            for user_idx in self.user_item_map:
                for item_idx in self.user_item_map[user_idx]:
                    # Cari indeks item dalam all_items array
                    item_pos = np.nonzero(self.all_items == item_idx)[0]
                    if len(item_pos) > 0:
                        item_popularity[item_pos[0]] += 1
            
            # Normalisasi popularitas
            max_pop = item_popularity.max()
            if max_pop > 0:
                item_popularity = item_popularity / max_pop
        else:
            # Use provided popularity if available
            item_popularity = np.array([self.item_popularity.get(item, 0) for item in self.all_items])
        
        # Buat distribusi probabilitas inverse untuk mendorong diversity
        # Formula: 1 - (pop^0.5) * 0.75
        # Ini membuat item populer tetap ada kesempatan, tapi menurunkan dominasinya
        item_probability = 1.0 - (np.power(item_popularity, 0.5) * 0.75)
        item_probability = item_probability / item_probability.sum()
        
        # Prepare untuk hard negative sampling - group items by category
        category_to_items = {}
        if self.item_categories:
            for item_idx in self.all_items:
                if item_idx in self.item_categories:
                    categories = self.item_categories[item_idx]
                    if not isinstance(categories, list):
                        categories = [categories]
                        
                    for category in categories:
                        if category not in category_to_items:
                            category_to_items[category] = []
                        category_to_items[category].append(item_idx)
        
        for i, user_idx in enumerate(self.user_indices):
            # Get items yang sudah diinteraksi
            interacted_items = set(self.user_item_map.get(user_idx, []))
            
            # Dapatkan kategori yang disukai user berdasarkan frekuensi interaksi
            user_preferred_categories = []
            if user_idx in self.user_category_map:
                user_preferred_categories = sorted(
                    self.user_category_map[user_idx].items(), 
                    key=lambda x: x[1], 
                    reverse=True
                )
                user_preferred_categories = [cat for cat, _ in user_preferred_categories]
            
            # Generate negative samples untuk user ini dengan 3 strategi:
            # 1. In-category negatives (40%)
            # 2. Trending negatives (30%)
            # 3. Diverse negatives (30%)
            
            for _ in range(self.num_negative):
                neg_item_idx = None
                
                # Randomly choose sampling strategy
                sampling_strategy = self.rng.choice(['in-category', 'trending', 'diverse'], p=[0.4, 0.3, 0.3])
                
                if sampling_strategy == 'in-category' and user_preferred_categories and category_to_items:
                    # In-category negative sampling
                    for category in user_preferred_categories[:3]:  # Try top 3 categories
                        if category in category_to_items:
                            # Get items in this category
                            cat_items = category_to_items[category]
                            # Filter out already interacted items
                            available_items = [item for item in cat_items if item not in interacted_items]
                            
                            if available_items:
                                # Select random item from same category
                                neg_item_idx = self.rng.choice(available_items)
                                break
                
                elif sampling_strategy == 'trending' and self.item_trend_scores:
                    # Trending negative sampling - focus on trending items
                    # Convert trend scores to probabilities
                    trend_items = []
                    trend_scores = []
                    
                    for item in self.all_items:
                        if item not in interacted_items and item in self.item_trend_scores:
                            trend_items.append(item)
                            trend_scores.append(max(0.1, self.item_trend_scores[item]))
                    
                    if trend_items:
                        # Normalize scores to probabilities
                        trend_probs = np.array(trend_scores)
                        trend_probs = trend_probs / trend_probs.sum()
                        
                        # Sample based on trend scores
                        neg_item_idx = self.rng.choice(trend_items, p=trend_probs)
                
                # If previous strategies failed or we're using diverse strategy, use anti-popularity sampling
                if neg_item_idx is None:
                    # Try anti-popularity sampling a few times
                    for _ in range(5):
                        candidate = self.rng.choice(self.all_items, p=item_probability)
                        if candidate not in interacted_items:
                            neg_item_idx = candidate
                            break
                    
                    # If still no valid item, just pick any non-interacted item
                    if neg_item_idx is None:
                        available_items = np.array([item for item in self.all_items if item not in interacted_items])
                        if len(available_items) > 0:
                            neg_item_idx = self.rng.choice(available_items)
                        else:
                            # Last resort - pick any item (could be duplicate in extreme cases)
                            neg_item_idx = self.rng.choice(self.all_items)
                
                # Add negative sample
                neg_samples.append((user_idx, neg_item_idx, 0.0))
        
        return neg_samples
        
    def __len__(self):
        return self.length
    
    def __getitem__(self, idx):
        # Positive samples diakses langsung
        if idx < len(self.ratings):
            return (
                self.user_indices_tensor[idx],
                self.item_indices_tensor[idx], 
                self.ratings_tensor[idx]
            )
        
        # Negative samples diakses dari list yang sudah digenerate
        neg_idx = idx - len(self.ratings)
        if neg_idx < len(self.neg_samples):
            user_idx, item_idx, rating = self.neg_samples[neg_idx]
            return (
                torch.tensor(user_idx, dtype=torch.long),
                torch.tensor(item_idx, dtype=torch.long),
                torch.tensor(rating, dtype=torch.float)
            )
        
        # Fallback jika indeks terlalu besar (seharusnya tidak terjadi)
        return (
            torch.tensor(0, dtype=torch.long),
            torch.tensor(0, dtype=torch.long),
            torch.tensor(0.0, dtype=torch.float)
        )
    
    def _create_user_item_map(self) -> Dict[int, List[int]]:
        """Create mapping of user to interacted items"""
        user_item_map = {}
        for user_idx, item_idx in zip(self.user_indices, self.item_indices):
            if user_idx not in user_item_map:
                user_item_map[user_idx] = []
            user_item_map[user_idx].append(item_idx)
        return user_item_map


class CryptoNCFModel(nn.Module):
    """
    Neural Collaborative Filtering Model dioptimalkan untuk domain cryptocurrency
    dengan arsitektur yang lebih dalam dan fitur khusus:
    1. Residual connections
    2. Layer normalization
    3. Dual-path architecture (GMF + MLP)
    4. Attention mechanism
    """
    
    def __init__(self, num_users: int, num_items: int, embedding_dim: int, layers: List[int], dropout: float):
        super(CryptoNCFModel, self).__init__()
        
        # Embeddings yang terpisah untuk GMF dan MLP
        self.user_embedding_gmf = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_gmf = nn.Embedding(num_items, embedding_dim)
        
        self.user_embedding_mlp = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_mlp = nn.Embedding(num_items, embedding_dim)
        
        # MLP path dengan residual connections
        self.mlp_layers = nn.ModuleList()
        input_size = 2 * embedding_dim  # Concatenated user+item embeddings
        
        for i, layer_size in enumerate(layers):
            # Create sequential block with normalization, activation and dropout
            if i == 0:
                # First layer
                mlp_block = nn.Sequential(
                    nn.Linear(input_size, layer_size),
                    nn.LayerNorm(layer_size),  # Layer norm instead of batch norm
                    nn.LeakyReLU(0.1),
                    nn.Dropout(dropout)
                )
            else:
                # Add residual connection if dimensions match
                if layers[i-1] == layer_size:
                    mlp_block = nn.Sequential(
                        ResidualBlock(layers[i-1], layer_size, dropout)
                    )
                else:
                    mlp_block = nn.Sequential(
                        nn.Linear(layers[i-1], layer_size),
                        nn.LayerNorm(layer_size),
                        nn.LeakyReLU(0.1),
                        nn.Dropout(dropout)
                    )
            
            self.mlp_layers.append(mlp_block)
        
        # Simple attention mechanism for GMF path
        self.attention = nn.Sequential(
            nn.Linear(embedding_dim, embedding_dim // 2),
            nn.ReLU(),
            nn.Linear(embedding_dim // 2, 1),
            nn.Sigmoid()
        )
        
        # Output layer: combine GMF and MLP results
        self.output_layer = nn.Linear(layers[-1] + embedding_dim, 1)
        self.sigmoid = nn.Sigmoid()
        
        # Initialize weights
        self._init_weights()
    
    def _init_weights(self):
        """Initialize model weights with improved method"""
        for m in self.modules():
            if isinstance(m, nn.Linear):
                # Use Kaiming initialization for linear layers with LeakyReLU
                nn.init.kaiming_normal_(m.weight, mode='fan_in', nonlinearity='leaky_relu')
                if m.bias is not None:
                    nn.init.zeros_(m.bias)
            elif isinstance(m, nn.Embedding):
                # Normal initialization for embeddings with smaller variance
                nn.init.normal_(m.weight, mean=0.0, std=0.01)
    
    def forward(self, user_indices, item_indices):
        # GMF path
        user_embedding_gmf = self.user_embedding_gmf(user_indices)
        item_embedding_gmf = self.item_embedding_gmf(item_indices)
        
        # Apply attention to GMF embeddings
        user_attention = self.attention(user_embedding_gmf)
        item_attention = self.attention(item_embedding_gmf)
        
        # Apply attention weights
        user_embedding_gmf = user_embedding_gmf * user_attention
        item_embedding_gmf = item_embedding_gmf * item_attention
        
        # Element-wise product for GMF path
        gmf_vector = user_embedding_gmf * item_embedding_gmf
        
        # MLP path
        user_embedding_mlp = self.user_embedding_mlp(user_indices)
        item_embedding_mlp = self.item_embedding_mlp(item_indices)
        mlp_vector = torch.cat([user_embedding_mlp, item_embedding_mlp], dim=-1)
        
        # Process through MLP layers
        for layer in self.mlp_layers:
            mlp_vector = layer(mlp_vector)
        
        # Combine GMF and MLP paths
        combined = torch.cat([gmf_vector, mlp_vector], dim=-1)
        
        # Final prediction
        output = self.output_layer(combined)
        return self.sigmoid(output).view(-1)


class ResidualBlock(nn.Module):
    """Residual block for MLP path"""
    def __init__(self, in_features, out_features, dropout):
        super(ResidualBlock, self).__init__()
        self.linear = nn.Linear(in_features, out_features)
        self.norm = nn.LayerNorm(out_features)
        self.activation = nn.LeakyReLU(0.1)
        self.dropout = nn.Dropout(dropout)
        
    def forward(self, x):
        identity = x
        out = self.linear(x)
        out = self.norm(out)
        out = self.activation(out)
        out = self.dropout(out)
        out = out + identity  # Residual connection
        return out


class NCFRecommender:
    """
    Recommender using Neural Collaborative Filtering dioptimalkan untuk cryptocurrency
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None, use_cuda: bool = True):
        # Model parameters
        self.params = params or NCF_PARAMS
        
        # Initialize model
        self.model = None
        self.user_encoder = LabelEncoder()
        self.item_encoder = LabelEncoder()
        
        # Setup device
        self.use_cuda = use_cuda and torch.cuda.is_available()
        self.device = torch.device("cuda" if self.use_cuda else "cpu")
        
        logger.info(f"Using device: {self.device}")
        
        # Data
        self.projects_df = None
        self.interactions_df = None
        self.user_item_matrix = None
        
        # Keep track of original IDs
        self.users = None
        self.items = None
        
        # Item metadata for better sampling
        self.item_categories = None
        self.item_popularity = None
        self.item_trend_scores = None
        
        # Cache untuk mempercepat rekomendasi
        self._recommendation_cache = {}
        self._popular_items = None  # Cache for cold-start
    
    def load_data(self, projects_path: Optional[str] = None, interactions_path: Optional[str] = None) -> bool:
        # Use default paths if not specified
        if projects_path is None:
            projects_path = os.path.join(PROCESSED_DIR, "projects.csv")
        if interactions_path is None:
            interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
            
        try:
            # Load projects data
            if os.path.exists(projects_path):
                self.projects_df = pd.read_csv(projects_path)
                logger.info(f"Loaded {len(self.projects_df)} projects from {projects_path}")
                
                # Extract category information for better sampling
                self.item_categories = {}
                if 'primary_category' in self.projects_df.columns:
                    for _, row in self.projects_df.iterrows():
                        if 'id' in row:
                            item_id = row['id']
                            
                            # Try to get multiple categories if available
                            categories = []
                            if 'categories_list' in row:
                                # Process categories list
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
                            
                            self.item_categories[item_id] = categories
                    
                    logger.info(f"Extracted categories for {len(self.item_categories)} items")
                
                # Extract popularity for sampling
                self.item_popularity = {}
                if 'popularity_score' in self.projects_df.columns:
                    for _, row in self.projects_df.iterrows():
                        if 'id' in row and 'popularity_score' in row:
                            self.item_popularity[row['id']] = row['popularity_score'] / 100  # Normalize to 0-1
                    
                    logger.info(f"Extracted popularity scores for {len(self.item_popularity)} items")
                
                # Extract trend scores for sampling
                self.item_trend_scores = {}
                if 'trend_score' in self.projects_df.columns:
                    for _, row in self.projects_df.iterrows():
                        if 'id' in row and 'trend_score' in row:
                            self.item_trend_scores[row['id']] = row['trend_score'] / 100  # Normalize to 0-1
                    
                    logger.info(f"Extracted trend scores for {len(self.item_trend_scores)} items")
                
                # Precompute popular items for cold-start
                self._precompute_popular_items()
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
                
                # Extract unique users and items
                self.users = self.user_item_matrix.index.tolist()
                self.items = self.user_item_matrix.columns.tolist()
                
                # Fit encoders
                self.user_encoder.fit(self.users)
                self.item_encoder.fit(self.items)
                
                logger.info(f"Prepared {len(self.users)} users and {len(self.items)} items")
            else:
                logger.error(f"Interactions file not found: {interactions_path}")
                return False
                
            return True
            
        except Exception as e:
            logger.error(f"Error loading data: {str(e)}")
            import traceback
            logger.error(traceback.format_exc())
            return False
    
    def _precompute_popular_items(self):
        """Precompute popular items for cold-start recommendations"""
        if self.projects_df is None:
            return
            
        # Combine popularity and trend scores
        popular_items = []
        
        # Check for trend score and popularity score
        if 'trend_score' in self.projects_df.columns and 'popularity_score' in self.projects_df.columns:
            # Use weighted combination of trend and popularity
            df = self.projects_df.copy()
            
            # Create combined score (70% trend, 30% popularity)
            df['combined_score'] = df['trend_score'] * 0.7 + df['popularity_score'] * 0.3
            
            # Sort by combined score
            top_df = df.sort_values('combined_score', ascending=False)
            
            # Get top 30%
            top_n = max(30, int(len(df) * 0.3))
            top_items = top_df.head(top_n)
            
            # Create list of (id, score) tuples
            for _, row in top_items.iterrows():
                score = row['combined_score'] / 100  # Normalize to 0-1
                popular_items.append((row['id'], score))
                
        elif 'trend_score' in self.projects_df.columns:
            # Just use trend score
            top_df = self.projects_df.sort_values('trend_score', ascending=False)
            
            # Get top 30%
            top_n = max(30, int(len(self.projects_df) * 0.3))
            top_items = top_df.head(top_n)
            
            # Create list of (id, score) tuples
            for _, row in top_items.iterrows():
                score = row['trend_score'] / 100  # Normalize to 0-1
                popular_items.append((row['id'], score))
                
        elif 'popularity_score' in self.projects_df.columns:
            # Just use popularity score
            top_df = self.projects_df.sort_values('popularity_score', ascending=False)
            
            # Get top 30%
            top_n = max(30, int(len(self.projects_df) * 0.3))
            top_items = top_df.head(top_n)
            
            # Create list of (id, score) tuples
            for _, row in top_items.iterrows():
                score = row['popularity_score'] / 100  # Normalize to 0-1
                popular_items.append((row['id'], score))
                
        elif 'market_cap' in self.projects_df.columns:
            # Use market cap as fallback
            top_df = self.projects_df.sort_values('market_cap', ascending=False)
            
            # Get top 30%
            top_n = max(30, int(len(self.projects_df) * 0.3))
            top_items = top_df.head(top_n)
            
            # Create list of (id, score) tuples normalized by max market cap
            max_market_cap = top_items['market_cap'].max()
            if max_market_cap > 0:
                for _, row in top_items.iterrows():
                    score = min(1.0, row['market_cap'] / max_market_cap * 0.8)
                    popular_items.append((row['id'], score))
            
        # Store for later use
        self._popular_items = popular_items
        
        logger.info(f"Precomputed {len(popular_items)} popular items for cold-start")
    
    def _prepare_data(self) -> Tuple[np.ndarray, np.ndarray, np.ndarray, Dict, Dict, Dict, np.ndarray]:
        """Prepare data for training with enhanced metadata for better sampling"""
        # Convert interactions to training format
        interactions = []
        
        # Determine max rating dynamically
        max_rating = float(self.interactions_df['weight'].max())
        logger.info(f"Detected max rating value: {max_rating}")
        
        for user in self.users:
            user_interactions = self.user_item_matrix.loc[user]
            for item in self.items:
                rating = user_interactions[item]
                if rating > 0:  # Only positive interactions
                    user_idx = self.user_encoder.transform([user])[0]
                    item_idx = self.item_encoder.transform([item])[0]
                    # Normalize rating to 0-1 range dynamically
                    normalized_rating = float(rating) / max_rating
                    interactions.append((user_idx, item_idx, normalized_rating))
        
        # Convert to arrays
        interaction_array = np.array(interactions)
        
        if len(interaction_array) == 0:
            raise ValueError("No interactions found in the data")
        
        user_indices = interaction_array[:, 0].astype(np.int64)
        item_indices = interaction_array[:, 1].astype(np.int64)
        ratings = interaction_array[:, 2].astype(np.float32)
        
        # Get all unique items
        all_items = np.array([self.item_encoder.transform([item])[0] for item in self.items])
        
        # Convert item categories to encoded indices
        encoded_categories = {}
        encoded_popularity = {}
        encoded_trend_scores = {}
        
        # Process categories
        for item_id, categories in self.item_categories.items():
            if item_id in self.items:  # Only include items in our training data
                try:
                    item_idx = self.item_encoder.transform([item_id])[0]
                    encoded_categories[item_idx] = categories
                except:
                    # Skip if transform fails
                    pass
        
        # Process popularity scores
        for item_id, score in self.item_popularity.items():
            if item_id in self.items:  # Only include items in our training data
                try:
                    item_idx = self.item_encoder.transform([item_id])[0]
                    encoded_popularity[item_idx] = score
                except:
                    # Skip if transform fails
                    pass
        
        # Process trend scores
        for item_id, score in self.item_trend_scores.items():
            if item_id in self.items:  # Only include items in our training data
                try:
                    item_idx = self.item_encoder.transform([item_id])[0]
                    encoded_trend_scores[item_idx] = score
                except:
                    # Skip if transform fails
                    pass
        
        # Log normalization info
        logger.info(f"Normalized {len(ratings)} ratings to range [0, 1]")
        logger.info(f"Rating statistics - Min: {ratings.min():.4f}, Max: {ratings.max():.4f}, Mean: {ratings.mean():.4f}")
        
        return user_indices, item_indices, ratings, encoded_categories, encoded_popularity, encoded_trend_scores, all_items
    
    def _validate_training_data(self, user_indices, item_indices, ratings):
        """Validate data quality before training and apply appropriate filtering"""
        logger.info("Validating training data...")
        
        # Check for min interaction count per user
        user_counts = np.zeros(len(np.unique(user_indices)), dtype=int)
        for idx in user_indices:
            user_counts[idx] += 1
        
        min_user_interactions = user_counts.min()
        max_user_interactions = user_counts.max()
        avg_user_interactions = user_counts.mean()
        
        logger.info(f"User interaction statistics: min={min_user_interactions}, "
                f"max={max_user_interactions}, avg={avg_user_interactions:.2f}")
        
        # Check if we can use stratified split
        can_stratify = True
        
        # Stratified split memerlukan setidaknya 2 sampel per user
        if min_user_interactions < 2:
            logger.warning(f"Some users have only {min_user_interactions} interactions, which is too few for stratified split")
            
            # Identify users with at least 2 interactions
            valid_users = np.nonzero(user_counts >= 2)[0]
            
            # Calculate ratio of valid users
            valid_user_ratio = len(valid_users) / len(user_counts)
            logger.info(f"Found {len(valid_users)}/{len(user_counts)} users with at least 2 interactions ({valid_user_ratio:.1%})")
            
            if valid_user_ratio < 0.8:
                # If too many users would be filtered out, don't use stratification
                logger.warning("Too many users would be filtered out, disabling stratified split")
                can_stratify = False
            else:
                # Filter data to include only users with sufficient interactions
                valid_indices = np.isin(user_indices, valid_users)
                
                # Log filtering info
                removed_interactions = len(user_indices) - np.sum(valid_indices)
                logger.info(f"Filtered out {removed_interactions}/{len(user_indices)} interactions ({removed_interactions/len(user_indices):.1%})")
                
                # Filter arrays
                user_indices = user_indices[valid_indices]
                item_indices = item_indices[valid_indices]
                ratings = ratings[valid_indices]
        
        # Check for class imbalance
        unique_ratings, rating_counts = np.unique(ratings, return_counts=True)
        min_rating_count = rating_counts.min()
        
        if min_rating_count < 2:
            logger.warning(f"Some rating values have only {min_rating_count} samples, disabling stratified split")
            can_stratify = False
        
        # Ensure we still have enough data after filtering
        if len(user_indices) < 10:
            logger.warning("Too few interactions after filtering, using all data without stratification")
            can_stratify = False
        
        # Check for extreme imbalance in interaction counts
        if max_user_interactions > 50 * min_user_interactions:
            logger.warning(f"Extreme imbalance in user interaction counts: min={min_user_interactions}, max={max_user_interactions}")
            logger.warning("Will apply weight adjustment during training to handle imbalance")
        
        # Log final data stats
        logger.info(f"Final training data: {len(user_indices)} interactions, "
                f"{len(np.unique(user_indices))} users, {len(np.unique(item_indices))} items")
        
        return user_indices, item_indices, ratings, can_stratify
        
    def train(self, val_ratio: Optional[float] = None, batch_size: Optional[int] = None, 
              num_epochs: Optional[int] = None, learning_rate: Optional[float] = None,
              save_model: bool = True, **kwargs) -> Dict[str, Any]:
        """
        Train NCF model with enhanced strategy:
        1. Better parameter initialization
        2. Learning rate scheduling
        3. Early stopping with improved criteria
        4. Gradual warm-up
        """
        # Use config params if not specified
        val_ratio = val_ratio if val_ratio is not None else self.params.get('val_ratio', 0.15)
        batch_size = batch_size if batch_size is not None else self.params.get('batch_size', 128)
        num_epochs = num_epochs if num_epochs is not None else self.params.get('epochs', 30)
        learning_rate = learning_rate if learning_rate is not None else self.params.get('learning_rate', 0.0001)
        
        # Get additional parameters for training
        weight_decay = kwargs.get('weight_decay', self.params.get('weight_decay', 5e-4))
        num_negative = kwargs.get('negative_ratio', self.params.get('negative_ratio', 3))
        patience = kwargs.get('patience', self.params.get('patience', 7))
        
        logger.info("Starting NCF training with enhanced architecture")
        logger.info(f"Parameters: batch_size={batch_size}, lr={learning_rate}, "
                    f"weight_decay={weight_decay}, negative_ratio={num_negative}")
        
        start_time = time.time()
        
        # Prepare data
        try:
            user_indices, item_indices, ratings, encoded_categories, encoded_popularity, encoded_trend_scores, all_items = self._prepare_data()
        except ValueError as e:
            logger.error(f"Error preparing data: {str(e)}")
            return {"error": str(e)}
        
        # Validate training data before splitting
        user_indices, item_indices, ratings, can_stratify = self._validate_training_data(
            user_indices, item_indices, ratings
        )
        
        # Ensure batch size is valid for the dataset size
        # Batch size should be divisible by 8 for optimal performance
        batch_size = min(max(32, ((len(user_indices) + 7) // 8) * 8), batch_size)
        batch_size = min(batch_size, 512)  # Cap at 512 to avoid memory issues
        logger.info(f"Using optimized batch size: {batch_size}")
        
        # Split data into train and validation with appropriate strategy
        try:
            if can_stratify:
                # Split data into train and validation with stratification by user
                train_indices, val_indices = train_test_split(
                    np.arange(len(user_indices)), 
                    test_size=val_ratio, 
                    random_state=42,
                    stratify=user_indices  # Stratified by user for better balance
                )
            else:
                logger.warning("Cannot perform stratified split, using random split")
                train_indices, val_indices = train_test_split(
                    np.arange(len(user_indices)), 
                    test_size=val_ratio, 
                    random_state=42
                )
        except ValueError as e:
            logger.warning(f"Could not perform stratified split: {e}")
            logger.warning("Falling back to random split without stratification")
            train_indices, val_indices = train_test_split(
                np.arange(len(user_indices)), 
                test_size=val_ratio, 
                random_state=42
            )
        
        train_user_indices = user_indices[train_indices]
        train_item_indices = item_indices[train_indices]
        train_ratings = ratings[train_indices]
        
        val_user_indices = user_indices[val_indices]
        val_item_indices = item_indices[val_indices]
        val_ratings = ratings[val_indices]
        
        # Create datasets with enhanced metadata
        train_dataset = NCFDataset(
            train_user_indices, train_item_indices, train_ratings, 
            num_negative=num_negative,
            item_categories=encoded_categories,
            item_popularity=encoded_popularity,
            item_trend_scores=encoded_trend_scores,
            all_items=all_items,
            seed=42  # Fixed seed for reproducibility
        )
        
        val_dataset = NCFDataset(
            val_user_indices, val_item_indices, val_ratings, 
            num_negative=num_negative,
            item_categories=encoded_categories,
            item_popularity=encoded_popularity,
            item_trend_scores=encoded_trend_scores,
            all_items=all_items,
            seed=43  # Different seed for validation
        )
        
        # Create dataloaders with pin_memory for faster transfer to GPU
        train_loader = DataLoader(
            train_dataset, 
            batch_size=batch_size, 
            shuffle=True, 
            num_workers=0,  # No multiprocessing for stability
            pin_memory=self.use_cuda,
            drop_last=True  # Drop last batch if not full size
        )
        
        val_loader = DataLoader(
            val_dataset, 
            batch_size=batch_size, 
            shuffle=False, 
            num_workers=0,
            pin_memory=self.use_cuda,
            drop_last=True
        )
        
        # Initialize model with improved architecture
        num_users = len(self.user_encoder.classes_)
        num_items = len(self.item_encoder.classes_)
        
        # Get model architecture from params
        embedding_dim = self.params.get('embedding_dim', 64)
        layers = self.params.get('layers', [128, 64, 32])
        dropout = self.params.get('dropout', 0.3)

        self.model = CryptoNCFModel(
            num_users=num_users,
            num_items=num_items,
            embedding_dim=embedding_dim,
            layers=layers,
            dropout=dropout
        ).to(self.device)
        
        # Use Binary Cross Entropy loss
        criterion = nn.BCELoss()
        
        # Use AdamW optimizer for better regularization
        optimizer = optim.AdamW(
            self.model.parameters(), 
            lr=learning_rate,
            weight_decay=weight_decay
        )
        
        # Learning rate scheduler with warm-up and cosine annealing
        total_steps = len(train_loader) * num_epochs
        warmup_steps = int(total_steps * 0.1)  # 10% of steps for warm-up
        
        def lr_lambda(step):
            if step < warmup_steps:
                # Linear warm-up
                return float(step) / float(max(1, warmup_steps))
            else:
                # Cosine annealing
                progress = float(step - warmup_steps) / float(max(1, total_steps - warmup_steps))
                return 0.5 * (1.0 + np.cos(np.pi * progress))
        
        scheduler = optim.lr_scheduler.LambdaLR(optimizer, lr_lambda)
        
        # Training loop
        train_losses = []
        val_losses = []
        
        # Early stopping parameters
        best_val_loss = float('inf')
        patience_counter = 0
        best_model_state = None
        improvement_threshold = 0.005  # Need 0.5% improvement to be considered significant
        consecutive_no_improvement = 0  # Counter for epochs without improvement
        max_consecutive_no_improvement = kwargs.get('patience', self.params.get('patience', 7))  # Max consecutive epochs without improvement

        logger.info(f"Starting training for {num_epochs} epochs with patience {patience}")
        
        for epoch in range(1, num_epochs + 1):
            epoch_start_time = time.time()
            
            # Training
            self.model.train()
            train_loss = 0
            train_batches = 0
            
            for batch_idx, (user_indices, item_indices, ratings) in enumerate(train_loader):
                try:
                    # Shape validation
                    if user_indices.dim() > 1 and user_indices.size(1) == 1:
                        user_indices = user_indices.squeeze(1)
                    if item_indices.dim() > 1 and item_indices.size(1) == 1:
                        item_indices = item_indices.squeeze(1)
                    if ratings.dim() > 1 and ratings.size(1) == 1:
                        ratings = ratings.squeeze(1)
                    
                    # Skip batch if size = 1 (to avoid batch norm issues)
                    if user_indices.size(0) <= 1:
                        continue
                    
                    # Move to device
                    user_indices = user_indices.to(self.device)
                    item_indices = item_indices.to(self.device)
                    ratings = ratings.to(self.device)
                    
                    # Forward pass
                    outputs = self.model(user_indices, item_indices)
                    loss = criterion(outputs, ratings)
                    
                    # Backward pass and optimize
                    optimizer.zero_grad()
                    loss.backward()
                    
                    # Clip gradients to prevent explosion
                    torch.nn.utils.clip_grad_norm_(self.model.parameters(), max_norm=1.0)
                    
                    optimizer.step()
                    scheduler.step()  # Update learning rate
                    
                    train_loss += loss.item()
                    train_batches += 1
                    
                    if batch_idx % 10 == 0:
                        logger.debug(f"Epoch {epoch}, Batch {batch_idx}: Loss {loss.item():.4f}")
                        
                except Exception as e:
                    logger.warning(f"Error in training batch {batch_idx}: {e}")
                    continue
            
            avg_train_loss = train_loss / max(train_batches, 1)
            train_losses.append(avg_train_loss)
            
            # Validation
            self.model.eval()
            val_loss = 0
            val_batches = 0
            
            with torch.no_grad():
                for batch_idx, (user_indices, item_indices, ratings) in enumerate(val_loader):
                    try:
                        # Shape validation
                        if user_indices.dim() > 1 and user_indices.size(1) == 1:
                            user_indices = user_indices.squeeze(1)
                        if item_indices.dim() > 1 and item_indices.size(1) == 1:
                            item_indices = item_indices.squeeze(1)
                        if ratings.dim() > 1 and ratings.size(1) == 1:
                            ratings = ratings.squeeze(1)
                        
                        # Skip small batches
                        if user_indices.size(0) <= 1:
                            continue
                        
                        # Move to device
                        user_indices = user_indices.to(self.device)
                        item_indices = item_indices.to(self.device)
                        ratings = ratings.to(self.device)
                        
                        # Forward pass
                        outputs = self.model(user_indices, item_indices)
                        loss = criterion(outputs, ratings)
                        
                        val_loss += loss.item()
                        val_batches += 1
                        
                    except Exception as e:
                        logger.warning(f"Error in validation batch {batch_idx}: {e}")
                        continue
            
            avg_val_loss = val_loss / max(val_batches, 1)
            val_losses.append(avg_val_loss)
            
            # Get current learning rate
            current_lr = optimizer.param_groups[0]['lr']
            
            # Early stopping with improvement threshold
            if avg_val_loss < best_val_loss * (1 - improvement_threshold):
                # Significant improvement
                improvement_percent = 100 * (best_val_loss - avg_val_loss) / best_val_loss
                best_val_loss = avg_val_loss
                patience_counter = 0
                consecutive_no_improvement = 0
                # Save best model state
                best_model_state = copy.deepcopy(self.model.state_dict())
                logger.info(f"New best validation loss: {best_val_loss:.4f} (improved by {improvement_percent:.2f}%)")
            else:
                patience_counter += 1
                consecutive_no_improvement += 1
                logger.info(f"Validation loss did not improve significantly. Patience: {patience_counter}/{patience}")
                
                # If small improvement but not significant, still track as best
                if avg_val_loss < best_val_loss:
                    consecutive_no_improvement = 0
                    logger.info(f"Small improvement detected: {best_val_loss:.4f} -> {avg_val_loss:.4f}")
                    best_val_loss = avg_val_loss
            
            # Log progress
            epoch_time = time.time() - epoch_start_time
            logger.info(f"Epoch {epoch}/{num_epochs} - "
                    f"Train Loss: {avg_train_loss:.4f}, "
                    f"Val Loss: {avg_val_loss:.4f}, "
                    f"Time: {epoch_time:.2f}s, "
                    f"LR: {current_lr:.6f}")
            
            # Check early stopping criteria
            if patience_counter >= patience or consecutive_no_improvement >= max_consecutive_no_improvement:
                if patience_counter >= patience:
                    logger.info(f"Early stopping triggered after {epoch} epochs (patience exceeded)")
                else:
                    logger.info(f"Early stopping triggered after {epoch} epochs (no significant improvement for {max_consecutive_no_improvement} epochs)")
                break
            
            # Learning rate reduction if loss plateaus
            if epoch > 5 and avg_train_loss > 0.9 * train_losses[-2]:
                new_lr = current_lr * 0.5
                for param_group in optimizer.param_groups:
                    param_group['lr'] = new_lr
                logger.info(f"Learning rate reduced to {new_lr:.6f} due to plateauing loss")
        
        # Restore best model if we did early stopping
        if best_model_state is not None and (patience_counter >= patience or consecutive_no_improvement >= max_consecutive_no_improvement):
            logger.info(f"Restoring model to best state with validation loss {best_val_loss:.4f}")
            self.model.load_state_dict(best_model_state)
        
        total_time = time.time() - start_time
        logger.info(f"Training completed in {total_time:.2f}s")
        
        # Calculate validation metrics for performance comparison
        validation_metrics = self._calculate_validation_metrics(val_dataset)
        
        # Save model if requested
        if save_model:
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            self.save_model(model_path)
        
        return {
            'train_loss': train_losses,
            'val_loss': val_losses,
            'training_time': total_time,
            'early_stopped': patience_counter >= patience or consecutive_no_improvement >= max_consecutive_no_improvement,
            'best_epoch': epoch - patience_counter if patience_counter >= patience else epoch,
            'best_val_loss': best_val_loss,
            'validation_metrics': validation_metrics
        }
    
    def _calculate_validation_metrics(self, val_dataset):
        """Calculate validation metrics for model comparison with improved methodology"""
        self.model.eval()
        
        # Extract positive examples from validation set
        positive_indices = np.nonzero(val_dataset.ratings > 0)[0]
        
        if len(positive_indices) == 0:
            return {'precision': 0, 'recall': 0, 'ndcg': 0, 'hit_ratio': 0}
        
        # Sample up to 1000 positive examples for evaluation
        sample_size = min(1000, len(positive_indices))
        sample_indices = np.random.choice(positive_indices, size=sample_size, replace=False)
        
        # Calculate metrics
        precision_list = []
        recall_list = []
        ndcg_list = []
        hit_list = []
        
        with torch.no_grad():
            for idx in sample_indices:
                user_idx = val_dataset.user_indices[idx]
                pos_item_idx = val_dataset.item_indices[idx]
                
                # Get user's interacted items to exclude
                user_interacted_items = val_dataset.user_item_map.get(user_idx, [])
                
                # IMPROVEMENT: Use all available items instead of just 99 random negatives
                # This makes evaluation much more realistic and challenging
                
                # Get all items excluding user's already interacted items
                all_candidate_items = np.setdiff1d(val_dataset.all_items, user_interacted_items)
                
                # If all candidate items is too large (>10,000), sample a large subset
                # This balances computational feasibility with realistic evaluation
                if len(all_candidate_items) > 10000:
                    negative_items = np.random.choice(all_candidate_items, size=9999, replace=False)
                    candidate_items = np.append([pos_item_idx], negative_items)
                else:
                    # Use positive item plus all available negative items
                    candidate_items = np.append([pos_item_idx], all_candidate_items)
                
                # Move to device
                user_tensor = torch.LongTensor([user_idx] * len(candidate_items)).to(self.device)
                item_tensor = torch.LongTensor(candidate_items).to(self.device)
                
                # Get predictions
                predictions = self.model(user_tensor, item_tensor).cpu().numpy()
                
                # Sort items by prediction score
                pred_indices = np.argsort(predictions)[::-1]
                
                # Calculate metrics at k=10
                k = 10
                topk_indices = pred_indices[:k]
                
                # Precision@k: proportion of recommended items that are relevant
                precision = 1.0 if 0 in topk_indices else 0.0
                precision_list.append(precision)
                
                # Recall@k: proportion of relevant items that are recommended
                recall = 1.0 if 0 in topk_indices else 0.0
                recall_list.append(recall)
                
                # NDCG@k: Normalized Discounted Cumulative Gain
                if 0 in topk_indices:
                    position = np.nonzero(topk_indices == 0)[0][0]
                    ndcg = 1.0 / np.log2(position + 2)  # +2 because position is 0-indexed
                else:
                    ndcg = 0.0
                ndcg_list.append(ndcg)
                
                # Hit Ratio@k: whether the test item is in the top-k items
                hit_ratio = 1.0 if 0 in topk_indices else 0.0
                hit_list.append(hit_ratio)
        
        # Calculate average metrics
        metrics = {
            'precision': np.mean(precision_list),
            'recall': np.mean(recall_list),
            'ndcg': np.mean(ndcg_list),
            'hit_ratio': np.mean(hit_list)
        }
        
        return metrics
    
    def save_model(self, filepath: Optional[str] = None) -> str:
        if filepath is None:
            # Create default path
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filepath = os.path.join(MODELS_DIR, f"ncf_model_{timestamp}.pkl")
            
        # Create directory if it doesn't exist
        os.makedirs(os.path.dirname(filepath), exist_ok=True)
        
        # Save model
        model_state = {
            'model_state_dict': self.model.state_dict(),
            'user_encoder': self.user_encoder,
            'item_encoder': self.item_encoder,
            'users': self.users,
            'items': self.items,
            'config': {
                'embedding_dim': self.params['embedding_dim'],
                'layers': self.params['layers'],
                'dropout': self.params['dropout']
            },
            'timestamp': datetime.now().isoformat()
        }
        
        with open(filepath, 'wb') as f:
            pickle.dump(model_state, f)
            
        logger.info(f"Model saved to {filepath}")
        return filepath
    
    def load_model(self, filepath: str) -> bool:
        try:
            logger.info(f"Loading NCF model from {filepath}")
            
            if not os.path.exists(filepath):
                logger.error(f"Model file not found: {filepath}")
                return False
                
            with open(filepath, 'rb') as f:
                model_state = pickle.load(f)
                
            # Log model state keys for debugging
            logger.info(f"NCF model state contains keys: {list(model_state.keys())}")
                
            # Extract components
            state_dict = model_state.get('model_state_dict')
            if state_dict is None:
                logger.error("No 'model_state_dict' key in loaded NCF model")
                return False
                
            self.user_encoder = model_state.get('user_encoder')
            self.item_encoder = model_state.get('item_encoder')
            self.users = model_state.get('users')
            self.items = model_state.get('items')
            config = model_state.get('config')
            
            # Create model with same architecture
            num_users = len(self.user_encoder.classes_)
            num_items = len(self.item_encoder.classes_)
            
            # Create model with enhanced architecture
            try:
                self.model = CryptoNCFModel(
                    num_users=num_users,
                    num_items=num_items,
                    embedding_dim=config['embedding_dim'],
                    layers=config['layers'],
                    dropout=config['dropout']
                ).to(self.device)
                
                # Try to load state dict - may need adjusting if architecture changed
                try:
                    self.model.load_state_dict(state_dict)
                except Exception as e:
                    logger.warning(f"Error loading state dict with CryptoNCFModel: {e}")
                    logger.warning("Loading with strict=False to accommodate architecture differences")
                    
                    # Try loading with strict=False
                    self.model.load_state_dict(state_dict, strict=False)
                    logger.info("Model loaded with architecture accommodations")
            except Exception as e:
                logger.error(f"Error creating model with enhanced architecture: {e}")
                return False
            
            # Set model to evaluation mode
            self.model.eval()
            
            # Precompute popular items if not already done
            if self._popular_items is None:
                self._precompute_popular_items()
            
            logger.info(f"NCF model successfully loaded from {filepath}")
            return True
                
        except Exception as e:
            logger.error(f"Error loading NCF model: {str(e)}")
            # Log traceback for debugging
            import traceback
            logger.error(traceback.format_exc())
            return False
        
    def is_trained(self) -> bool:
        if self.model is None:
            return False
        return True
    
    def predict(self, user_id: str, item_id: str) -> float:
        """Predict rating for user-item pair"""
        if self.model is None:
            logger.error("Model not trained or loaded")
            return 0.0
        
        # Check if user and item exist
        if user_id not in self.users or item_id not in self.items:
            return 0.0
        
        # Encode user and item
        user_idx = self.user_encoder.transform([user_id])[0]
        item_idx = self.item_encoder.transform([item_id])[0]
        
        # Convert to tensors and move to device
        user_tensor = torch.LongTensor([user_idx]).to(self.device)
        item_tensor = torch.LongTensor([item_idx]).to(self.device)
        
        # Make prediction
        self.model.eval()
        with torch.no_grad():
            prediction = self.model(user_tensor, item_tensor).item()
        
        return prediction
    
    def recommend_for_user(self, user_id: str, n: int = 10, 
                   exclude_known: bool = True) -> List[Tuple[str, float]]:
        """
        PERBAIKAN: Generate recommendations dengan score validation yang ketat
        """
        # Check cache for performance
        cache_key = f"{user_id}_{n}_{exclude_known}"
        if cache_key in self._recommendation_cache:
            cache_time, cache_results = self._recommendation_cache[cache_key]
            if time.time() - cache_time < 3600:
                # PERBAIKAN: Validasi score dari cache
                validated_cache = []
                for item_id, score in cache_results:
                    clean_score = float(np.clip(score, 0.0, 1.0))
                    if not np.isnan(clean_score) and not np.isinf(clean_score):
                        validated_cache.append((item_id, clean_score))
                
                validated_cache.sort(key=lambda x: x[1], reverse=True)
                return validated_cache

        if self.model is None:
            logger.error("Model not trained or loaded")
            return []

        # Check if user exists
        if user_id not in self.users:
            logger.warning(f"User {user_id} not in training data")
            return self._get_cold_start_recommendations(n)

        # Get known items to exclude
        known_items = set()
        if exclude_known and self.user_item_matrix is not None:
            if user_id in self.user_item_matrix.index:
                user_interactions = self.user_item_matrix.loc[user_id]
                known_items = set(user_interactions[user_interactions > 0].index)

        # Get metadata untuk diversity tracking
        category_counts = {}
        item_to_category = {}
        item_to_chain = {}
        item_to_trend = {}

        try:
            if self.projects_df is not None:
                if 'primary_category' in self.projects_df.columns:
                    for _, row in self.projects_df.iterrows():
                        if 'id' in row:
                            item_id = row['id']
                            
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
                            
                            item_to_category[item_id] = categories
                
                if 'chain' in self.projects_df.columns:
                    item_to_chain = dict(zip(self.projects_df['id'], self.projects_df['chain']))
                if 'trend_score' in self.projects_df.columns:
                    item_to_trend = dict(zip(self.projects_df['id'], self.projects_df['trend_score']))
        except Exception as e:
            logger.warning(f"Error getting item metadata: {str(e)}")

        # Get potential items to recommend
        candidate_items = [item for item in self.items if item not in known_items]

        if not candidate_items:
            return []

        # Encode user
        user_idx = self.user_encoder.transform([user_id])[0]
        user_tensor = torch.LongTensor([user_idx]).to(self.device)

        # PERBAIKAN: Generate predictions dengan validation ketat
        predictions = []
        batch_size = 512

        for i in range(0, len(candidate_items), batch_size):
            batch_items = candidate_items[i:i+batch_size]
            
            try:
                item_indices = self.item_encoder.transform(batch_items)
                item_tensor = torch.LongTensor(item_indices).to(self.device)
                
                batch_user_tensor = user_tensor.repeat(len(batch_items))
                
                self.model.eval()
                with torch.no_grad():
                    batch_predictions = self.model(batch_user_tensor, item_tensor).cpu().numpy()
                    
                    # PERBAIKAN: Clip dan validate predictions
                    batch_predictions = np.clip(batch_predictions, 0.0, 1.0)
                    batch_predictions = np.nan_to_num(batch_predictions, nan=0.0, posinf=1.0, neginf=0.0)
                
                # Store predictions with validation
                for item_id, pred in zip(batch_items, batch_predictions):
                    clean_pred = float(np.clip(pred, 0.0, 1.0))
                    if not np.isnan(clean_pred) and not np.isinf(clean_pred):
                        predictions.append((item_id, clean_pred))
                        
            except Exception as e:
                logger.warning(f"Error in batch prediction: {e}")
                continue

        # PERBAIKAN: Fallback jika predictions terlalu sedikit
        if len(predictions) < n and self.projects_df is not None:
            fallback_recs = self._get_fallback_recommendations(n, exclude_ids=known_items)
            
            predicted_ids = {item_id for item_id, _ in predictions}
            filtered_fallbacks = []
            
            for item_id, score in fallback_recs:
                if item_id not in predicted_ids:
                    clean_score = float(np.clip(score, 0.0, 1.0))
                    if not np.isnan(clean_score) and not np.isinf(clean_score):
                        filtered_fallbacks.append((item_id, clean_score))
            
            predictions.extend(filtered_fallbacks)

        # PERBAIKAN: Sort dengan validation
        valid_predictions = []
        for item_id, score in predictions:
            clean_score = float(np.clip(score, 0.0, 1.0))
            if not np.isnan(clean_score) and not np.isinf(clean_score):
                valid_predictions.append((item_id, clean_score))
        
        valid_predictions.sort(key=lambda x: x[1], reverse=True)

        if len(valid_predictions) <= n:
            # Store in cache
            self._recommendation_cache[cache_key] = (time.time(), valid_predictions)
            return valid_predictions

        # Take more candidates for better diversity
        top_candidates = valid_predictions[:min(len(valid_predictions), n*3)]

        # PERBAIKAN: Enhanced diversity dengan score validation
        if item_to_category:
            # Select top items unconditionally (25% by pure score)
            top_count = max(n // 4, 1)
            result = top_candidates[:top_count]
            
            # Track selected categories and chains
            selected_categories = {}
            selected_chains = {}
            
            for item_id, _ in result:
                if item_id in item_to_category:
                    categories = item_to_category[item_id]
                    for category in categories:
                        selected_categories[category] = selected_categories.get(category, 0) + 1
                
                if item_id in item_to_chain:
                    chain = item_to_chain[item_id]
                    selected_chains[chain] = selected_chains.get(chain, 0) + 1

            # Diversity limits
            max_per_category = max(1, int(n * 0.25))
            max_per_chain = max(2, int(n * 0.4))
            
            # Remaining candidates
            remaining = top_candidates[top_count:]
            
            # Apply diversity balancing
            while len(result) < n and remaining:
                best_score = -float('inf')
                best_idx = -1
                
                for idx, (item_id, score) in enumerate(remaining):
                    adjusted_score = score
                    
                    # Category diversity adjustments
                    if item_id in item_to_category:
                        categories = item_to_category[item_id]
                        
                        category_penalty = 0
                        for category in categories:
                            cat_count = selected_categories.get(category, 0)
                            if cat_count >= max_per_category:
                                category_penalty -= 0.2
                                break
                        
                        category_bonus = 0
                        for category in categories:
                            if category not in selected_categories:
                                category_bonus += 0.1
                                break
                        
                        adjusted_score += category_penalty + category_bonus
                    
                    # Chain diversity
                    if item_id in item_to_chain:
                        chain = item_to_chain[item_id]
                        chain_count = selected_chains.get(chain, 0)
                        
                        if chain_count >= max_per_chain:
                            adjusted_score -= 0.15
                        elif chain_count == 0:
                            adjusted_score += 0.05
                    
                    # Trend boost
                    if item_id in item_to_trend:
                        trend_score = item_to_trend[item_id]
                        if trend_score > 80:
                            adjusted_score += 0.15
                        elif trend_score > 65:
                            adjusted_score += 0.05
                    
                    # PERBAIKAN: Clip adjusted score
                    adjusted_score = np.clip(adjusted_score, 0.0, 1.0)
                    
                    if adjusted_score > best_score:
                        best_score = adjusted_score
                        best_idx = idx
                
                if best_idx >= 0:
                    item_id, original_score = remaining.pop(best_idx)
                    
                    # PERBAIKAN: Use original score, not adjusted
                    clean_score = float(np.clip(original_score, 0.0, 1.0))
                    result.append((item_id, clean_score))
                    
                    # Update tracking
                    if item_id in item_to_category:
                        categories = item_to_category[item_id]
                        for category in categories:
                            selected_categories[category] = selected_categories.get(category, 0) + 1
                    
                    if item_id in item_to_chain:
                        chain = item_to_chain[item_id]
                        selected_chains[chain] = selected_chains.get(chain, 0) + 1
                else:
                    break
            
            # PERBAIKAN: Final validation dan sort
            final_result = []
            for item_id, score in result:
                clean_score = float(np.clip(score, 0.0, 1.0))
                if not np.isnan(clean_score) and not np.isinf(clean_score):
                    final_result.append((item_id, clean_score))
            
            final_result.sort(key=lambda x: x[1], reverse=True)
            
            # Store in cache
            self._recommendation_cache[cache_key] = (time.time(), final_result)
            
            return final_result[:n]

        # PERBAIKAN: Jika tidak ada category data, return validated top candidates
        validated_top = []
        for item_id, score in top_candidates[:n]:
            clean_score = float(np.clip(score, 0.0, 1.0))
            if not np.isnan(clean_score) and not np.isinf(clean_score):
                validated_top.append((item_id, clean_score))
        
        validated_top.sort(key=lambda x: x[1], reverse=True)
        
        # Store in cache
        self._recommendation_cache[cache_key] = (time.time(), validated_top)
        
        return validated_top
    
    def _get_fallback_recommendations(self, n: int, exclude_ids: set = None) -> List[Tuple[str, float]]:
        """
        PERBAIKAN: Get fallback recommendations dengan score validation
        """
        if exclude_ids is None:
            exclude_ids = set()
            
        fallback_recs = []
        
        # Try trending items first
        if 'trend_score' in self.projects_df.columns:
            trending_items = self.projects_df.sort_values('trend_score', ascending=False)
            
            for _, row in trending_items.iterrows():
                if row['id'] not in exclude_ids and len(fallback_recs) < n:
                    # PERBAIKAN: Clip dan validate score
                    raw_score = row['trend_score'] / 100 * 0.8
                    clean_score = float(np.clip(raw_score, 0.0, 1.0))
                    if not np.isnan(clean_score) and not np.isinf(clean_score):
                        fallback_recs.append((row['id'], clean_score))
        
        # Add popular items if needed
        if len(fallback_recs) < n and 'popularity_score' in self.projects_df.columns:
            popular_items = self.projects_df.sort_values('popularity_score', ascending=False)
            
            for _, row in popular_items.iterrows():
                if (row['id'] not in exclude_ids and 
                    row['id'] not in [rec[0] for rec in fallback_recs] and 
                    len(fallback_recs) < n):
                    
                    # PERBAIKAN: Clip dan validate score
                    raw_score = row['popularity_score'] / 100 * 0.7
                    clean_score = float(np.clip(raw_score, 0.0, 1.0))
                    if not np.isnan(clean_score) and not np.isinf(clean_score):
                        fallback_recs.append((row['id'], clean_score))
        
        # Add market cap items if still needed
        if len(fallback_recs) < n and 'market_cap' in self.projects_df.columns:
            market_cap_items = self.projects_df.sort_values('market_cap', ascending=False)
            max_cap = market_cap_items['market_cap'].max()
            
            for _, row in market_cap_items.iterrows():
                if (row['id'] not in exclude_ids and 
                    row['id'] not in [rec[0] for rec in fallback_recs] and 
                    len(fallback_recs) < n):
                    
                    # PERBAIKAN: Normalize dan clip market cap score
                    if max_cap > 0:
                        raw_score = row['market_cap'] / max_cap * 0.6
                    else:
                        raw_score = 0.5
                    
                    clean_score = float(np.clip(raw_score, 0.0, 1.0))
                    if not np.isnan(clean_score) and not np.isinf(clean_score):
                        fallback_recs.append((row['id'], clean_score))
        
        # Add random items if still needed
        remaining = n - len(fallback_recs)
        if remaining > 0:
            available_items = [item for item in self.items 
                            if item not in exclude_ids 
                            and item not in [rec[0] for rec in fallback_recs]]
            
            if available_items:
                random_items = random.sample(available_items, min(remaining, len(available_items)))
                
                for item in random_items:
                    # PERBAIKAN: Default score yang sudah divalidasi
                    clean_score = 0.4  # Low default score dalam range valid
                    fallback_recs.append((item, clean_score))
        
        return fallback_recs
    
    def _get_cold_start_recommendations(self, n: int = 10) -> List[Tuple[str, float]]:
        """
        PERBAIKAN: Cold-start recommendations dengan score validation
        """
        # Use precomputed popular items if available
        if self._popular_items:
            validated_popular = []
            for item_id, score in self._popular_items[:n]:
                clean_score = float(np.clip(score, 0.0, 1.0))
                if not np.isnan(clean_score) and not np.isinf(clean_score):
                    validated_popular.append((item_id, clean_score))
            
            if validated_popular:
                return validated_popular
        
        # Fallback to trending + popular items dengan validation
        fallback_recs = self._get_fallback_recommendations(n)
        
        # PERBAIKAN: Double validation untuk cold-start
        validated_fallback = []
        for item_id, score in fallback_recs:
            clean_score = float(np.clip(score, 0.0, 1.0))
            if not np.isnan(clean_score) and not np.isinf(clean_score):
                validated_fallback.append((item_id, clean_score))
        
        validated_fallback.sort(key=lambda x: x[1], reverse=True)
        return validated_fallback
    
    def recommend_projects(self, user_id: str, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get recommendations as project dictionaries with all available fields
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
                project_dict['recommendation_score'] = float(score)
                
                # Ensure all required fields are present
                required_fields = ['id', 'name', 'symbol', 'image', 'current_price', 'market_cap', 
                                'total_volume', 'price_change_percentage_24h', 'price_change_percentage_7d_in_currency', 
                                'popularity_score', 'trend_score', 'primary_category', 'chain']
                
                for field in required_fields:
                    if field not in project_dict:
                        if field == 'price_usd' and 'current_price' in project_dict:
                            project_dict['price_usd'] = project_dict['current_price']
                        elif field == 'volume_24h' and 'total_volume' in project_dict:
                            project_dict['volume_24h'] = project_dict['total_volume']
                        elif field == 'price_change_7d' and 'price_change_percentage_7d_in_currency' in project_dict:
                            project_dict['price_change_7d'] = project_dict['price_change_percentage_7d_in_currency']
                        else:
                            project_dict[field] = None
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        return detailed_recommendations
    
    def get_recommendations_by_category(self, user_id: str, category: str, n: int = 10, chain: Optional[str] = None, strict: bool = False) -> List[Dict[str, Any]]:
        """
        Mendapatkan rekomendasi berdasarkan kategori dengan opsional filter chain
        """
        logger.info(f"Getting category-filtered recommendations for user {user_id}, category={category}, chain={chain}, strict={strict}")
        
        # Check if user exists
        if user_id not in self.users:
            logger.warning(f"User {user_id} not found in training data, falling back to category-based popular items")
            # In strict mode, we apply the strict filter to the category-based populars as well
            return self._get_category_based_recommendations(category, n, chain, strict=strict)
        
        # Get initial recommendations with increased count for filtering
        multiplier = 4  # NCF may need more filtering headroom
        recommendations = self.recommend_for_user(user_id, n=n*multiplier)
        
        # Filter projects by category (and chain if provided)
        filtered_recommendations = []
        
        for project_id, score in recommendations:
            # Find project data
            project_df_row = self.projects_df[self.projects_df['id'] == project_id]
            
            if not project_df_row.empty:
                # Check category match
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
        
        # Convert to detailed recommendations
        detailed_recommendations = []
        
        # Add filtered recommendations to detailed_recommendations
        for project_id, score in filtered_recommendations[:n]:
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
        
        logger.info(f"Found {len(filtered_recommendations)} filtered recommendations")
        
        # If strict mode, return only exact matches
        if strict:
            return detailed_recommendations[:n]
        
        # If we have too few recommendations, backfill with category-based popular items
        if len(detailed_recommendations) < n // 2:
            logger.warning(f"Too few filtered recommendations, adding category-based popular items")
            
            # Get IDs of already added recommendations
            existing_ids = {rec['id'] for rec in detailed_recommendations}
            
            # Get category-based popular items
            category_popular = self._get_category_based_recommendations(category, n*2, chain)
            
            # Add non-duplicate items
            for rec in category_popular:
                if rec['id'] not in existing_ids and len(detailed_recommendations) < n:
                    # Mark as popular supplementary item
                    rec['recommendation_source'] = 'category-popular'
                    # PERBAIKAN: Set filter_match untuk item tambahan
                    rec['filter_match'] = 'fallback'
                    detailed_recommendations.append(rec)
        
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
                
                # Add to matching projects if both category and chain match
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
        if user_id not in self.users:
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
                    filtered_recommendations.append((project_id, score))
        
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
                    # PERBAIKAN: Set filter_match untuk item fallback
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
        """
        Get cold-start recommendations with optional user interests
        """
        recommendations = []
        
        if user_interests and self.projects_df is not None:
            # Filter projects by categories if interests are provided
            interest_filtered_projects = []
            
            for interest in user_interests:
                interest_lower = interest.lower()
                
                # Search for matching categories
                for _, row in self.projects_df.iterrows():
                    categories = []
                    
                    # Try to get categories from different fields
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
                    
                    # Check if any category matches interest
                    match_found = False
                    for category in categories:
                        if isinstance(category, str) and (interest_lower in category.lower() or category.lower() in interest_lower):
                            match_found = True
                            break
                    
                    if match_found:
                        interest_filtered_projects.append((row['id'], 0.85))  # High score for interest match
            
            # If we found projects matching interests
            if interest_filtered_projects:
                # Deduplicate and take top n*2 for diversity
                seen = set()
                unique_projects = []
                for proj_id, score in interest_filtered_projects:
                    if proj_id not in seen:
                        seen.add(proj_id)
                        unique_projects.append((proj_id, score))
                
                recommendations.extend(unique_projects[:n*2])
        
        # If not enough recommendations from interests, add trending/popular
        if len(recommendations) < n:
            remaining = n - len(recommendations)
            already_added = {proj_id for proj_id, _ in recommendations}
            
            # Add from precomputed popular items
            if self._popular_items:
                for proj_id, score in self._popular_items:
                    if proj_id not in already_added and len(recommendations) < n:
                        recommendations.append((proj_id, score * 0.8))  # Lower score for non-interest match
                        already_added.add(proj_id)
            
            # If still not enough, use fallback
            if len(recommendations) < n:
                remaining = n - len(recommendations)
                fallback_recs = self._get_fallback_recommendations(remaining, exclude_ids=already_added)
                recommendations.extend(fallback_recs)
        
        # Sort by score
        recommendations.sort(key=lambda x: x[1], reverse=True)
        
        # Convert to detailed dictionaries
        detailed_recommendations = []
        for project_id, score in recommendations[:n]:
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
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """Get trending projects with diversity"""
        if 'trend_score' in self.projects_df.columns:
            trending = self.projects_df.sort_values('trend_score', ascending=False).head(n*2)
            
            # Ensure category diversity
            if 'primary_category' in trending.columns:
                # Track categories
                selected_categories = {}
                result = []
                
                # Max per category (30% of total)
                max_per_category = max(1, int(n * 0.3))
                
                for _, project in trending.iterrows():
                    category = project['primary_category']
                    current_count = selected_categories.get(category, 0)
                    
                    # Skip if we already have too many from this category
                    if current_count >= max_per_category and len(result) >= n // 2:
                        continue
                    
                    # Add to results
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0)) / 100
                    result.append(project_dict)
                    
                    # Update tracking
                    selected_categories[category] = current_count + 1
                    
                    # Break if we have enough
                    if len(result) >= n:
                        break
                
                # If we don't have enough, add more without category constraints
                if len(result) < n:
                    remaining = [
                        p.to_dict() for _, p in trending.iterrows()
                        if p['id'] not in [r['id'] for r in result]
                    ]
                    
                    for project_dict in remaining[:n - len(result)]:
                        project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0)) / 100
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
        """Get popular projects with diversity"""
        if 'popularity_score' in self.projects_df.columns:
            popular = self.projects_df.sort_values('popularity_score', ascending=False).head(n*2)
            
            # Ensure category diversity
            if 'primary_category' in popular.columns:
                # Track categories
                selected_categories = {}
                result = []
                
                # Max per category (30% of total)
                max_per_category = max(1, int(n * 0.3))
                
                for _, project in popular.iterrows():
                    category = project['primary_category'] 
                    current_count = selected_categories.get(category, 0)
                    
                    # Skip if we already have too many from this category
                    if current_count >= max_per_category and len(result) >= n // 2:
                        continue
                    
                    # Add to results
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0)) / 100
                    result.append(project_dict)
                    
                    # Update tracking
                    selected_categories[category] = current_count + 1
                    
                    # Break if we have enough
                    if len(result) >= n:
                        break
                
                # If we don't have enough, add more without category constraints
                if len(result) < n:
                    remaining = [
                        p.to_dict() for _, p in popular.iterrows()
                        if p['id'] not in [r['id'] for r in result]
                    ]
                    
                    for project_dict in remaining[:n - len(result)]:
                        project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0)) / 100
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return top popular without diversity
                result = []
                for _, project in popular.head(n).iterrows():
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0)) / 100
                    result.append(project_dict)
                return result
        elif 'market_cap' in self.projects_df.columns:
            # Use market cap as fallback
            popular = self.projects_df.sort_values('market_cap', ascending=False).head(n)
            
            # Add recommendation score based on market cap
            result = []
            for _, project in popular.iterrows():
                project_dict = project.to_dict()
                # Normalize market cap to 0-1 range for recommendation score
                max_cap = self.projects_df['market_cap'].max()
                if max_cap > 0:
                    project_dict['recommendation_score'] = float(project_dict.get('market_cap', 0)) / max_cap
                else:
                    project_dict['recommendation_score'] = 0.5
                result.append(project_dict)
            
            return result
        else:
            # Just return first n projects with default score
            return [
                {**row.to_dict(), 'recommendation_score': 0.5}
                for _, row in self.projects_df.head(n).iterrows()
            ]


if __name__ == "__main__":
    # Testing the module
    ncf = NCFRecommender()
    
    # Load data
    if ncf.load_data():
        # Train model
        metrics = ncf.train(num_epochs=5, save_model=True)  # Short training for testing
        print(f"Training metrics: {metrics}")
        
        # Test recommendations
        if ncf.users:
            test_user = ncf.users[0]
            print(f"\nRecommendations for user {test_user}:")
            recs = ncf.recommend_projects(test_user, n=5)
            
            for i, rec in enumerate(recs, 1):
                print(f"{i}. {rec.get('name', rec.get('id'))} - Score: {rec.get('recommendation_score', 0):.4f}")
                
        # Test popular projects
        print("\nPopular projects:")
        popular = ncf.get_popular_projects(n=5)
        
        for i, proj in enumerate(popular, 1):
            print(f"{i}. {proj.get('name', proj.get('id'))} - Score: {proj.get('popularity_score', 0):.4f}")
            
        # Test trending projects
        print("\nTrending projects:")
        trending = ncf.get_trending_projects(n=5)
        
        for i, proj in enumerate(trending, 1):
            print(f"{i}. {proj.get('name', proj.get('id'))} - Score: {proj.get('trend_score', 0):.4f}")
    else:
        print("Failed to load data for NCF model")