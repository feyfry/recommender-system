"""
Neural Collaborative Filtering menggunakan PyTorch
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
    Dataset untuk Neural Collaborative Filtering dengan perbaikan untuk tensor kosong
    """
    
    def __init__(self, user_indices: np.ndarray, item_indices: np.ndarray, 
                 ratings: np.ndarray, num_negative: int = 4):
        """
        Initialize dataset
        
        Args:
            user_indices: Array user indices
            item_indices: Array item indices
            ratings: Array ratings/interactions
            num_negative: Jumlah sampel negatif per sampel positif
        """
        self.user_indices = user_indices
        self.item_indices = item_indices
        self.ratings = ratings
        self.num_negative = num_negative
        
        # Konversi ke tensor
        self.user_indices_tensor = torch.LongTensor(user_indices)
        self.item_indices_tensor = torch.LongTensor(item_indices)
        self.ratings_tensor = torch.FloatTensor(ratings)
        
        # Map untuk negative sampling
        self.user_item_map = self._create_user_item_map()
        
        # Generate semua unique item indices
        self.all_items = np.unique(item_indices)
        
        # Generate negative samples di awal untuk menghindari inconsistency
        self.neg_samples = self._pregenerate_negative_samples()
        
        # Panjang total dataset: sampel positif + sampel negatif
        self.length = len(ratings) + len(self.neg_samples)

    def _regenerate_negative_samples(self):
        """
        Pre-generate negative samples untuk menghindari tensor size mismatch
        """
        self.negative_samples = []
        # Gunakan seed tetap untuk konsistensi
        rng = np.random.default_rng(seed=42)
        
        for pos_idx in range(len(self.user_indices)):
            user_idx = self.user_indices[pos_idx]
            interacted_items = set(self.user_item_map.get(user_idx, []))
            
            user_neg_samples = []
            for _ in range(self.num_negative):
                # Coba sebanyak 10 kali untuk menemukan item yang belum diinteraksi
                for attempt in range(10):
                    neg_item_idx = rng.choice(self.all_items)
                    if neg_item_idx not in interacted_items:
                        user_neg_samples.append(neg_item_idx)
                        break
                    # Jika setelah 10 kali tidak menemukan, gunakan item pertama
                    if attempt == 9:
                        neg_item_idx = self.all_items[0]
                        user_neg_samples.append(neg_item_idx)
            
            # Add to negative samples list
            self.negative_samples.append(user_neg_samples)

    def _pregenerate_negative_samples(self):
        """
        Generate negative samples di awal
        
        Returns:
            list: List tuple (user_idx, item_idx, rating) untuk sampel negatif
        """
        # Gunakan seed tetap untuk reproducibility
        rng = np.random.default_rng(42)
        neg_samples = []
        
        for user_idx in self.user_indices:
            # Get items yang sudah diinteraksi
            interacted_items = set(self.user_item_map.get(user_idx, []))
            
            # Generate negative samples untuk user ini
            for _ in range(self.num_negative):
                # Cari item yang belum diinteraksi
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
    
    def _get_negative_item(self, user_idx: int) -> int:
        """
        Generate negative item (not interacted by user)
        
        Args:
            user_idx: User index
            
        Returns:
            int: Negative item index
        """
        # Items that user has interacted with
        interacted_items = set(self.user_item_map.get(user_idx, []))
        
        # Membuat instance Generator
        rng = np.random.default_rng(seed=42)  # or use hash(user_id) for user-specific seeding
        
        # Randomly select an item
        while True:
            neg_item_idx = rng.choice(self.all_items)
            if neg_item_idx not in interacted_items:
                return neg_item_idx


