"""
Konfigurasi utama untuk sistem rekomendasi Web3 (versi yang dioptimalkan)
"""

import os
from pathlib import Path
from dotenv import load_dotenv

# Load .env file
load_dotenv()

# Path dasar
BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = os.path.join(BASE_DIR, "data")
RAW_DIR = os.path.join(DATA_DIR, "raw")
PROCESSED_DIR = os.path.join(DATA_DIR, "processed")
MODELS_DIR = os.path.join(DATA_DIR, "models")

# Pastikan direktori ada
for dir_path in [DATA_DIR, RAW_DIR, PROCESSED_DIR, MODELS_DIR]:
    os.makedirs(dir_path, exist_ok=True)

# CoinGecko API
COINGECKO_API_URL = "https://api.coingecko.com/api/v3"
COINGECKO_API_KEY = os.environ.get("COINGECKO_API_KEY", "")  # Demo API key - Ambil dari .env

# Pengumpulan data
TOP_COINS_LIMIT = 500
TOP_COINS_DETAIL = 100
CATEGORIES = [
    "layer-1",
    "smart-contract-platform", 
    "decentralized-finance-defi",
    "non-fungible-tokens-nft",
    "gaming",
    "meme-token",
    "stablecoins",
    "metaverse",
    "layer-2",
    "liquid-staking",
    "artificial-intelligence",
    "fan-token",
    "move-to-earn",
    "real-world-assets-rwa"
]

# Parameter model rekomendasi
# Neural Collaborative Filtering - NORMAL TUNING
NCF_PARAMS = {
    "embedding_dim": 64,            # Ukuran embedding yang lebih kecil, sesuai dengan ukuran dataset
    "layers": [128, 64, 32],        # Arsitektur yang lebih sederhana
    "learning_rate": 0.001,         # Learning rate yang lebih moderat
    "batch_size": 128,              # Batch size lebih besar untuk stabilitas
    "epochs": 30,                   # Epoch yang cukup tanpa berlebihan
    "val_ratio": 0.2,               # Porsi validasi yang lebih besar
    "dropout": 0.3,                 # Dropout lebih ringan
    "weight_decay": 3e-4,           # Regularisasi lebih ringan
    "patience": 7,                  # Patience lebih pendek
    "negative_ratio": 4             # Negative samples lebih seimbang
}

# Feature-Enhanced CF - MODERATE TUNING
FECF_PARAMS = {
    "no_components": 48,            # Jumlah komponen lebih sedikit (sekitar 10% jumlah item)
    "content_alpha": 0.5            # Keseimbangan antara collaborative dan content features
}

# Hybrid Model - MODERATE TUNING
HYBRID_PARAMS = {
    "ncf_weight": 0.4,              # Bobot NCF lebih kecil (lebih andal CF)
    "fecf_weight": 0.6,             # Bobot FECF lebih besar
    "interaction_threshold_low": 5,  # Threshold lebih tinggi untuk cold start
    "interaction_threshold_high": 15, # Threshold lebih rendah untuk active users
    "diversity_factor": 0.3,        # Faktor diversitas lebih moderat
    "cold_start_fecf_weight": 0.8,  # Bobot FECF untuk cold start
    "explore_ratio": 0.25           # Eksplorasi lebih kecil
}

# Category Configuration - EXTREME TUNING
CATEGORY_CONFIG = {
    "max_per_category": 0.15,       # Even stricter category limits
    "prioritize_diverse": True,
    "boost_underrepresented": 0.5,  # Stronger boost
    "penalty_overrepresented": -0.8 # Much stronger penalty
}

# Keputusan investasi
TRADING_SIGNAL_WINDOW = 14  # Ukuran jendela untuk perhitungan indikator
CONFIDENCE_THRESHOLD = 0.7  # Threshold untuk keyakinan sinyal

# Evaluasi model
EVAL_METRICS = ["precision", "recall", "ndcg", "map", "mrr"]
EVAL_K_VALUES = [5, 10, 20]
EVAL_TEST_RATIO = 0.2
EVAL_RANDOM_SEED = 42

# Persona pengguna yang lebih beragam untuk sintetis data
USER_PERSONAS = {
    "defi_enthusiast": {
        "categories": ["defi", "layer-1", "stablecoin", "liquid-staking"],
        "weights": [0.5, 0.2, 0.2, 0.1]
    },
    "nft_collector": {
        "categories": ["nft", "gaming", "metaverse", "layer-1"],
        "weights": [0.6, 0.2, 0.1, 0.1]
    },
    "trader": {
        "categories": ["layer-1", "defi", "meme-token", "stablecoin"],
        "weights": [0.4, 0.3, 0.2, 0.1]
    },
    "conservative_investor": {
        "categories": ["layer-1", "stablecoin", "smart-contract-platform", "rwa"],
        "weights": [0.3, 0.3, 0.2, 0.2]
    },
    "risk_taker": {
        "categories": ["meme-token", "gaming", "nft", "defi"],
        "weights": [0.4, 0.3, 0.2, 0.1]
    },
    "tech_enthusiast": {
        "categories": ["ai", "layer-2", "privacy", "layer-1"],
        "weights": [0.4, 0.3, 0.2, 0.1]
    },
    "yield_farmer": {
        "categories": ["liquid-staking", "defi", "yield", "stablecoin"],
        "weights": [0.4, 0.3, 0.2, 0.1]
    },
    "metaverse_builder": {
        "categories": ["metaverse", "gaming", "nft", "layer-1"],
        "weights": [0.5, 0.3, 0.1, 0.1]
    }
}

# Interaction Diversity - EXTREME TUNING
INTERACTION_DIVERSITY = {
    "enable_exploration": True,
    "exploration_rate": 0.45,       # Much higher exploration
    "novelty_bias": 0.6,           # Strong novelty preference
    "temporal_variance": True,
    "negative_feedback": True
}

# API settings
API_HOST = "0.0.0.0"
API_PORT = 8000
API_CACHE_TTL = 300  # 5 menit dalam detik

# Cold Start Evaluation - EXTREME TUNING
COLD_START_EVAL_CONFIG = {
    "cold_start_users": 150,         # More test users
    "max_popular_items_exclude": 0.1, # Exclude more popular items
    "test_ratio": 0.4,               # Larger test set
    "min_interactions_required": 3,   # Lower requirement
    "category_diversity_enabled": True,
}

# Domain-specific weights - EXTREME TUNING
CRYPTO_DOMAIN_WEIGHTS = {
    "trend_importance": 0.85,       # Much stronger trend following
    "popularity_decay": 0.1,        # Faster popularity decay
    "category_correlation": 0.8,    # Stronger category influence
    "market_cap_influence": 0.6,    # More weight on market cap
    "chain_importance": 0.5,        # Stronger chain preference
}