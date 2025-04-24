"""
Neural Collaborative Filtering menggunakan PyTorch dengan optimasi untuk domain cryptocurrency
"""

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
    Dataset untuk Neural Collaborative Filtering dengan optimasi untuk domain cryptocurrency
    """
    
    def __init__(self, user_indices: np.ndarray, item_indices: np.ndarray, 
                 ratings: np.ndarray, num_negative: int = 4,
                 item_categories: Optional[Dict[int, str]] = None,
                 item_trend_scores: Optional[Dict[int, float]] = None):
        """
        Initialize dataset
        
        Args:
            user_indices: Array user indices
            item_indices: Array item indices
            ratings: Array ratings/interactions
            num_negative: Jumlah sampel negatif per sampel positif
            item_categories: Map dari item indices ke kategori (untuk negative sampling yang lebih baik)
            item_trend_scores: Map dari item indices ke trend scores (untuk sampling berdasarkan popularitas)
        """
        self.user_indices = user_indices
        self.item_indices = item_indices
        self.ratings = ratings
        self.num_negative = num_negative
        self.item_categories = item_categories
        self.item_trend_scores = item_trend_scores
        
        # Konversi ke tensor
        self.user_indices_tensor = torch.LongTensor(user_indices)
        self.item_indices_tensor = torch.LongTensor(item_indices)
        self.ratings_tensor = torch.FloatTensor(ratings)
        
        # Map untuk negative sampling
        self.user_item_map = self._create_user_item_map()
        
        # Tambahkan user-category map untuk sampling yang lebih cerdas jika kategori tersedia
        self.user_category_map = {}
        if self.item_categories:
            self._create_user_category_map()
        
        # Generate semua unique item indices
        self.all_items = np.unique(item_indices)
        
        # Generate negative samples di awal untuk menghindari inconsistency
        self.neg_samples = self._pregenerate_negative_samples()
        
        # Panjang total dataset: sampel positif + sampel negatif
        self.length = len(ratings) + len(self.neg_samples)

    def _create_user_category_map(self):
        """
        Buat mapping user ke kategori yang disukai berdasarkan interaksi positif
        Berguna untuk negative sampling yang lebih cerdas
        """
        for user_idx, item_idx in zip(self.user_indices, self.item_indices):
            if user_idx not in self.user_category_map:
                self.user_category_map[user_idx] = {}
                
            if self.item_categories and item_idx in self.item_categories:
                category = self.item_categories[item_idx]
                if category not in self.user_category_map[user_idx]:
                    self.user_category_map[user_idx][category] = 0
                self.user_category_map[user_idx][category] += 1

    def _pregenerate_negative_samples(self):
        """
        Generate negative samples di awal dengan strategi yang dioptimalkan untuk cryptocurrency
        
        Returns:
            list: List tuple (user_idx, item_idx, rating) untuk sampel negatif
        """
        # Gunakan seed tetap untuk reproducibility
        rng = np.random.default_rng(42)
        neg_samples = []
        
        for i, user_idx in enumerate(self.user_indices):
            # Get items yang sudah diinteraksi
            interacted_items = set(self.user_item_map.get(user_idx, []))
            
            # Jika ada kategori data, kita bisa melakukan stratified negative sampling
            user_preferred_categories = []
            if user_idx in self.user_category_map:
                # Dapatkan kategori yang disukai user berdasarkan frekuensi interaksi
                user_preferred_categories = sorted(
                    self.user_category_map[user_idx].items(), 
                    key=lambda x: x[1], 
                    reverse=True
                )
                user_preferred_categories = [cat for cat, _ in user_preferred_categories]
            
            # Generate negative samples untuk user ini
            for _ in range(self.num_negative):
                # Strategi sampling yang berbeda berdasarkan availability data
                if user_preferred_categories and self.item_categories and rng.random() < 0.7:
                    # 70% probability: Sample dari kategori yang disukai tapi item yang belum diinteraksi
                    # Ini membantu model untuk membedakan item yang disukai vs tidak dalam kategori yang sama
                    preferred_cat = rng.choice(user_preferred_categories)
                    
                    # Cari item dalam kategori ini yang belum diinteraksi
                    cat_items = [
                        item for item in self.all_items 
                        if item in self.item_categories and self.item_categories[item] == preferred_cat
                        and item not in interacted_items
                    ]
                    
                    if cat_items:
                        neg_item_idx = rng.choice(cat_items)
                    else:
                        # Jika tidak ada item yang cocok, lakukan random sampling
                        while True:
                            neg_item_idx = rng.choice(self.all_items)
                            if neg_item_idx not in interacted_items:
                                break
                elif self.item_trend_scores and rng.random() < 0.3:
                    # 30% probability: Sample item berdasarkan popularitas/trending (untuk domain crypto)
                    # Buat distribusi probabilitas berdasarkan trend score
                    items = []
                    scores = []
                    
                    for item in self.all_items:
                        if item not in interacted_items and item in self.item_trend_scores:
                            items.append(item)
                            scores.append(self.item_trend_scores[item])
                    
                    if items:
                        # Normalisasi scores untuk probability distribution
                        scores = np.array(scores)
                        min_score = scores.min() if scores.size > 0 else 0
                        max_score = scores.max() if scores.size > 0 else 1
                        
                        if max_score > min_score:
                            # Normalisasi untuk sampling probabilitas
                            probs = (scores - min_score) / (max_score - min_score)
                            probs = probs / probs.sum()
                            
                            neg_item_idx = rng.choice(items, p=probs)
                        else:
                            neg_item_idx = rng.choice(items)
                    else:
                        # Jika tidak ada item yang cocok, lakukan random sampling
                        while True:
                            neg_item_idx = rng.choice(self.all_items)
                            if neg_item_idx not in interacted_items:
                                break
                else:
                    # Random sampling dari semua item yang belum diinteraksi
                    while True:
                        neg_item_idx = rng.choice(self.all_items)
                        if neg_item_idx not in interacted_items:
                            break
                
                # Tambahkan sebagai sample negatif
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
        """
        Buat mapping user -> items untuk negative sampling
        
        Returns:
            dict: Mapping user ke list item yang diinteraksi
        """
        user_item_map = {}
        for user_idx, item_idx in zip(self.user_indices, self.item_indices):
            if user_idx not in user_item_map:
                user_item_map[user_idx] = []
            user_item_map[user_idx].append(item_idx)
        return user_item_map


class CryptoNCFModel(nn.Module):
    """
    Neural Collaborative Filtering Model dioptimalkan untuk domain cryptocurrency
    dengan arsitektur yang lebih dalam dan fitur khusus
    """
    
    def __init__(self, num_users: int, num_items: int, 
             embedding_dim: int,
             layers: List[int],
             dropout: float):
        super(CryptoNCFModel, self).__init__()
        
        # Embeddings yang terpisah untuk GMF dan MLP
        self.user_embedding_gmf = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_gmf = nn.Embedding(num_items, embedding_dim)
        
        self.user_embedding_mlp = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_mlp = nn.Embedding(num_items, embedding_dim)
        
        # MLP layers
        self.mlp_layers = nn.ModuleList()
        input_size = 2 * embedding_dim  # Concatenated embeddings
        
        for i, layer_size in enumerate(layers):
            if i == 0:
                self.mlp_layers.append(nn.Linear(input_size, layer_size))
            else:
                self.mlp_layers.append(nn.Linear(layers[i-1], layer_size))
            
            # Use LeakyReLU for better gradients on negative values
            self.mlp_layers.append(nn.LeakyReLU(0.1))
            self.mlp_layers.append(nn.BatchNorm1d(layer_size))
            self.mlp_layers.append(nn.Dropout(dropout))
        
        # Output layer: combine GMF and MLP results
        self.output_layer = nn.Linear(layers[-1] + embedding_dim, 1)
        self.sigmoid = nn.Sigmoid()
        
        # Initialize weights
        self._init_weights()
    
    def _init_weights(self):
        """
        Improved weight initialization for faster convergence
        """
        for m in self.modules():
            if isinstance(m, nn.Linear):
                # Use Kaiming initialization for linear layers with LeakyReLU
                nn.init.kaiming_normal_(m.weight, mode='fan_in', nonlinearity='leaky_relu')
                if m.bias is not None:
                    nn.init.zeros_(m.bias)
            elif isinstance(m, nn.Embedding):
                # Normal initialization for embeddings with smaller variance
                nn.init.normal_(m.weight, std=0.01)
    
    def forward(self, user_indices, item_indices):
        # GMF path
        user_embedding_gmf = self.user_embedding_gmf(user_indices)
        item_embedding_gmf = self.item_embedding_gmf(item_indices)
        gmf_vector = user_embedding_gmf * item_embedding_gmf  # Element-wise product
        
        # MLP path
        user_embedding_mlp = self.user_embedding_mlp(user_indices)
        item_embedding_mlp = self.item_embedding_mlp(item_indices)
        mlp_vector = torch.cat([user_embedding_mlp, item_embedding_mlp], dim=-1)
        
        # Process through MLP layers
        for i in range(0, len(self.mlp_layers), 4):
            mlp_vector = self.mlp_layers[i](mlp_vector)      # Linear
            mlp_vector = self.mlp_layers[i+1](mlp_vector)    # LeakyReLU
            mlp_vector = self.mlp_layers[i+2](mlp_vector)    # BatchNorm
            mlp_vector = self.mlp_layers[i+3](mlp_vector)    # Dropout
        
        # Combine GMF and MLP paths
        combined = torch.cat([gmf_vector, mlp_vector], dim=-1)
        
        # Final prediction
        output = self.output_layer(combined)
        return self.sigmoid(output).view(-1)


class NCFRecommender:
    """
    Recommender using Neural Collaborative Filtering dioptimalkan untuk cryptocurrency
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None, use_cuda: bool = True):
        """
        Initialize NCF Recommender
        
        Args:
            params: Model parameters (overwrites defaults from config)
            use_cuda: Whether to use CUDA for training if available
        """
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
        self.item_trend_scores = None
    
    def load_data(self, 
                 projects_path: Optional[str] = None, 
                 interactions_path: Optional[str] = None) -> bool:
        """
        Load data for the model
        
        Args:
            projects_path: Path to projects data
            interactions_path: Path to interactions data
            
        Returns:
            bool: Success status
        """
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
                
                # Extract category and trend information for better sampling
                if 'primary_category' in self.projects_df.columns:
                    self.item_categories = dict(zip(self.projects_df['id'], self.projects_df['primary_category']))
                    logger.info(f"Extracted categories for {len(self.item_categories)} items")
                
                if 'trend_score' in self.projects_df.columns:
                    self.item_trend_scores = dict(zip(self.projects_df['id'], self.projects_df['trend_score']))
                    logger.info(f"Extracted trend scores for {len(self.item_trend_scores)} items")
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
            return False
    
    def _prepare_data(self) -> Tuple[np.ndarray, np.ndarray, np.ndarray, Dict[int, str], Dict[int, float]]:
        """
        Prepare data for training with item metadata for improved sampling
        
        Returns:
            tuple: (user_indices, item_indices, ratings, encoded_categories, encoded_trend_scores)
        """
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
        
        # Convert item categories and trend scores to encoded indices
        encoded_categories = {}
        encoded_trend_scores = {}
        
        if self.item_categories:
            for item_id, category in self.item_categories.items():
                if item_id in self.items:  # Only include items in our training data
                    item_idx = self.item_encoder.transform([item_id])[0]
                    encoded_categories[item_idx] = category
        
        if self.item_trend_scores:
            for item_id, score in self.item_trend_scores.items():
                if item_id in self.items:  # Only include items in our training data
                    item_idx = self.item_encoder.transform([item_id])[0]
                    encoded_trend_scores[item_idx] = score
        
        # Log normalization info
        logger.info(f"Normalized {len(ratings)} ratings to range [0, 1]")
        logger.info(f"Rating statistics - Min: {ratings.min():.4f}, Max: {ratings.max():.4f}, Mean: {ratings.mean():.4f}")
        
        return user_indices, item_indices, ratings, encoded_categories, encoded_trend_scores
        
    def train(self, val_ratio: Optional[float] = None, batch_size: Optional[int] = None, 
        num_epochs: Optional[int] = None, learning_rate: Optional[float] = None,
        save_model: bool = True) -> Dict[str, List[float]]:
        """
        Train the NCF model dengan arsitektur hybrid GMF+MLP dan optimasi kustom
        
        Args:
            val_ratio: Validation data ratio
            batch_size: Batch size
            num_epochs: Number of epochs
            learning_rate: Learning rate
            save_model: Whether to save the model after training
            
        Returns:
            dict: Training metrics (loss per epoch)
        """
        # Use config params if not specified
        val_ratio = val_ratio if val_ratio is not None else self.params.get('val_ratio', 0.2)
        batch_size = batch_size if batch_size is not None else self.params.get('batch_size', 128)
        num_epochs = num_epochs if num_epochs is not None else self.params.get('epochs', 20)
        learning_rate = learning_rate if learning_rate is not None else self.params.get('learning_rate', 0.001)
        
        logger.info("Starting NCF training with optimized architecture")
        start_time = time.time()
        
        # Prepare data
        try:
            user_indices, item_indices, ratings, encoded_categories, encoded_trend_scores = self._prepare_data()
        except ValueError as e:
            logger.error(f"Error preparing data: {str(e)}")
            return {"error": str(e)}
        
        # Try stratified split, fall back to random split if not possible
        try:
            # Split data into train and validation with stratification by user
            train_indices, val_indices = train_test_split(
                np.arange(len(user_indices)), 
                test_size=val_ratio, 
                random_state=42,
                stratify=user_indices  # Stratified by user for better balance
            )
        except ValueError as e:
            logger.warning(f"Could not perform stratified split: {e}")
            logger.warning("Falling back to random split without stratification")
            train_indices, val_indices = train_test_split(
                np.arange(len(user_indices)), 
                test_size=val_ratio, 
                random_state=42
                # No stratify parameter
            )
        
        train_user_indices = user_indices[train_indices]
        train_item_indices = item_indices[train_indices]
        train_ratings = ratings[train_indices]
        
        val_user_indices = user_indices[val_indices]
        val_item_indices = item_indices[val_indices]
        val_ratings = ratings[val_indices]
        
        # Dynamic negative sampling ratio based on dataset size
        num_negative = self.params.get('negative_ratio', 6)
        logger.info(f"Using {num_negative} negative samples per positive interaction")
        
        # Create datasets dengan metadata untuk improved sampling
        train_dataset = NCFDataset(
            train_user_indices, train_item_indices, train_ratings, 
            num_negative=num_negative,
            item_categories=encoded_categories,
            item_trend_scores=encoded_trend_scores
        )
        val_dataset = NCFDataset(
            val_user_indices, val_item_indices, val_ratings, 
            num_negative=num_negative,
            item_categories=encoded_categories,
            item_trend_scores=encoded_trend_scores
        )
        
        # Use appropriate number of workers based on system
        import multiprocessing
        num_workers = min(4, max(1, multiprocessing.cpu_count() // 2))
        
        # Create dataloaders
        train_loader = DataLoader(
            train_dataset, 
            batch_size=batch_size, 
            shuffle=True, 
            num_workers=0,  # Using 0 to avoid issues with pickle
            pin_memory=self.use_cuda  # Better GPU memory transfer
        )
        
        val_loader = DataLoader(
            val_dataset, 
            batch_size=batch_size, 
            shuffle=False, 
            num_workers=0,
            pin_memory=self.use_cuda
        )
        
        # Initialize model with enhanced architecture
        num_users = len(self.user_encoder.classes_)
        num_items = len(self.item_encoder.classes_)

        self.model = CryptoNCFModel(
            num_users=num_users,
            num_items=num_items,
            embedding_dim=self.params['embedding_dim'],
            layers=self.params['layers'],
            dropout=self.params['dropout']
        ).to(self.device)
        
        # Use Binary Cross Entropy loss
        criterion = nn.BCELoss()
        
        # Use AdamW with weight decay for better regularization
        optimizer = optim.AdamW(
            self.model.parameters(), 
            lr=learning_rate,
            weight_decay=self.params.get('weight_decay', 1e-4)
        )
        
        # Learning rate scheduler with warm-up and cosine annealing
        # First few epochs use increasing learning rate, then decay
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
        patience = self.params.get('patience', 15)  # Increased patience for better convergence
        best_val_loss = float('inf')
        patience_counter = 0
        best_model_state = None

        logger.info(f"Starting training for {num_epochs} epochs with patience {patience}")
        
        for epoch in range(1, num_epochs + 1):
            epoch_start_time = time.time()
            
            # Training
            self.model.train()
            train_loss = 0
            train_batches = 0
            
            for batch_idx, (user_indices, item_indices, ratings) in enumerate(train_loader):
                try:
                    # Make sure tensors are properly shaped
                    if user_indices.dim() > 1 and user_indices.size(1) == 1:
                        user_indices = user_indices.squeeze(1)
                    if item_indices.dim() > 1 and item_indices.size(1) == 1:
                        item_indices = item_indices.squeeze(1)
                    if ratings.dim() > 1 and ratings.size(1) == 1:
                        ratings = ratings.squeeze(1)
                    
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
                        # Make sure tensors are properly shaped
                        if user_indices.dim() > 1 and user_indices.size(1) == 1:
                            user_indices = user_indices.squeeze(1)
                        if item_indices.dim() > 1 and item_indices.size(1) == 1:
                            item_indices = item_indices.squeeze(1)
                        if ratings.dim() > 1 and ratings.size(1) == 1:
                            ratings = ratings.squeeze(1)
                        
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
            logger.info(f"Current learning rate: {current_lr:.6f}")
            
            # Early stopping check with improvement threshold
            if avg_val_loss < best_val_loss * 0.998:  # 0.2% improvement required
                best_val_loss = avg_val_loss
                patience_counter = 0
                # Save best model state
                best_model_state = copy.deepcopy(self.model.state_dict())
                logger.info(f"New best validation loss: {best_val_loss:.4f}")
            else:
                patience_counter += 1
                logger.info(f"Validation loss did not improve. Patience: {patience_counter}/{patience}")
            
            # Log progress
            epoch_time = time.time() - epoch_start_time
            logger.info(f"Epoch {epoch}/{num_epochs} - "
                    f"Train Loss: {avg_train_loss:.4f}, "
                    f"Val Loss: {avg_val_loss:.4f}, "
                    f"Time: {epoch_time:.2f}s, "
                    f"LR: {current_lr:.6f}")
            
             # Check early stopping
            if patience_counter >= patience:
                logger.info(f"Early stopping triggered after {epoch} epochs")
                break
        
        # Restore best model if we did early stopping
        if best_model_state is not None and patience_counter >= patience:
            logger.info(f"Restoring model to best state with validation loss {best_val_loss:.4f}")
            self.model.load_state_dict(best_model_state)
        
        total_time = time.time() - start_time
        logger.info(f"Training completed in {total_time:.2f}s")
        
        # Save model if requested
        if save_model:
            model_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            self.save_model(model_path)
        
        return {
            'train_loss': train_losses,
            'val_loss': val_losses,
            'training_time': total_time,
            'early_stopped': patience_counter >= patience,
            'best_epoch': epoch - patience_counter if patience_counter >= patience else epoch
        }
    
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
        """
        Load model from file
        
        Args:
            filepath: Path to model file
            
        Returns:
            bool: Success status
        """
        try:
            logger.info(f"Attempting to load NCF model from {filepath}")
            
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
            
            # Compatible with both old NCFModel and new CryptoNCFModel
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
                    # Fall back to old architecture (for backward compatibility)
                    from src.models.ncf import NCFModel
                    self.model = NCFModel(
                        num_users=num_users,
                        num_items=num_items,
                        embedding_dim=config['embedding_dim'],
                        layers=config['layers'],
                        dropout=config['dropout']
                    ).to(self.device)
                    self.model.load_state_dict(state_dict)
            except Exception as e:
                # If CryptoNCFModel is not defined yet (first run), use the old model
                logger.warning(f"Falling back to original model architecture: {e}")
                from src.models.ncf import NCFModel
                self.model = NCFModel(
                    num_users=num_users,
                    num_items=num_items,
                    embedding_dim=config['embedding_dim'],
                    layers=config['layers'],
                    dropout=config['dropout']
                ).to(self.device)
                self.model.load_state_dict(state_dict)
            
            self.model.eval()  # Set to evaluation mode
            
            logger.info(f"NCF model successfully loaded from {filepath}")
            return True
                
        except Exception as e:
            logger.error(f"Error loading NCF model: {str(e)}")
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
    
    def predict(self, user_id: str, item_id: str) -> float:
        """
        Predict rating for a user-item pair
        
        Args:
            user_id: User ID
            item_id: Item ID
            
        Returns:
            float: Predicted rating (0-1)
        """
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
        Generate recommendations for a user with optimized diversity and
        crypto-specific boosting for trending projects
        
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
        
        # Check if user exists
        if user_id not in self.users:
            logger.warning(f"User {user_id} not in training data")
            return []
        
        # Get known items to exclude
        known_items = set()
        if exclude_known and self.user_item_matrix is not None:
            if user_id in self.user_item_matrix.index:
                user_interactions = self.user_item_matrix.loc[user_id]
                known_items = set(user_interactions[user_interactions > 0].index)
        
        # Get category information for diversity tracking
        category_counts = {}
        item_to_category = {}
        item_to_chain = {}
        item_to_trend = {}
        
        try:
            if self.projects_df is not None:
                # Map item_id to metadata for easier lookup
                if 'primary_category' in self.projects_df.columns:
                    item_to_category = dict(zip(self.projects_df['id'], self.projects_df['primary_category']))
                if 'chain' in self.projects_df.columns:
                    item_to_chain = dict(zip(self.projects_df['id'], self.projects_df['chain']))
                if 'trend_score' in self.projects_df.columns:
                    item_to_trend = dict(zip(self.projects_df['id'], self.projects_df['trend_score']))
        except Exception as e:
            logger.warning(f"Error getting item metadata: {str(e)}")
        
        # Get potential items to recommend
        candidate_items = [item for item in self.items if item not in known_items]
        
        # If no candidates, return empty list
        if not candidate_items:
            return []
        
        # Encode user
        user_idx = self.user_encoder.transform([user_id])[0]
        user_tensor = torch.LongTensor([user_idx]).to(self.device)
        
        # Generate predictions for all candidate items in batches
        predictions = []
        batch_size = 512  # Increased batch size for efficiency
        
        for i in range(0, len(candidate_items), batch_size):
            batch_items = candidate_items[i:i+batch_size]
            
            try:
                item_indices = self.item_encoder.transform(batch_items)
                item_tensor = torch.LongTensor(item_indices).to(self.device)
                
                # Replicate user tensor for each item
                batch_user_tensor = user_tensor.repeat(len(batch_items))
                
                # Make predictions
                self.model.eval()
                with torch.no_grad():
                    batch_predictions = self.model(batch_user_tensor, item_tensor).cpu().numpy()
                
                # Store predictions with items
                predictions.extend(list(zip(batch_items, batch_predictions)))
            except Exception as e:
                logger.warning(f"Error in batch prediction: {e}")
                # Skip this batch on error
                continue
        
        # If we have too few predictions, try to fallback to a popularity-based approach
        if len(predictions) < n and hasattr(self, 'projects_df'):
            logger.warning(f"Got only {len(predictions)} predictions, adding popularity-based candidates")
            
            # Get popular items that aren't already in our predictions
            predicted_items = {item for item, _ in predictions}
            if 'popularity_score' in self.projects_df.columns:
                popular_items = self.projects_df.sort_values('popularity_score', ascending=False)['id'].tolist()
                
                # Add popular items not already predicted or known
                for item in popular_items:
                    if (item not in predicted_items and 
                        item not in known_items and 
                        len(predictions) < n*2):  # Get twice what we need for filtering later
                        # Add with lower confidence score
                        predictions.append((item, 0.5))
                        predicted_items.add(item)
        
        # Sort by prediction score
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        # If we have fewer recommendations than requested, return what we have
        if len(predictions) <= n:
            return predictions
        
        # Take more candidates for better diversity
        top_candidates = predictions[:min(len(predictions), n*3)]
        
        # Score normalization - sigmoid to enhance differentiation
        min_score = min([score for _, score in top_candidates])
        max_score = max([score for _, score in top_candidates])
        score_range = max(0.001, max_score - min_score)  # Avoid division by zero
        
        # Apply sigmoid normalization
        normalized_candidates = []
        for item_id, score in top_candidates:
            # Normalize to 0-1 range
            norm_score = (score - min_score) / score_range
            # Apply sigmoid for better separation
            sigmoid_score = 1.0 / (1.0 + np.exp(-8 * (norm_score - 0.5)))
            normalized_candidates.append((item_id, sigmoid_score))
        
        # Cryptocurrency-specific boost for trending items
        boosted_candidates = []
        for item_id, score in normalized_candidates:
            final_score = score
            
            # Boost based on trend score - crypto is highly trend-sensitive
            if item_id in item_to_trend:
                trend_score = item_to_trend[item_id]
                if trend_score > 80:  # Very trending
                    final_score += 0.2
                elif trend_score > 65:  # Moderately trending
                    final_score += 0.1
                elif trend_score > 50:  # Slightly trending
                    final_score += 0.05
            
            boosted_candidates.append((item_id, final_score))
        
        # Sort by boosted score
        boosted_candidates.sort(key=lambda x: x[1], reverse=True)
        
        # Ensure category diversity
        if item_to_category:
            # First, select some top items unconditionally
            top_count = max(n // 4, 1)  # 25% by pure score
            result = boosted_candidates[:top_count]
            
            # Track selected categories
            selected_categories = {}
            selected_chains = {}
            
            for item_id, _ in result:
                if item_id in item_to_category:
                    category = item_to_category[item_id]
                    selected_categories[category] = selected_categories.get(category, 0) + 1
                if item_id in item_to_chain:
                    chain = item_to_chain[item_id]
                    selected_chains[chain] = selected_chains.get(chain, 0) + 1
            
            # Max per category (25% of total)
            max_per_category = max(1, int(n * 0.25))
            # Max per chain (40% of total) - chains are less diverse in crypto
            max_per_chain = max(2, int(n * 0.4))
            
            # Remaining candidates
            remaining = boosted_candidates[top_count:]
            
            # Select remaining with diversity in mind
            while len(result) < n and remaining:
                best_idx = -1
                best_adjusted_score = -float('inf')
                
                for idx, (item_id, score) in enumerate(remaining):
                    category_adjustment = 0
                    chain_adjustment = 0
                    
                    # Category diversity
                    if item_id in item_to_category:
                        category = item_to_category[item_id]
                        current_count = selected_categories.get(category, 0)
                        
                        if current_count >= max_per_category:
                            # Heavy penalty for exceeding max per category
                            category_adjustment = -0.5
                        elif current_count == 0:
                            # Strong boost for new categories
                            category_adjustment = 0.3
                        else:
                            # Small adjustment based on count
                            category_adjustment = 0.1 * (1 - current_count / max_per_category)
                    
                    # Chain diversity
                    if item_id in item_to_chain:
                        chain = item_to_chain[item_id]
                        current_count = selected_chains.get(chain, 0)
                        
                        if current_count >= max_per_chain:
                            # Penalty for exceeding max per chain
                            chain_adjustment = -0.3
                        elif current_count == 0:
                            # Boost for new chains
                            chain_adjustment = 0.2
                        else:
                            # Small adjustment
                            chain_adjustment = 0.05 * (1 - current_count / max_per_chain)
                    
                    # Combined adjustment
                    diversity_adjustment = category_adjustment + chain_adjustment * 0.5  # Chain less important than category
                    adjusted_score = score + diversity_adjustment
                    
                    if adjusted_score > best_adjusted_score:
                        best_idx = idx
                        best_adjusted_score = adjusted_score
                
                if best_idx >= 0:
                    item_id, score = remaining.pop(best_idx)
                    result.append((item_id, score))
                    
                    # Update tracking
                    if item_id in item_to_category:
                        category = item_to_category[item_id]
                        selected_categories[category] = selected_categories.get(category, 0) + 1
                    if item_id in item_to_chain:
                        chain = item_to_chain[item_id]
                        selected_chains[chain] = selected_chains.get(chain, 0) + 1
                else:
                    # If no suitable candidate, take next best by score
                    if remaining:
                        item_id, score = remaining.pop(0)
                        result.append((item_id, score))
                        
                        # Update tracking
                        if item_id in item_to_category:
                            category = item_to_category[item_id]
                            selected_categories[category] = selected_categories.get(category, 0) + 1
                        if item_id in item_to_chain:
                            chain = item_to_chain[item_id]
                            selected_chains[chain] = selected_chains.get(chain, 0) + 1
                    else:
                        break
            
            # Sort by final score
            result.sort(key=lambda x: x[1], reverse=True)
            return result[:n]
        
        # If no category info, just return top candidates
        return boosted_candidates[:n]
    
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
                project_dict['recommendation_score'] = float(score)
                
                # Add to results
                detailed_recommendations.append(project_dict)
        
        return detailed_recommendations
    
    def get_cold_start_recommendations(self, 
                                      user_interests: Optional[List[str]] = None,
                                      n: int = 10) -> List[Dict[str, Any]]:
        """
        Get recommendations for cold-start users optimized for cryptocurrency domain
        
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
            
            # If not enough projects match interests, add some trending ones
            if len(filtered_projects) < n * 1.5:
                trending_projects = None
                
                # Get trending projects if available
                if 'trend_score' in self.projects_df.columns:
                    trending_projects = self.projects_df.sort_values('trend_score', ascending=False).head(n)
                    
                    # Combine with category-based recommendations
                    if trending_projects is not None:
                        filtered_projects = pd.concat([filtered_projects, trending_projects])
                        filtered_projects = filtered_projects.drop_duplicates(subset='id')
            
            # If still not enough, add popular general projects
            if len(filtered_projects) < n:
                filtered_projects = self.projects_df
        else:
            # Use combined popularity and trend approach
            filtered_projects = self.projects_df
        
        # Create advanced scoring for crypto cold-start
        if 'popularity_score' in filtered_projects.columns and 'trend_score' in filtered_projects.columns:
            # Normalize both metrics to 0-100 range if needed
            if filtered_projects['popularity_score'].max() > 100:
                filtered_projects['popularity_score'] = 100 * filtered_projects['popularity_score'] / filtered_projects['popularity_score'].max()
            
            if filtered_projects['trend_score'].max() > 100:
                filtered_projects['trend_score'] = 100 * filtered_projects['trend_score'] / filtered_projects['trend_score'].max()
            
            # For crypto, trend matters more than pure popularity for cold start
            filtered_projects['combined_score'] = (
                filtered_projects['popularity_score'] * 0.3 + 
                filtered_projects['trend_score'] * 0.7
            )
            
            # Consider market cap tier for cold-start (prefer established projects for safety)
            if 'market_cap' in filtered_projects.columns:
                # Normalize market cap to 0-1 range
                max_market_cap = filtered_projects['market_cap'].max()
                if max_market_cap > 0:
                    market_cap_normalized = filtered_projects['market_cap'] / max_market_cap
                    
                    # Add market cap bonus - prefer established projects for cold-start
                    filtered_projects['combined_score'] += market_cap_normalized * 20  # Significant boost
            
            # Sort by combined score
            recommendations = filtered_projects.sort_values('combined_score', ascending=False).head(n*2)
            
            # Ensure good diversity in final results
            if 'primary_category' in recommendations.columns:
                # Create tracking for category counts
                selected_categories = {}
                result = []
                
                # Max per category (25% of total)
                max_per_category = max(1, int(n * 0.25))
                
                # Process candidates to ensure category diversity
                for _, project in recommendations.iterrows():
                    category = project['primary_category']
                    current_count = selected_categories.get(category, 0)
                    
                    # Skip if we already have enough from this category
                    if current_count >= max_per_category and len(result) >= n // 2:
                        continue
                    
                    # Add this project
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('combined_score', 0))
                    result.append(project_dict)
                    
                    # Update category tracking
                    selected_categories[category] = current_count + 1
                    
                    # Break if we have enough recommendations
                    if len(result) >= n:
                        break
                
                # If we don't have enough after diversity filtering, add more
                if len(result) < n:
                    # Add remaining recommendations without category constraints
                    remaining = [
                        p.to_dict() for _, p in recommendations.iterrows()
                        if p['id'] not in [r['id'] for r in result]
                    ]
                    
                    for project_dict in remaining[:n - len(result)]:
                        project_dict['recommendation_score'] = float(project_dict.get('combined_score', 0))
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return top recommendations if no category data
                result = []
                for _, project in recommendations.head(n).iterrows():
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('combined_score', 0))
                    result.append(project_dict)
                return result
        else:
            # Just return top n projects if no scores available
            return filtered_projects.head(n).to_dict('records')
    
    def get_trending_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get trending projects based on trend score with category diversity
        
        Args:
            n: Number of trending projects to return
            
        Returns:
            list: List of trending project dictionaries
        """
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
                    project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0))
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
                        project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0))
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return top trending without diversity
                result = []
                for _, project in trending.head(n).iterrows():
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('trend_score', 0))
                    result.append(project_dict)
                return result
        else:
            logger.warning("No trend score available, returning top projects by popularity")
            return self.get_popular_projects(n)
    
    def get_popular_projects(self, n: int = 10) -> List[Dict[str, Any]]:
        """
        Get popular projects based on popularity score with diversity
        
        Args:
            n: Number of popular projects to return
            
        Returns:
            list: List of popular project dictionaries
        """
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
                    project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
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
                        project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
                        result.append(project_dict)
                
                return result[:n]
            else:
                # Just return top popular without diversity
                result = []
                for _, project in popular.head(n).iterrows():
                    project_dict = project.to_dict()
                    project_dict['recommendation_score'] = float(project_dict.get('popularity_score', 0))
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