class NCFModel(nn.Module):
    """
    Neural Collaborative Filtering Model
    """
    
    def __init__(self, num_users: int, num_items: int, 
             embedding_dim: int,
             layers: List[int],
             dropout: float):
        """
        Initialize NCF model
        
        Args:
            num_users: Number of users
            num_items: Number of items
            embedding_dim: Dimension of embeddings
            layers: List of MLP layer sizes
            dropout: Dropout rate
        """
        super(NCFModel, self).__init__()
        
        # GMF part (General Matrix Factorization)
        self.user_embedding_gmf = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_gmf = nn.Embedding(num_items, embedding_dim)
        
        # MLP part (Multi-Layer Perceptron)
        self.user_embedding_mlp = nn.Embedding(num_users, embedding_dim)
        self.item_embedding_mlp = nn.Embedding(num_items, embedding_dim)
        
        # MLP layers
        self.mlp_layers = nn.ModuleList()
        input_size = 2 * embedding_dim
        
        for i, layer_size in enumerate(layers):
            self.mlp_layers.append(nn.Linear(input_size, layer_size))
            self.mlp_layers.append(nn.ReLU())
            self.mlp_layers.append(nn.BatchNorm1d(layer_size))
            self.mlp_layers.append(nn.Dropout(dropout))
            input_size = layer_size
        
        # Output layer
        self.output_layer = nn.Linear(layers[-1] + embedding_dim, 1)
        self.sigmoid = nn.Sigmoid()
        
        # Initialize weights
        self._init_weights()
    
    def _init_weights(self):
        """Initialize model weights"""
        # Xavier/Glorot initialization
        for m in self.modules():
            if isinstance(m, nn.Linear):
                nn.init.xavier_uniform_(m.weight)
                if m.bias is not None:
                    nn.init.zeros_(m.bias)
            elif isinstance(m, nn.Embedding):
                nn.init.normal_(m.weight, mean=0, std=0.01)
    
    def forward(self, user_indices, item_indices):
        """Forward pass"""
        # GMF part
        user_embedding_gmf = self.user_embedding_gmf(user_indices)
        item_embedding_gmf = self.item_embedding_gmf(item_indices)
        gmf_vector = user_embedding_gmf * item_embedding_gmf
        
        # MLP part
        user_embedding_mlp = self.user_embedding_mlp(user_indices)
        item_embedding_mlp = self.item_embedding_mlp(item_indices)
        mlp_vector = torch.cat([user_embedding_mlp, item_embedding_mlp], dim=-1)
        
        # Process through MLP layers
        for i in range(0, len(self.mlp_layers), 4):
            mlp_vector = self.mlp_layers[i](mlp_vector)
            mlp_vector = self.mlp_layers[i+1](mlp_vector)
            mlp_vector = self.mlp_layers[i+2](mlp_vector)
            mlp_vector = self.mlp_layers[i+3](mlp_vector)
        
        # Concatenate GMF and MLP vectors
        combined_vector = torch.cat([gmf_vector, mlp_vector], dim=-1)
        
        # Final prediction
        output = self.output_layer(combined_vector)
        output = self.sigmoid(output)
        
        return output.view(-1)


