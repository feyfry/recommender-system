"""
Neural Collaborative Filtering alternatif menggunakan TensorFlow
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

import tensorflow as tf
from tensorflow.keras.models import Model
from tensorflow.keras.layers import Input, Embedding, Flatten, Dense, Multiply, Concatenate, Dropout, BatchNormalization
from tensorflow.keras.optimizers import Adam
from tensorflow.keras.regularizers import l2
from tensorflow.keras.callbacks import EarlyStopping, ModelCheckpoint
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


class NCFRecommender:
    """
    Recommender using Neural Collaborative Filtering with TensorFlow
    """
    
    def __init__(self, params: Optional[Dict[str, Any]] = None):
        """
        Initialize NCF Recommender
        
        Args:
            params: Model parameters (overwrites defaults from config)
        """
        # Model parameters
        self.params = params or NCF_PARAMS
        
        # Initialize model
        self.model = None
        self.user_encoder = LabelEncoder()
        self.item_encoder = LabelEncoder()
        
        # Setup device and memory management for TensorFlow
        physical_devices = tf.config.list_physical_devices('GPU')
        if physical_devices:
            try:
                # Allow memory growth for GPU
                for device in physical_devices:
                    tf.config.experimental.set_memory_growth(device, True)
                logger.info(f"Using GPU: {physical_devices}")
            except Exception as e:
                logger.warning(f"Error configuring GPU: {e}")
                
        # Data
        self.projects_df = None
        self.interactions_df = None
        self.user_item_matrix = None
        
        # Keep track of original IDs
        self.users = None
        self.items = None
        
        # Max rating for normalization
        self.max_rating = None
        
        # Extra tracking for model state
        self.is_model_loaded = False
    
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
                
                # Detect max rating value for normalization
                self.max_rating = float(self.interactions_df['weight'].max())
                logger.info(f"Detected max rating value: {self.max_rating}")
                
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
        
        # Ensure max_rating is detected
        if self.max_rating is None:
            self.max_rating = float(self.interactions_df['weight'].max())
            logger.info(f"Detected max rating value: {self.max_rating}")
        
        for user in self.users:
            user_interactions = self.user_item_matrix.loc[user]
            for item in self.items:
                rating = user_interactions[item]
                if rating > 0:  # Only positive interactions
                    user_idx = self.user_encoder.transform([user])[0]
                    item_idx = self.item_encoder.transform([item])[0]
                    interactions.append((user_idx, item_idx, rating))
        
        # Convert to arrays
        interaction_array = np.array(interactions)
        
        if len(interaction_array) == 0:
            raise ValueError("No interactions found in the data")
        
        user_indices = interaction_array[:, 0].astype(np.int32)
        item_indices = interaction_array[:, 1].astype(np.int32)
        ratings = interaction_array[:, 2].astype(np.float32)
        
        # Log normalization info
        logger.info(f"Prepared {len(ratings)} ratings for normalization with max value: {self.max_rating}")
        logger.info(f"Rating statistics - Min: {ratings.min():.4f}, Max: {ratings.max():.4f}, Mean: {ratings.mean():.4f}")
        
        return user_indices, item_indices, ratings
    
    def _build_model(self, num_users: int, num_items: int) -> tf.keras.Model:
        """
        Build NCF model
        
        Args:
            num_users: Number of users
            num_items: Number of items
            
        Returns:
            tf.keras.Model: Built model
        """
        # Parameters
        embedding_dim = self.params['embedding_dim']
        layers = self.params['layers']
        reg = self.params.get('weight_decay', 1e-3)
        dropout_rate = self.params.get('dropout', 0.2)
        
        # Define inputs
        user_input = Input(shape=(1,), name='user_input')
        item_input = Input(shape=(1,), name='item_input')
        
        # GMF part
        user_embedding_gmf = Embedding(num_users, embedding_dim, embeddings_regularizer=l2(reg),
                                      name='user_embedding_gmf')(user_input)
        item_embedding_gmf = Embedding(num_items, embedding_dim, embeddings_regularizer=l2(reg),
                                      name='item_embedding_gmf')(item_input)
        
        # Flatten embeddings
        user_latent_gmf = Flatten()(user_embedding_gmf)
        item_latent_gmf = Flatten()(item_embedding_gmf)
        
        # Element-wise product for GMF
        gmf_vector = Multiply()([user_latent_gmf, item_latent_gmf])
        
        # MLP part
        user_embedding_mlp = Embedding(num_users, embedding_dim, embeddings_regularizer=l2(reg),
                                      name='user_embedding_mlp')(user_input)
        item_embedding_mlp = Embedding(num_items, embedding_dim, embeddings_regularizer=l2(reg),
                                      name='item_embedding_mlp')(item_input)
        
        # Flatten embeddings
        user_latent_mlp = Flatten()(user_embedding_mlp)
        item_latent_mlp = Flatten()(item_embedding_mlp)
        
        # Concatenate embeddings for MLP
        mlp_vector = Concatenate()([user_latent_mlp, item_latent_mlp])
        
        # Build MLP layers
        for i, layer_size in enumerate(layers):
            mlp_vector = Dense(
                layer_size, 
                activation='relu',
                kernel_regularizer=l2(reg),
                name=f'mlp_layer_{i}'
            )(mlp_vector)
            mlp_vector = BatchNormalization(name=f'batch_norm_{i}')(mlp_vector)
            mlp_vector = Dropout(dropout_rate, name=f'dropout_{i}')(mlp_vector)
        
        # Concatenate GMF and MLP
        predict_vector = Concatenate()([gmf_vector, mlp_vector])
        
        # Final prediction layer
        prediction = Dense(1, activation='sigmoid', kernel_regularizer=l2(reg),
                         name='prediction')(predict_vector)
        
        # Build model
        model = Model([user_input, item_input], prediction)
        
        return model
    
    def train(self, val_ratio: Optional[float] = None, batch_size: Optional[int] = None, 
      num_epochs: Optional[int] = None, learning_rate: Optional[float] = None,
      save_model: bool = True) -> Dict[str, Any]:
        """
        Train the NCF model with Early Stopping
        
        Args:
            val_ratio: Validation data ratio
            batch_size: Batch size
            num_epochs: Number of epochs
            learning_rate: Learning rate
            save_model: Whether to save the model after training
            
        Returns:
            dict: Training metrics
        """
        # Use config params if not specified
        val_ratio = val_ratio if val_ratio is not None else self.params.get('val_ratio', 0.2)
        batch_size = batch_size if batch_size is not None else self.params.get('batch_size', 256)
        num_epochs = num_epochs if num_epochs is not None else self.params.get('epochs', 30)
        learning_rate = learning_rate if learning_rate is not None else self.params.get('learning_rate', 0.0005)
        
        logger.info("Starting NCF training with TensorFlow")
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
        
        train_users = user_indices[train_indices]
        train_items = item_indices[train_indices]
        train_ratings = ratings[train_indices]
        
        val_users = user_indices[val_indices]
        val_items = item_indices[val_indices]
        val_ratings = ratings[val_indices]
        
        # Create data inputs directly instead of using generators
        all_items = np.unique(item_indices)
        
        # Create direct dataset inputs instead of using generators
        def create_tf_dataset(users, items, ratings, batch_size, shuffle=True):
            """
            Create TensorFlow dataset with proper type handling
            
            Args:
                users: Array of user indices
                items: Array of item indices
                ratings: Array of ratings
                batch_size: Batch size
                shuffle: Whether to shuffle the dataset
                
            Returns:
                tf.data.Dataset: TensorFlow dataset
            """
            # Pastikan semua array memiliki tipe data yang benar
            users = np.array(users, dtype=np.int32)
            items = np.array(items, dtype=np.int32)
            ratings = np.array(ratings, dtype=np.float32)  # Penting: Gunakan float32 konsisten
            
            # Create positive samples
            pos_dataset = tf.data.Dataset.from_tensor_slices(
                ((users, items), ratings / self.max_rating)
            )
            
            # Sample negative items
            neg_users = np.repeat(users, 4)  # 4 negative samples per positive
            neg_items = []
            # Penting: Gunakan float32 untuk konsistensi dengan dataset positif
            neg_labels = np.zeros(len(neg_users), dtype=np.float32)
            
            # Create RNG untuk reproducibility dengan metode yang direkomendasikan
            rng = np.random.Generator(np.random.PCG64(42))  # Alternatif: np.random.default_rng(42)
            
            # Create user-item map untuk negative sampling
            user_items_map = {}
            for u, i in zip(users, items):
                if u not in user_items_map:
                    user_items_map[u] = set()
                user_items_map[u].add(i)
            
            # Generate negative samples
            for i, user in enumerate(neg_users):
                while True:
                    neg_item = rng.choice(all_items)
                    if user not in user_items_map or neg_item not in user_items_map[user]:
                        neg_items.append(neg_item)
                        break
            
            # Konversi ke array NumPy dengan tipe yang benar
            neg_items = np.array(neg_items, dtype=np.int32)
            
            neg_dataset = tf.data.Dataset.from_tensor_slices(
                ((neg_users, neg_items), neg_labels)
            )
            
            # Combine positive and negative samples
            dataset = pos_dataset.concatenate(neg_dataset)
            
            # Shuffle and batch
            if shuffle:
                dataset = dataset.shuffle(buffer_size=10000, seed=42)
            
            dataset = dataset.batch(batch_size)
            dataset = dataset.prefetch(tf.data.AUTOTUNE)
            
            return dataset

        
        # Create training and validation datasets
        train_dataset = create_tf_dataset(train_users, train_items, train_ratings, batch_size)
        val_dataset = create_tf_dataset(val_users, val_items, val_ratings, batch_size, shuffle=False)
        
        # Initialize model
        num_users = len(self.user_encoder.classes_)
        num_items = len(self.item_encoder.classes_)
        
        self.model = self._build_model(num_users, num_items)
        
        # Compile model
        self.model.compile(
            optimizer=Adam(learning_rate=learning_rate),
            loss='binary_crossentropy',
            metrics=['mse', 'mae']
        )
        
        # Set up callbacks
        callbacks = [
            EarlyStopping(
                monitor='val_loss',
                patience=5,
                restore_best_weights=True,
                verbose=1
            )
        ]
        
        # Create a temporary model checkpoint
        temp_model_path = os.path.join(MODELS_DIR, "temp_ncf_model.h5")
        os.makedirs(os.path.dirname(temp_model_path), exist_ok=True)
        
        model_checkpoint = ModelCheckpoint(
            temp_model_path,
            monitor='val_loss',
            save_best_only=True,
            verbose=0
        )
        callbacks.append(model_checkpoint)
        
        # Train model
        logger.info(f"Starting training for {num_epochs} epochs")
        history = self.model.fit(
            train_dataset,
            validation_data=val_dataset,
            epochs=num_epochs,
            callbacks=callbacks,
            verbose=1
        )
        
        # Clean up temporary model file
        if os.path.exists(temp_model_path):
            os.remove(temp_model_path)
        
        # Calculate metrics
        train_loss = history.history['loss']
        val_loss = history.history['val_loss']
        
        total_time = time.time() - start_time
        logger.info(f"Training completed in {total_time:.2f}s")
        
        # Set model loaded flag
        self.is_model_loaded = True
        
        # Save model if requested
        if save_model:
            # Save model to default file and ncf_model.pkl for compatibility with hybrid
            model_path = os.path.join(MODELS_DIR, "ncf_tf_model.pkl")
            self.save_model(model_path)
            
            # Save also to ncf_model.pkl for compatibility with hybrid
            compat_path = os.path.join(MODELS_DIR, "ncf_model.pkl")
            self.save_model(compat_path)
        
        return {
            'train_loss': train_loss,
            'val_loss': val_loss,
            'training_time': total_time,
            'early_stopped': len(train_loss) < num_epochs,
            'best_epoch': np.argmin(val_loss) + 1
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
            filepath = os.path.join(MODELS_DIR, f"ncf_tf_model_{timestamp}.pkl")
            
        # Create directory if it doesn't exist
        os.makedirs(os.path.dirname(filepath), exist_ok=True)
        
        # Standardisasi penamaan file weights dengan format yang benar
        # PENTING: File harus diakhiri dengan ".weights.h5"
        weights_dirname = os.path.dirname(filepath)
        weights_basename = os.path.basename(filepath).replace('.pkl', '.weights.h5')
        weights_path = os.path.join(weights_dirname, weights_basename)
        
        logger.info(f"Saving model weights to {weights_path}")
        
        try:
            # Save model weights
            self.model.save_weights(weights_path)
            
            # Save model state
            model_state = {
                'model_config': self.model.get_config(),
                'model_weights_path': weights_path,  # Use consistent path
                'user_encoder': self.user_encoder,
                'item_encoder': self.item_encoder,
                'users': self.users,
                'items': self.items,
                'max_rating': self.max_rating,
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
            
        except Exception as e:
            logger.error(f"Error saving model: {str(e)}")
            # Rollback jika terjadi error
            if os.path.exists(weights_path):
                try:
                    os.remove(weights_path)
                except:
                    pass
            # Return filepath meskipun gagal, untuk mencegah error "NoneType"
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
            model_config = model_state.get('model_config')
            weights_path = model_state.get('model_weights_path')
            
            if model_config is None:
                logger.error("No 'model_config' key in loaded NCF model")
                return False
                
            self.user_encoder = model_state.get('user_encoder')
            self.item_encoder = model_state.get('item_encoder')
            self.users = model_state.get('users')
            self.items = model_state.get('items')
            self.params = model_state.get('config')
            self.max_rating = model_state.get('max_rating')
            
            if self.max_rating is None:
                logger.warning("No max_rating found in model state, using default value of 5.0")
                self.max_rating = 5.0
            
            # Create model with same architecture
            num_users = len(self.user_encoder.classes_)
            num_items = len(self.item_encoder.classes_)
            
            # Rebuild model
            self.model = self._build_model(num_users, num_items)
            
            # Compile model (needed before loading weights)
            self.model.compile(
                optimizer=Adam(learning_rate=self.params.get('learning_rate', 0.0005)),
                loss='binary_crossentropy',
                metrics=['mse', 'mae']
            )
            
            # Load weights
            weights_loaded = False
            
            # Coba versi path yang ada di file state
            if weights_path and os.path.exists(weights_path):
                logger.info(f"Loading weights from specified path: {weights_path}")
                try:
                    self.model.load_weights(weights_path)
                    weights_loaded = True
                except Exception as e:
                    logger.warning(f"Error loading weights from {weights_path}: {e}")
            
            # Coba alternatif penamaan weights jika belum berhasil
            if not weights_loaded:
                alternate_weights_path = filepath.replace('.pkl', '_weights.h5')
                if os.path.exists(alternate_weights_path):
                    logger.info(f"Trying alternate weights path: {alternate_weights_path}")
                    try:
                        self.model.load_weights(alternate_weights_path)
                        weights_loaded = True
                    except Exception as e:
                        logger.warning(f"Error loading weights from alternate path: {e}")
                        
            # Coba alternatif penamaan lainnya
            if not weights_loaded:
                alternate_weights_path = filepath.replace('.pkl', '.weights.h5')
                if os.path.exists(alternate_weights_path):
                    logger.info(f"Trying second alternate weights path: {alternate_weights_path}")
                    try:
                        self.model.load_weights(alternate_weights_path)
                        weights_loaded = True
                    except Exception as e:
                        logger.warning(f"Error loading weights from second alternate path: {e}")
            
            # Coba model weights langsung dari model state jika ada
            if not weights_loaded and 'model_weights' in model_state:
                logger.info("Loading weights directly from model state")
                try:
                    self.model.set_weights(model_state['model_weights'])
                    weights_loaded = True
                except Exception as e:
                    logger.warning(f"Error loading weights from model state: {e}")
            
            if not weights_loaded:
                logger.warning("Could not load weights, using initialized weights")
            
            # Set model loaded flag
            self.is_model_loaded = True
            
            logger.info(f"NCF model successfully loaded from {filepath}")
            return True
                
        except Exception as e:
            logger.error(f"Error loading NCF model: {str(e)}")
            # Log traceback for debugging
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
        
        # Gunakan flag tambahan untuk deteksi status model
        return self.is_model_loaded
    
    def predict(self, user_id: str, item_id: str) -> float:
        """
        Predict rating for a user-item pair
        
        Args:
            user_id: User ID
            item_id: Item ID
            
        Returns:
            float: Predicted rating (0-1)
        """
        if not self.is_trained():
            logger.error("Model not trained or loaded")
            return 0.0
        
        # Check if user and item exist
        if user_id not in self.users or item_id not in self.items:
            return 0.0
        
        # Encode user and item
        user_idx = self.user_encoder.transform([user_id])[0]
        item_idx = self.item_encoder.transform([item_id])[0]
        
        # Convert to tensors
        user_tensor = np.array([user_idx])
        item_tensor = np.array([item_idx])
        
        # Make prediction
        prediction = self.model.predict([user_tensor, item_tensor], verbose=0)[0][0]
        
        return float(prediction)
    
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
        if not self.is_trained():
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
        
        # Get potential items to recommend
        candidate_items = [item for item in self.items if item not in known_items]
        
        # If no candidates, return empty list
        if not candidate_items:
            return []
        
        # Encode user
        user_idx = self.user_encoder.transform([user_id])[0]
        
        # Generate predictions for all candidate items
        predictions = []
        
        # Process in batches to avoid memory issues
        batch_size = 256
        for i in range(0, len(candidate_items), batch_size):
            batch_items = candidate_items[i:i+batch_size]
            item_indices = self.item_encoder.transform(batch_items)
            
            # Create batch input tensors
            user_tensor = np.full(len(batch_items), user_idx)
            item_tensor = np.array(item_indices)
            
            # Make predictions
            batch_predictions = self.model.predict([user_tensor, item_tensor], verbose=0).flatten()
            
            # Store predictions
            for item, score in zip(batch_items, batch_predictions):
                predictions.append((item, float(score)))
        
        # Sort by prediction score
        predictions.sort(key=lambda x: x[1], reverse=True)
        
        # Return top-n
        return predictions[:n]
    
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
        metrics = ncf.train(num_epochs=10, save_model=True)  # Short training for testing
        print(f"Training metrics: {metrics}")
        
        # Test loading model
        model_path = os.path.join(MODELS_DIR, "ncf_tf_model.pkl")
        if os.path.exists(model_path):
            print("\nTesting model loading...")
            success = ncf.load_model(model_path)
            print(f"Model loading {'successful' if success else 'failed'}")
        
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
            
    else:
        print("Failed to load data for NCF model")