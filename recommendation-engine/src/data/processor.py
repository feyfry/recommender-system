"""
Modul untuk memproses dan menyiapkan data koin/token Web3
"""

import os
import re
import json
import logging
import pandas as pd
import numpy as np
from datetime import datetime
from typing import Dict, List, Tuple, Optional, Any, Union
import sys

# Tambahkan path root ke sys.path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from config import RAW_DIR, PROCESSED_DIR, USER_PERSONAS

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class DataProcessor:
    """
    Class untuk memproses data cryptocurrency untuk sistem rekomendasi
    """
    
    def __init__(self):
        """
        Inisialisasi processor dengan kategori dan platform normalisasi
        """
        # Pastikan direktori ada
        os.makedirs(PROCESSED_DIR, exist_ok=True)
        
        # Definisikan kategori mapping untuk normalisasi
        self.category_mappings = {
            'defi': ['defi', 'decentralized-finance', 'decentralized-finance-defi', 'lending', 'yield-farming'],
            'nft': ['nft', 'non-fungible-tokens', 'non-fungible-tokens-nft', 'collectibles'],
            'layer-1': ['layer-1', 'layer1', 'l1', 'blockchain-service', 'smart-contract-platform'],
            'layer-2': ['layer-2', 'layer2', 'l2', 'scaling'],
            'gaming': ['gaming', 'play-to-earn', 'p2e', 'game', 'metaverse', 'gaming-guild'],
            'stablecoin': ['stablecoin', 'stablecoin-algorithmically-stabilized', 'stablecoin-asset-backed'],
            'meme': ['meme', 'meme-token', 'dog', 'inu', 'cat', 'food'],
            'exchange': ['exchange', 'exchange-token', 'exchange-based', 'centralized-exchange'],
        }
        
        # Definisikan platform mapping untuk normalisasi chain
        self.blockchain_platforms = {
            'ethereum': ['eth', 'erc20', 'erc-20', 'erc721', 'erc-721'],
            'binance-smart-chain': ['bnb', 'bsc', 'bep20', 'bep-20'],
            'solana': ['sol'],
            'polygon-pos': ['polygon', 'matic'],
            'avalanche': ['avax'],
            'tron': ['trx'],
            'cardano': ['ada']
        }
    
    def load_latest_data(self) -> Tuple[Optional[pd.DataFrame], Optional[pd.DataFrame], Optional[pd.DataFrame]]:
        """
        Load data mentah terbaru dari direktori RAW_DIR
        
        Returns:
            tuple: (projects_df, categories_df, trending_df)
        """
        logger.info("Loading raw data")
        
        # Mencari file market data terbaru
        market_files = [f for f in os.listdir(RAW_DIR) if f.startswith("coins_markets_") and f.endswith(".json")]
        
        if not market_files:
            logger.error("No market data files found")
            return None, None, None
        
        # Mengumpulkan semua data market
        all_market_data = []
        for file in market_files:
            try:
                with open(os.path.join(RAW_DIR, file), 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if isinstance(data, list):
                        all_market_data.extend(data)
            except Exception as e:
                logger.error(f"Error loading market data file {file}: {e}")
        
        if not all_market_data:
            logger.error("No market data loaded")
            return None, None, None
        
        # Membuat DataFrame market dan menghapus duplikat
        market_df = pd.DataFrame(all_market_data)
        market_df = market_df.drop_duplicates(subset='id')
        
        # Load detail coin
        detail_files = [f for f in os.listdir(RAW_DIR) if f.startswith("coin_details_") and f.endswith(".json")]
        detailed_data = []
        
        for file in detail_files:
            try:
                with open(os.path.join(RAW_DIR, file), 'r', encoding='utf-8') as f:
                    data = json.load(f)
                
                # Extract coin ID from filename
                coin_id = file.replace("coin_details_", "").replace(".json", "")
                
                # Extract relevant details
                community_data = data.get('community_data', {}) or {}
                developer_data = data.get('developer_data', {}) or {}
                
                detailed_info = {
                    'id': coin_id,
                    'platforms': data.get('platforms', {}),
                    'categories': data.get('categories', []),
                    'reddit_subscribers': community_data.get('reddit_subscribers', 0),
                    'twitter_followers': community_data.get('twitter_followers', 0),
                    'github_stars': developer_data.get('stars', 0),
                    'github_subscribers': developer_data.get('subscribers', 0),
                    'github_forks': developer_data.get('forks', 0),
                    'description': data.get('description', {}).get('en', ''),
                    'genesis_date': data.get('genesis_date'),
                    'sentiment_votes_up_percentage': data.get('sentiment_votes_up_percentage', 50)
                }
                detailed_data.append(detailed_info)
            except Exception as e:
                logger.error(f"Error processing detail file {file}: {e}")
        
        # Buat DataFrame detail
        detailed_df = pd.DataFrame(detailed_data) if detailed_data else None
        
        # Load kategori
        categories_df = None
        if os.path.exists(os.path.join(RAW_DIR, "coins_categories.json")):
            try:
                with open(os.path.join(RAW_DIR, "coins_categories.json"), 'r', encoding='utf-8') as f:
                    categories_data = json.load(f)
                categories_df = pd.DataFrame(categories_data)
            except Exception as e:
                logger.error(f"Error loading categories data: {e}")
        
        # Load trending
        trending_df = None
        if os.path.exists(os.path.join(RAW_DIR, "trending_coins.json")):
            try:
                with open(os.path.join(RAW_DIR, "trending_coins.json"), 'r', encoding='utf-8') as f:
                    trending_data = json.load(f)
                if 'coins' in trending_data:
                    trending_coins = [item['item'] for item in trending_data['coins']]
                    trending_df = pd.DataFrame(trending_coins)
            except Exception as e:
                logger.error(f"Error loading trending data: {e}")
        
        # Merge market data dengan detail data
        projects_df = None
        if detailed_df is not None and not detailed_df.empty:
            projects_df = market_df.merge(detailed_df, on='id', how='left', suffixes=('', '_detailed'))
        else:
            projects_df = market_df
        
        logger.info(f"Loaded {len(projects_df)} projects, {len(categories_df) if categories_df is not None else 0} categories")
        return projects_df, categories_df, trending_df
    
    def process_data(self, n_users: int = 500) -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
        """
        Proses data mentah dan persiapkan untuk sistem rekomendasi
        
        Args:
            n_users: Jumlah user sintetis untuk dibuat
            
        Returns:
            tuple: (projects_df, interactions_df, features_df)
        """
        logger.info("Processing data")
        
        # Load data mentah
        projects_df, categories_df, trending_df = self.load_latest_data()
        
        if projects_df is None or projects_df.empty:
            raise ValueError("No project data available")
            
        # 1. Bersihkan dan lengkapi data proyek
        projects_df = self._clean_project_data(projects_df, trending_df)
        
        # 2. Buat fitur untuk rekomendasi
        features_df = self._create_features(projects_df)
        
        # 3. Buat data interaksi sintetis
        interactions_df = self._create_synthetic_interactions(projects_df, n_users)
        
        # 4. Simpan data yang sudah diproses
        self._save_processed_data(projects_df, interactions_df, features_df)
        
        return projects_df, interactions_df, features_df
    
    def _clean_project_data(self, projects_df: pd.DataFrame, trending_df: Optional[pd.DataFrame] = None) -> pd.DataFrame:
        """
        Bersihkan dan standarisasi data proyek
        
        Args:
            projects_df: DataFrame proyek yang akan dibersihkan
            trending_df: DataFrame proyek trending (opsional)
            
        Returns:
            pd.DataFrame: DataFrame proyek yang sudah dibersihkan
        """
        logger.info("Cleaning project data")
        
        # Buat salinan untuk dimodifikasi
        df = projects_df.copy()
        
        # Tangani nilai NaN
        df['market_cap'] = df['market_cap'].fillna(0)
        df['total_volume'] = df['total_volume'].fillna(0)
        df['current_price'] = df['current_price'].fillna(0)
        
        # Pastikan kolom numerical ada
        for col in ['price_change_percentage_24h', 'price_change_percentage_7d_in_currency', 
                   'price_change_percentage_30d_in_currency', 'price_change_percentage_1h_in_currency']:
            if col in df.columns:
                df[col] = df[col].fillna(0)
            else:
                df[col] = 0
        
        # Tangani kolom sosial
        for col in ['reddit_subscribers', 'twitter_followers', 'github_stars', 
                  'github_subscribers', 'github_forks']:
            if col in df.columns:
                df[col] = df[col].fillna(0).astype(int)
            else:
                df[col] = 0
        
        # Standarisasi platforms dan categories
        if 'platforms' in df.columns:
            # Jika platforms adalah string, konversi ke dict
            if df['platforms'].dtype == 'object' and isinstance(df['platforms'].iloc[0], str):
                df['platforms'] = df['platforms'].apply(lambda x: json.loads(x) if isinstance(x, str) else x)
            # Pastikan nilai None diganti dengan dict kosong
            df['platforms'] = df['platforms'].fillna({})
        else:
            df['platforms'] = [{} for _ in range(len(df))]
            
        # Tangani kolom categories dengan cara yang lebih aman
        if 'categories' in df.columns:
            def clean_categories(x):
                if isinstance(x, (list, np.ndarray)):
                    return x
                if pd.isna(x) or x is None:
                    return []
                if isinstance(x, str):
                    try:
                        return json.loads(x)
                    except:
                        return []
                return []
            
            df['categories'] = df['categories'].apply(clean_categories)
        else:
            df['categories'] = [[] for _ in range(len(df))]
        
        # Ekstrak primary_category dan chain
        df['primary_category'] = df.apply(lambda row: self._extract_primary_category(row['categories']), axis=1)
        df['chain'] = df.apply(lambda row: self._extract_primary_chain(row['platforms']), axis=1)
        
        # Hitung skor popularitas dan tren
        df = self._calculate_metrics(df)
        
        # Tambahkan skor trending jika ada data trending
        if trending_df is not None and not trending_df.empty:
            trending_ids = trending_df['id'].tolist() if 'id' in trending_df.columns else []
            df['is_trending'] = df['id'].apply(lambda x: 1 if x in trending_ids else 0)
            # Boost trend score untuk koin trending
            df.loc[df['is_trending'] == 1, 'trend_score'] += 30
        else:
            df['is_trending'] = 0
        
        return df
    
    def _extract_primary_category(self, categories: List[str]) -> str:
        """
        Ekstrak kategori utama dari daftar kategori
        
        Args:
            categories: Daftar kategori koin
            
        Returns:
            str: Kategori utama yang dinormalisasi
        """
        if not categories:
            return 'unknown'
            
        # Normalisasi kategori
        normalized_categories = [cat.lower() for cat in categories if cat]
        
        # Cek setiap kategori terhadap mapping
        for category_name, aliases in self.category_mappings.items():
            for alias in aliases:
                if alias in normalized_categories:
                    return category_name
        
        # Jika tidak ada match, ambil kategori pertama
        return categories[0].lower() if categories else 'unknown'
    
    def _extract_primary_chain(self, platforms: Dict[str, str]) -> str:
        """
        Ekstrak blockchain utama dari daftar platforms
        
        Args:
            platforms: Dictionary platform (blockchain -> alamat kontrak)
            
        Returns:
            str: Chain utama yang dinormalisasi
        """
        # Handle NaN or non-dict values
        if pd.isna(platforms) or not isinstance(platforms, dict):
            return 'unknown'
                
        if not platforms:
            return 'unknown'
                
        # Prioritas chain
        priority_chains = ['ethereum', 'binance-smart-chain', 'solana', 'polygon-pos', 'avalanche']
        
        # Cek apakah ada priority chain yang match
        for chain in priority_chains:
            if chain in platforms:
                return chain
        
        # Cek menggunakan normalisasi dengan aliases
        platform_keys = [p.lower() for p in platforms.keys()]
        for chain_name, aliases in self.blockchain_platforms.items():
            for alias in aliases:
                if alias in platform_keys:
                    return chain_name
        
        # Jika tidak ada match, ambil platform pertama
        return list(platforms.keys())[0] if platforms else 'unknown'
    
    def _calculate_metrics(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Hitung metrik seperti popularitas, tren, aktivitas developer, dll.
        
        Args:
            df: DataFrame proyek
            
        Returns:
            pd.DataFrame: DataFrame dengan metrik tambahan
        """
        logger.info("Calculating additional metrics")
        
        # Buat salinan untuk dimodifikasi
        result_df = df.copy()
        
        # 1. Enhanced Popularity Score (based on market cap, volume, and social metrics)
        # Gunakan log untuk mengatasi perbedaan skala yang besar
        market_cap = np.log1p(result_df['market_cap'].fillna(0)) / 30
        volume = np.log1p(result_df['total_volume'].fillna(0)) / 25
        
        # Social metrics dengan pembobotan
        reddit = np.log1p(result_df['reddit_subscribers'].fillna(0)) / 15
        twitter = np.log1p(result_df['twitter_followers'].fillna(0)) / 15
        github = np.log1p(result_df['github_stars'].fillna(0)) / 10
        
        # Combined popularity score
        popularity_score = (
            0.35 * market_cap + 
            0.25 * volume + 
            0.15 * reddit + 
            0.15 * twitter +
            0.10 * github
        )
        
        # Scale to 0-100
        result_df['popularity_score'] = popularity_score * 100
        
        # 2. Trend Score
        price_24h = result_df['price_change_percentage_24h'].fillna(0) / 100
        price_24h = price_24h.clip(-1, 1)  # Clip extreme values
        
        # Get 7d and 30d changes if available
        price_7d = result_df['price_change_percentage_7d_in_currency'].fillna(0) / 100
        price_7d = price_7d.clip(-1, 1)
        
        price_30d = result_df['price_change_percentage_30d_in_currency'].fillna(0) / 100
        price_30d = price_30d.clip(-1, 1)
        
        # Weighted trend score dengan decay (recent changes more important)
        trend_score = (
            0.6 * price_24h + 
            0.3 * price_7d + 
            0.1 * price_30d
        )
        
        # Scale to 0-100 with 50 as neutral
        result_df['trend_score'] = 50 + (trend_score * 50)
        
        # 3. Developer Activity Score
        if all(col in result_df.columns for col in ['github_stars', 'github_forks']):
            github_stats = np.log1p(result_df['github_stars']) + np.log1p(result_df['github_forks'])
            max_stats = github_stats.quantile(0.95)
            
            if max_stats > 0:
                dev_score = (github_stats / max_stats).clip(0, 1)
                result_df['developer_activity_score'] = (dev_score * 100).clip(0, 100)
            else:
                result_df['developer_activity_score'] = 0
        else:
            result_df['developer_activity_score'] = 0
        
        # 4. Social Engagement Score (ratio of followers to market cap)
        if 'market_cap' in result_df.columns and result_df['market_cap'].max() > 0:
            social_sum = result_df['reddit_subscribers'] + result_df['twitter_followers']
            market_cap_millions = result_df['market_cap'] / 1_000_000
            
            # Avoid division by zero
            market_cap_norm = market_cap_millions.replace(0, np.nan)
            
            # Calculate engagement ratio
            engagement_ratio = social_sum / market_cap_norm
            
            # Fill NaN values with median
            median_ratio = engagement_ratio.median()
            engagement_ratio = engagement_ratio.fillna(median_ratio)
            
            # Apply log and normalize
            engagement_score = np.log1p(engagement_ratio)
            max_score = engagement_score.quantile(0.95)  # Gunakan percentile 95 untuk menghindari outlier
            engagement_score = engagement_score / max_score
            
            # Scale to 0-100
            result_df['social_engagement_score'] = (engagement_score * 100).clip(0, 100)
        else:
            result_df['social_engagement_score'] = 50
        
        # 5. Description Length (NEW) - Panjang deskripsi sebagai indikator kualitas dokumentasi
        if 'description' in result_df.columns:
            result_df['description_length'] = result_df['description'].fillna('').apply(len)
            # Normalize to 0-100 scale
            max_length = result_df['description_length'].quantile(0.95)  # 95th percentile to avoid outliers
            if max_length > 0:
                result_df['description_length'] = (result_df['description_length'] / max_length * 100).clip(0, 100)
        else:
            result_df['description_length'] = 0
            
        # 6. Age Days (NEW) - Usia proyek dalam hari
        if 'genesis_date' in result_df.columns:
            today = pd.Timestamp.now().date()
            result_df['age_days'] = result_df['genesis_date'].apply(
                lambda x: (today - pd.to_datetime(x).date()).days if pd.notna(x) else 0
            )
        else:
            result_df['age_days'] = 0
            
        # 7. Maturity Score (NEW) - Kombinasi dari usia, aktivitas developer, dan engagement sosial
        # Proyek yang lebih tua, dengan aktivitas developer tinggi dan engagement sosial yang baik 
        # dianggap lebih matang
        age_score = np.log1p(result_df['age_days']) / np.log1p(result_df['age_days'].quantile(0.95))
        age_score = age_score.fillna(0).clip(0, 1)
        
        dev_score = result_df['developer_activity_score'] / 100
        social_score = result_df['social_engagement_score'] / 100
        
        # Weighted maturity score
        maturity_score = (0.4 * age_score + 0.4 * dev_score + 0.2 * social_score)
        result_df['maturity_score'] = (maturity_score * 100).clip(0, 100)
        
        return result_df
    
    def _create_features(self, projects_df: pd.DataFrame) -> pd.DataFrame:
        """
        Buat matriks fitur untuk model rekomendasi
        
        Args:
            projects_df: DataFrame proyek
            
        Returns:
            pd.DataFrame: DataFrame fitur
        """
        logger.info("Creating feature matrix")
        
        # Pilih dan standarisasi fitur numerik
        numeric_features = [
            'market_cap', 'total_volume', 'current_price',
            'price_change_percentage_24h', 'price_change_percentage_7d_in_currency',
            'popularity_score', 'trend_score', 'developer_activity_score', 'social_engagement_score'
        ]
        
        # Pilih hanya kolom yang ada
        available_numeric = [col for col in numeric_features if col in projects_df.columns]
        
        # Buat salinan fitur yang ada
        features_df = projects_df[['id'] + available_numeric].copy()
        
        # One-hot encode kategori
        if 'primary_category' in projects_df.columns:
            category_dummies = pd.get_dummies(projects_df['primary_category'], prefix='category')
            features_df = pd.concat([features_df, category_dummies], axis=1)
        
        # One-hot encode chain
        if 'chain' in projects_df.columns:
            chain_dummies = pd.get_dummies(projects_df['chain'], prefix='chain')
            features_df = pd.concat([features_df, chain_dummies], axis=1)
        
        # Normalisasi fitur numerik
        numeric_columns = features_df.columns.difference(['id'])
        
        # Gunakan Min-Max scaling untuk normalisasi (0-1)
        for col in numeric_columns:
            if col in available_numeric:  # Normalisasi hanya fitur numerik
                min_val = features_df[col].min()
                max_val = features_df[col].max()
                
                if max_val > min_val:
                    features_df[col] = (features_df[col] - min_val) / (max_val - min_val)
                else:
                    features_df[col] = 0
        
        return features_df
    
    def _create_synthetic_interactions(self, projects_df: pd.DataFrame, n_users: int = 500) -> pd.DataFrame:
        """
        Buat interaksi user sintetis berdasarkan persona
        
        Args:
            projects_df: DataFrame proyek
            n_users: Jumlah user sintetis yang dibuat
            
        Returns:
            pd.DataFrame: DataFrame interaksi user
        """
        logger.info(f"Creating synthetic interactions for {n_users} users")
        
        # Create RNG instance with seed for reproducibility
        rng = np.random.default_rng(42)
        
        interactions = []
        personas = list(USER_PERSONAS.keys())
        
        # Generate users dengan pola konsisten
        for user_id in range(1, n_users + 1):
            # Assign persona ke user
            user_persona = personas[user_id % len(personas)]
            persona_data = USER_PERSONAS[user_persona]
            
            # Tentukan jumlah interaksi berdasarkan level aktivitas
            activity_level = rng.choice(['low', 'medium', 'high'], p=[0.2, 0.5, 0.3])
            
            if activity_level == 'low':
                n_interactions = rng.integers(3, 10)
            elif activity_level == 'medium':
                n_interactions = rng.integers(10, 25)
            else:  # high
                n_interactions = rng.integers(25, 50)
            
            # Filter proyek berdasarkan kategori preferensi
            preferred_categories = persona_data['categories']
            category_weights = persona_data['weights']
            
            # Untuk setiap interaksi, pilih proyek berdasarkan preferensi
            for _ in range(n_interactions):
                # Pilih kategori berdasarkan preferensi
                selected_category = rng.choice(preferred_categories, p=category_weights)
                
                # Filter proyek berdasarkan kategori
                category_projects = projects_df[projects_df['primary_category'] == selected_category]
                
                if category_projects.empty:
                    # Fallback ke semua proyek jika tidak ada match
                    category_projects = projects_df
                
                # Pilih proyek dengan weight berdasarkan popularitas dan tren
                weights = category_projects['popularity_score'] * category_projects['trend_score']
                weights = weights / weights.sum()
                
                # Pilih proyek dengan probability berdasarkan weights
                try:
                    selected_index = rng.choice(category_projects.index, p=weights)
                    selected_project = category_projects.loc[selected_index]
                except:
                    # Fallback jika ada masalah dengan weights
                    selected_project = category_projects.sample(1).iloc[0]
                
                # Tentukan tipe interaksi berdasarkan persona
                if user_persona == 'defi_enthusiast':
                    interaction_probs = [0.3, 0.3, 0.3, 0.1]  # view, favorite, portfolio_add, research
                elif user_persona == 'nft_collector':
                    interaction_probs = [0.3, 0.4, 0.2, 0.1]
                elif user_persona == 'trader':
                    interaction_probs = [0.2, 0.2, 0.4, 0.2]
                elif user_persona == 'conservative_investor':
                    interaction_probs = [0.4, 0.2, 0.3, 0.1]
                elif user_persona == 'risk_taker':
                    interaction_probs = [0.3, 0.3, 0.3, 0.1]
                else:
                    interaction_probs = [0.4, 0.3, 0.2, 0.1]
                
                interaction_type = rng.choice(
                    ['view', 'favorite', 'portfolio_add', 'research'],
                    p=interaction_probs
                )
                
                # Set interaction weight based on interaction type
                if interaction_type == 'view':
                    weight = rng.integers(1, 3)
                elif interaction_type == 'favorite':
                    weight = rng.integers(3, 5)
                elif interaction_type == 'portfolio_add':
                    weight = rng.integers(4, 6)
                else:  # research
                    weight = rng.integers(2, 4)
                
                # Tambahkan ke interaksi
                interactions.append({
                    'user_id': f"user_{user_id}",
                    'project_id': selected_project['id'],
                    'interaction_type': interaction_type,
                    'weight': weight,
                    'timestamp': datetime.now().isoformat()
                })
        
        # Buat DataFrame
        interactions_df = pd.DataFrame(interactions)
        
        return interactions_df
    
    def _save_processed_data(self, projects_df: pd.DataFrame, interactions_df: pd.DataFrame, 
                            features_df: pd.DataFrame) -> None:
        """
        Simpan data yang sudah diproses
        
        Args:
            projects_df: DataFrame proyek
            interactions_df: DataFrame interaksi
            features_df: DataFrame fitur
        """
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Simpan DataFrame
        projects_path = os.path.join(PROCESSED_DIR, f"projects_{timestamp}.csv")
        interactions_path = os.path.join(PROCESSED_DIR, f"interactions_{timestamp}.csv")
        features_path = os.path.join(PROCESSED_DIR, f"features_{timestamp}.csv")
        
        # Simpan standard path juga
        projects_std_path = os.path.join(PROCESSED_DIR, "projects.csv")
        interactions_std_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        features_std_path = os.path.join(PROCESSED_DIR, "features.csv")
        
        # Simpan dengan timestamp
        projects_df.to_csv(projects_path, index=False)
        interactions_df.to_csv(interactions_path, index=False)
        features_df.to_csv(features_path, index=False)
        
        # Simpan standard path
        projects_df.to_csv(projects_std_path, index=False)
        interactions_df.to_csv(interactions_std_path, index=False)
        features_df.to_csv(features_std_path, index=False)
        
        logger.info(f"Saved processed data to {PROCESSED_DIR}")
    
    def load_processed_data(self) -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
        """
        Load data yang sudah diproses dari standar path
        
        Returns:
            tuple: (projects_df, interactions_df, features_df)
        """
        # Standard paths
        projects_path = os.path.join(PROCESSED_DIR, "projects.csv")
        interactions_path = os.path.join(PROCESSED_DIR, "interactions.csv")
        features_path = os.path.join(PROCESSED_DIR, "features.csv")
        
        # Cek apakah file ada
        if not all(os.path.exists(path) for path in [projects_path, interactions_path, features_path]):
            logger.warning("Processed data files not found, processing raw data...")
            return self.process_data()
        
        # Load data
        projects_df = pd.read_csv(projects_path)
        interactions_df = pd.read_csv(interactions_path)
        features_df = pd.read_csv(features_path)
        
        logger.info(f"Loaded processed data: {len(projects_df)} projects, {len(interactions_df)} interactions")
        return projects_df, interactions_df, features_df


if __name__ == "__main__":
    # Test mode
    processor = DataProcessor()
    
    # Process data
    try:
        projects_df, interactions_df, features_df = processor.process_data(n_users=100)
        
        print(f"Processed {len(projects_df)} projects")
        print(f"Generated {len(interactions_df)} interactions")
        print(f"Created feature matrix with shape {features_df.shape}")
        
        # Print top projects by popularity
        print("\nTop 5 projects by popularity:")
        top_popular = projects_df.sort_values('popularity_score', ascending=False).head(5)
        for _, row in top_popular.iterrows():
            print(f"{row['name']} ({row['symbol']}): Score={row['popularity_score']:.2f}, Category={row['primary_category']}")
        
        # Print top trending projects
        print("\nTop 5 trending projects:")
        top_trending = projects_df.sort_values('trend_score', ascending=False).head(5)
        for _, row in top_trending.iterrows():
            print(f"{row['name']} ({row['symbol']}): Score={row['trend_score']:.2f}, Category={row['primary_category']}")
        
    except ValueError as e:
        print(f"Error: {e}")
        print("Make sure to run data collection first!")