class NCFRecommender:
    """
    Recommender using Neural Collaborative Filtering
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
    
    def _prepare_data(self) -> Tuple[np.ndarray, np.ndarray, np.ndarray]:
        """
        Prepare data for training
        
        Returns:
            tuple: (user_indices, item_indices, ratings)
        """
        # Convert interactions to training format
        interactions = []
        
        # Tentukan nilai max rating dari data secara dinamis
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
        
        user_indices = interaction_array[:, 0]
        item_indices = interaction_array[:, 1]
        ratings = interaction_array[:, 2]
        
        # Log normalization info
        logger.info(f"Normalized {len(ratings)} ratings to range [0, 1]")
        logger.info(f"Rating statistics - Min: {ratings.min():.4f}, Max: {ratings.max():.4f}, Mean: {ratings.mean():.4f}")
        
        return user_indices, item_indices, ratings
    
    def custom_collate_fn(self, batch):
        """
        Custom collate function untuk menangani tensor dengan ukuran yang berbeda
        
        Args:
            batch: Batch data dari DataLoader
            
        Returns:
            tuple: (users_tensor, items_tensor, ratings_tensor)
        """
        users = []
        items = []
        ratings = []
        
        for user, item, rating in batch:
            # Handle single scalar value
            if isinstance(user, torch.Tensor) and user.dim() == 0:
                user = user.unsqueeze(0)
            if isinstance(item, torch.Tensor) and item.dim() == 0:
                item = item.unsqueeze(0)
            if isinstance(rating, torch.Tensor) and rating.dim() == 0:
                rating = rating.unsqueeze(0)
                
            # Handle empty tensors
            if user.numel() == 0:
                user = torch.zeros(1, dtype=torch.long)
            if item.numel() == 0:
                item = torch.zeros(1, dtype=torch.long)
            if rating.numel() == 0:
                rating = torch.zeros(1, dtype=torch.float)
                
            # Ensure all tensors are 1D and same length
            if user.dim() > 1:
                user = user.flatten()
            if item.dim() > 1:
                item = item.flatten()
            if rating.dim() > 1:
                rating = rating.flatten()
                
            # Ensure all tensors have same length
            max_len = max(user.size(0), item.size(0), rating.size(0))
            if user.size(0) < max_len:
                user = torch.cat([user, torch.zeros(max_len - user.size(0), dtype=torch.long)])
            if item.size(0) < max_len:
                item = torch.cat([item, torch.zeros(max_len - item.size(0), dtype=torch.long)])
            if rating.size(0) < max_len:
                rating = torch.cat([rating, torch.zeros(max_len - rating.size(0), dtype=torch.float)])
                
            users.append(user)
            items.append(item)
            ratings.append(rating)
        
        try:
            # Stack tensors
            users_tensor = torch.stack(users)
            items_tensor = torch.stack(items)
            ratings_tensor = torch.stack(ratings)
            
            return users_tensor, items_tensor, ratings_tensor
        except Exception as e:
            # Fallback jika stack masih gagal
            print(f"Warning: Error in collate_fn: {e}")
            
            # Buat tensor dummy
            batch_size = len(batch)
            return (
                torch.zeros((batch_size, 1), dtype=torch.long),
                torch.zeros((batch_size, 1), dtype=torch.long),
                torch.zeros((batch_size, 1), dtype=torch.float)
            )
        
    def train(self, val_ratio: Optional[float] = None, batch_size: Optional[int] = None, 
          num_epochs: Optional[int] = None, learning_rate: Optional[float] = None,
          save_model: bool = True) -> Dict[str, List[float]]:
        """
        Train the NCF model (Versi dengan Early Stopping)
        
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
        
        logger.info("Starting NCF training")
        start_time = time.time()
        
        # Prepare data
        try:
            user_indices, item_indices, ratings = self._prepare_data()
        except ValueError as e:
            logger.error(f"Error preparing data: {str(e)}")
            return {"error": str(e)}
        
        # Split data into train and validation
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
        
        # Create datasets dengan implementasi baru
        train_dataset = NCFDataset(
            train_user_indices, train_item_indices, train_ratings, num_negative=4
        )
        val_dataset = NCFDataset(
            val_user_indices, val_item_indices, val_ratings, num_negative=4
        )
        
        # Create dataloaders with custom collate function
        train_loader = DataLoader(
            train_dataset, 
            batch_size=batch_size, 
            shuffle=True, 
            num_workers=0,  # Using 0 to avoid issues with pickle
            collate_fn=self.custom_collate_fn
        )
        
        val_loader = DataLoader(
            val_dataset, 
            batch_size=batch_size, 
            shuffle=False, 
            num_workers=0,
            collate_fn=self.custom_collate_fn
        )
        
        # Initialize model
        num_users = len(self.user_encoder.classes_)
        num_items = len(self.item_encoder.classes_)

        self.model = NCFModel(
            num_users=num_users,
            num_items=num_items,
            embedding_dim=self.params['embedding_dim'],
            layers=self.params['layers'],
            dropout=self.params['dropout']
        ).to(self.device)
        
        # Loss function and optimizer
        criterion = nn.BCELoss()
        optimizer = optim.Adam(
            self.model.parameters(), 
            lr=learning_rate,
            weight_decay=self.params.get('weight_decay', 1e-3)  # Peningkatan weight decay
        )
        
        # Training loop
        train_losses = []
        val_losses = []
        
        # Early stopping parameters
        patience = 5  # Jumlah epoch untuk sabar menunggu improvement
        best_val_loss = float('inf')
        patience_counter = 0
        best_model_state = None
        
        logger.info(f"Starting training for {num_epochs} epochs")
        
        for epoch in range(1, num_epochs + 1):
            epoch_start_time = time.time()
            
            # Training
            self.model.train()
            train_loss = 0
            train_batches = 0
            
            for batch_idx, (user_indices, item_indices, ratings) in enumerate(train_loader):
                try:
                    # Make sure to squeeze if dimensions are extra
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
                    optimizer.step()
                    
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
                        # Make sure to squeeze if dimensions are extra
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
            
            # Early stopping check
            if avg_val_loss < best_val_loss:
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
                    f"Time: {epoch_time:.2f}s")
            
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
            
            self.model = NCFModel(
                num_users=num_users,
                num_items=num_items,
                embedding_dim=config['embedding_dim'],
                layers=config['layers'],
                dropout=config['dropout']
            ).to(self.device)
            
            # Load weights
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
        Generate recommendations for a user with score normalization
        and diversity promotion
        
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
        try:
            if self.projects_df is not None and 'primary_category' in self.projects_df.columns:
                # Map item_id to category for easier lookup
                item_to_category = dict(zip(self.projects_df['id'], self.projects_df['primary_category']))
        except Exception as e:
            logger.warning(f"Error getting category information: {str(e)}")
            item_to_category = {}
        
        # Get potential items to recommend
        candidate_items = [item for item in self.items if item not in known_items]
        
        # If no candidates, return empty list
        if not candidate_items:
            return []
        
        # Encode user
        user_idx = self.user_encoder.transform([user_id])[0]
        user_tensor = torch.LongTensor([user_idx]).to(self.device)
        
        # Generate predictions for all candidate items
        predictions = []
        
        # Process in batches to avoid memory issues
        batch_size = 128
        for i in range(0, len(candidate_items), batch_size):
            batch_items = candidate_items[i:i+batch_size]
            item_indices = self.item_encoder.transform(batch_items)
            item_tensor = torch.LongTensor(item_indices).to(self.device)
            
            # Replicate user tensor for each item
            batch_user_tensor = user_tensor.repeat(len(batch_items))
            
            # Make predictions
            self.model.eval()
            with torch.no_grad():
                batch_predictions = self.model(batch_user_tensor, item_tensor).cpu().numpy()
            
            # Store predictions
            predictions.extend(list(zip(batch_items, batch_predictions)))
        
        # Sort by prediction score
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        # If we have less recommendations than requested, return what we have
        if len(predictions) <= n:
            return predictions
        
        # NORMALISASI & DIVERSITY ENHANCEMENT
        # Taking more candidates than needed to ensure variety
        top_candidates = predictions[:min(len(predictions), n*3)]
        
        # 1. Score Normalization - distribute scores more evenly
        min_score = min([score for _, score in top_candidates])
        max_score = max([score for _, score in top_candidates])
        score_range = max(0.001, max_score - min_score)  # Avoid division by zero
        
        # Apply normalization
        normalized_predictions = []
        for item_id, score in top_candidates:
            # Standard min-max normalization
            norm_score = (score - min_score) / score_range
            normalized_predictions.append((item_id, norm_score))
        
        # 2. Diversity Enhancement
        if item_to_category:
            # First select top few items normally
            top_k = n // 3  # Select 1/3 of items by raw score
            final_predictions = normalized_predictions[:top_k]
            
            # Track categories already included
            selected_categories = {
                item_to_category.get(item_id, 'unknown')
                for item_id, _ in final_predictions
            }
            
            # Select remaining items with diversity boost
            remaining_candidates = normalized_predictions[top_k:]
            
            # Order by score but boost items from new categories
            while len(final_predictions) < n and remaining_candidates:
                next_item = None
                best_score = -1
                
                for idx, (item_id, score) in enumerate(remaining_candidates):
                    item_category = item_to_category.get(item_id, 'unknown')
                    
                    # Apply diversity boost for new categories
                    diversity_boost = 0.1 if item_category not in selected_categories else 0
                    adjusted_score = score + diversity_boost
                    
                    if adjusted_score > best_score:
                        best_score = adjusted_score
                        next_item = (idx, item_id, score, item_category)
                
                if next_item:
                    idx, item_id, original_score, category = next_item
                    final_predictions.append((item_id, original_score))
                    selected_categories.add(category)
                    remaining_candidates.pop(idx)
                else:
                    break
            
            # Add any remaining needed items by score
            if len(final_predictions) < n:
                for item_id, score in remaining_candidates:
                    final_predictions.append((item_id, score))
                    if len(final_predictions) >= n:
                        break
            
            # Re-sort the final list by score
            final_predictions.sort(key=lambda x: x[1], reverse=True)
            return final_predictions[:n]
        else:
            # If no category information, just return top normalized predictions
            return normalized_predictions[:n]
    
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