"""
Konfigurasi utama untuk sistem rekomendasi Web3
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
    "metaverse"
]

# Parameter model rekomendasi
# - Neural Collaborative Filtering
NCF_PARAMS = {
    "embedding_dim": 64,
    "layers": [128, 64, 32, 16],
    "learning_rate": 0.001,
    "batch_size": 256,
    "epochs": 20,
    "val_ratio": 0.2,
    "dropout": 0.2,
    "weight_decay": 1e-4
}

# - Feature-Enhanced CF (LightFM)
FECF_PARAMS = {
    "no_components": 64,
    "learning_rate": 0.05,
    "loss": "warp",
    "max_sampled": 10,
    "epochs": 20
}

# - Hybrid Model
HYBRID_PARAMS = {
    "ncf_weight": 0.5,            # Default weight - akan disesuaikan dinamis di hybrid.py
    "fecf_weight": 0.5,           # Default weight - akan disesuaikan dinamis di hybrid.py
    "interaction_threshold_low": 5,   # Di bawah ini mengandalkan FECF
    "interaction_threshold_high": 20, # Di atas ini mengandalkan NCF
    "diversity_factor": 0.2,      # Faktor untuk meningkatkan keragaman rekomendasi
    "cold_start_fecf_weight": 0.9  # Bobot FECF untuk pengguna cold-start
}

# Keputusan investasi
TRADING_SIGNAL_WINDOW = 14  # Ukuran jendela untuk perhitungan indikator
CONFIDENCE_THRESHOLD = 0.7  # Threshold untuk keyakinan sinyal

# Evaluasi model
EVAL_METRICS = ["precision", "recall", "ndcg", "map", "mrr"]
EVAL_K_VALUES = [5, 10, 20]
EVAL_TEST_RATIO = 0.2
EVAL_RANDOM_SEED = 42

# Persona pengguna untuk sintetis data
USER_PERSONAS = {
    "defi_enthusiast": {
        "categories": ["defi", "layer-1", "stablecoin"],
        "weights": [0.6, 0.3, 0.1]
    },
    "nft_collector": {
        "categories": ["nft", "gaming", "metaverse"],
        "weights": [0.7, 0.2, 0.1]
    },
    "trader": {
        "categories": ["layer-1", "defi", "meme-token"],
        "weights": [0.5, 0.3, 0.2]
    },
    "conservative_investor": {
        "categories": ["layer-1", "stablecoin", "smart-contract-platform"],
        "weights": [0.4, 0.4, 0.2]
    },
    "risk_taker": {
        "categories": ["meme-token", "gaming", "nft"],
        "weights": [0.5, 0.3, 0.2]
    }
}

# API settings
API_HOST = "0.0.0.0"
API_PORT = 8000
API_CACHE_TTL = 3600  # 1 jam dalam detik