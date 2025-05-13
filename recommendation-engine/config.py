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
TOP_COINS_LIMIT = 1000
TOP_COINS_DETAIL = 1000
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
# Neural Collaborative Filtering - FINE-TUNED SETTINGS
NCF_PARAMS = {
    "embedding_dim": 64,            # Ditingkatkan dari 32
    "layers": [128, 64, 32],        # Arsitektur lebih dalam dan lebih lebar
    "learning_rate": 0.0001,        # Learning rate optimal
    "batch_size": 128,              # Batch size diturunkan dari 256
    "epochs": 30,                   # Lebih banyak epoch
    "val_ratio": 0.15,              # Porsi validasi tetap
    "dropout": 0.3,                 # Dropout dikurangi sedikit
    "weight_decay": 5e-4,           # Regularisasi sedikit dikurangi
    "patience": 7,                  # Patience ditingkatkan
    "negative_ratio": 3             # Lebih banyak negative samples
}

# Feature-Enhanced CF - OPTIMIZED SETTINGS
FECF_PARAMS = {
    "no_components": 64,            # Ditingkatkan dari 48
    "content_alpha": 0.55           # Lebih seimbang antara collaborative dan content features
}

# Hybrid Model - BALANCED ADAPTIVE SETTINGS
HYBRID_PARAMS = {
    "ncf_weight": 0.35,             # Bobot NCF yang lebih signifikan
    "fecf_weight": 0.65,            # FECF tetap dominan tapi tidak terlalu tinggi
    "interaction_threshold_low": 5,  # Threshold low sesuai interaksi realistis
    "interaction_threshold_high": 15, # Threshold high yang lebih realistis - diturunkan dari 20
    "diversity_factor": 0.3,        # Tingkatkan faktor diversitas
    "cold_start_fecf_weight": 0.75,  # FECF lebih dominan untuk cold start tapi tidak ekstrem
    "explore_ratio": 0.30,          # Tingkatkan eksplorasi
    "normalization": "sigmoid",     # Tetap gunakan sigmoid normalization
    "ensemble_method": "stacking",  # Metode ensemble baru yang lebih adaptif - stacking
    "n_candidates_factor": 3,        # Lebih banyak kandidat vs. hasil akhir - diturunkan dari 4
    "category_diversity_weight": 0.25, # Tingkatkan bobot diversitas kategori
    "trending_boost_factor": 0.2,    # Faktor boost untuk trending items - diturunkan dari 0.35
    "confidence_threshold": 0.7,     # Threshold kepercayaan untuk metode selective
}

# Category Configuration - ENHANCED SETTINGS
CATEGORY_CONFIG = {
    "max_per_category": 0.2,        # Sedikit tingkatkan batas kategori
    "prioritize_diverse": True,
    "boost_underrepresented": 0.6,  # Tingkatkan boost kategori langka
    "penalty_overrepresented": -0.7 # Penalti kuat untuk kategori over-represented
}

# Keputusan investasi
TRADING_SIGNAL_WINDOW = 14  # Ukuran jendela untuk perhitungan indikator
CONFIDENCE_THRESHOLD = 0.7  # Threshold untuk keyakinan sinyal

# Evaluasi model
EVAL_METRICS = ["precision", "recall", "ndcg", "map", "mrr", "hit_ratio"]
EVAL_K_VALUES = [5, 10, 20]
EVAL_TEST_RATIO = 0.2
EVAL_RANDOM_SEED = 42

# Persona pengguna
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

# Interaction Diversity - OPTIMIZED SETTINGS
INTERACTION_DIVERSITY = {
    "enable_exploration": True,
    "exploration_rate": 0.35,       # Terkalibrasi dari 0.45
    "novelty_bias": 0.5,            # Juga terkalibrasi dari 0.6
    "temporal_variance": True,
    "negative_feedback": True
}

# API settings
API_HOST = "0.0.0.0"
API_PORT = 8001
API_CACHE_TTL = 300  # 5 menit dalam detik

# Cold Start Evaluation - FIXED SETTINGS
COLD_START_EVAL_CONFIG = {
    "cold_start_users": 100,         # Jumlah test users tetap
    "max_popular_items_exclude": 0.1, # Hanya exclude 10% item populer - dinaikkan dari 0.05
    "test_ratio": 0.25,               # Test set yang lebih reasonable - diturunkan dari 0.3
    "min_interactions_required": 3,   # Sesuaikan dengan minimal interaksi
    "category_diversity_enabled": True,
}

# Domain-specific weights - OPTIMIZED FOR CRYPTO
CRYPTO_DOMAIN_WEIGHTS = {
    "trend_importance": 0.65,       # Terkalibrasi dari 0.75
    "popularity_decay": 0.2,         # Lebih cepat popularity decay - dinaikkan dari 0.1
    "category_correlation": 0.65,    # Sedikit lebih rendah
    "market_cap_influence": 0.45,    # Diturunkan sedikit bobot market cap dari 0.55
    "chain_importance": 0.45,        # Sedikit lebih rendah
}