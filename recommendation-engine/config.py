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
# - Neural Collaborative Filtering - DIOPTIMALKAN
NCF_PARAMS = {
    "embedding_dim": 64,           # Diturunkan dari 128 menjadi 64
    "layers": [128, 64, 32],   # Layer lebih dalam dengan unit lebih banyak
    "learning_rate": 0.002,         # Slightly higher learning rate
    "batch_size": 256,              # Reduced to prevent overfitting
    "epochs": 50,                  # Significantly increased
    "val_ratio": 0.15,              # Tetap
    "dropout": 0.4,                 # Increased dropout untuk regularisasi lebih kuat
    "weight_decay": 5e-4,           # Increased weight decay
    "patience": 10,                 # More patience for convergence
    "negative_ratio": 4            # Increased negative samples
}

# - Feature-Enhanced CF
FECF_PARAMS = {
    "no_components": 64,            # Diturunkan dari 96 menjadi 64
    "content_alpha": 0.45           # Slightly more weight to collaborative data
}

# - Hybrid Model - DIOPTIMALKAN
HYBRID_PARAMS = {
    "ncf_weight": 0.3,              # Reduced from 0.4 to give more weight to FECF
    "fecf_weight": 0.7,             # Increased from 0.6
    "interaction_threshold_low": 3, # Lowered from 5
    "interaction_threshold_high": 15, # Lowered from 20
    "diversity_factor": 0.25,       # Increased from 0.15 for better diversity
    "cold_start_fecf_weight": 0.95, # Keep at 0.95 as it performs well
    "explore_ratio": 0.3            # Increased from 0.2 to promote exploration
}

# Konfigurasi kategori untuk meningkatkan keragaman
CATEGORY_CONFIG = {
    "max_per_category": 0.2,        # Reduced from 0.25
    "prioritize_diverse": True,
    "boost_underrepresented": 0.35, # Increased from 0.25
    "penalty_overrepresented": -0.5 # Increased from -0.4
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

# Konfigurasi diversifikasi untuk interaksi sintetis - DIOPTIMALKAN
INTERACTION_DIVERSITY = {
    "enable_exploration": True,   # Aktifkan eksplorasi di luar kategori utama
    "exploration_rate": 0.3,      # Increased from 0.2 to 0.3
    "novelty_bias": 0.4,          # Increased from 0.3 to 0.4
    "temporal_variance": True,    # Variasi preferensi seiring waktu
    "negative_feedback": True     # Simulasi feedback negatif secara acak
}

# API settings
API_HOST = "0.0.0.0"
API_PORT = 8000
API_CACHE_TTL = 300  # 5 menit dalam detik

# Cold-start evaluation parameters - DITAMBAHKAN
COLD_START_EVAL_CONFIG = {
    "cold_start_users": 20,         # Number of users for cold-start evaluation
    "max_popular_items_exclude": 0.05, # Exclude top 5% most popular items
    "test_ratio": 0.3,               # Ratio of interactions to hide for cold-start simulation
    "min_interactions_required": 5,  # Minimum interactions needed for cold-start testing
    "category_diversity_enabled": False, # Enable category diversity for evaluation
}

# Domain-specific weights for cryptocurrency - DITAMBAHKAN
CRYPTO_DOMAIN_WEIGHTS = {
    "trend_importance": 0.7,      # Importance of trend signals (crypto is highly trend-driven)
    "popularity_decay": 0.05,     # How fast popularity decays over time
    "category_correlation": 0.6,  # How much categories correlate with user preferences
    "market_cap_influence": 0.4,  # How much market cap influences recommendations
    "chain_importance": 0.3,      # Importance of blockchain when making recommendations
}