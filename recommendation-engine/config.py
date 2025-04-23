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
    "metaverse",
    "layer-2",
    "liquid-staking",
    "artificial-intelligence",
    "fan-token",
    "move-to-earn",
    "real-world-assets-rwa"
]

# Parameter model rekomendasi
# - Neural Collaborative Filtering - DRASTICALLY SIMPLIFIED
NCF_PARAMS = {
    "embedding_dim": 64,           # Ditingkatkan dari 16 ke 64
    "layers": [128, 64, 32, 16],   # Perluas jaringan
    "learning_rate": 0.001,        # Ditingkatkan dari 0.0002 ke 0.001
    "batch_size": 256,             # Pertahankan
    "epochs": 50,                  # Tingkatkan
    "val_ratio": 0.15,             # Pertahankan
    "dropout": 0.3,                # Kurangi
    "weight_decay": 1e-4,          # Kurangi
    "patience": 10,                # Pertahankan
    "negative_ratio": 4            # Ditingkatkan dari 2 ke 4
}

# - Feature-Enhanced CF
FECF_PARAMS = {
    "no_components": 64,
    "content_alpha": 0.5     # Balance between CF and content (0.5 = 50-50 split)
}

# - Hybrid Model
HYBRID_PARAMS = {
    "ncf_weight": 0.4,            # Default weight untuk NCF
    "fecf_weight": 0.6,           # Default weight untuk FECF
    "interaction_threshold_low": 5,   # Di bawah ini mengandalkan FECF
    "interaction_threshold_high": 20, # Di atas ini gunakan bobot seimbang
    "diversity_factor": 0.15,      # Faktor untuk diversifikasi rekomendasi
    "cold_start_fecf_weight": 0.95,  # Bobot FECF untuk pengguna cold-start
    "explore_ratio": 0.2           # Proporsi rekomendasi untuk eksplorasi
}

# Konfigurasi kategori untuk meningkatkan keragaman
CATEGORY_CONFIG = {
    "max_per_category": 0.25,        # Maksimum 30% rekomendasi dari satu kategori
    "prioritize_diverse": True,      # Prioritaskan keragaman kategori
    "boost_underrepresented": 0.25,   # Boost 0.2 untuk kategori yang kurang terwakili
    "penalty_overrepresented": -0.4  # Penalti 0.3 untuk kategori yang terlalu dominan
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

# Konfigurasi diversifikasi untuk interaksi sintetis
INTERACTION_DIVERSITY = {
    "enable_exploration": True,   # Aktifkan eksplorasi di luar kategori utama
    "exploration_rate": 0.2,      # 20% eksplorasi kategori
    "novelty_bias": 0.3,          # Preferensi untuk proyek baru/berbeda
    "temporal_variance": True,    # Variasi preferensi seiring waktu
    "negative_feedback": True     # Simulasi feedback negatif secara acak
}

# API settings
API_HOST = "0.0.0.0"
API_PORT = 8000
API_CACHE_TTL = 300  # 5 menit dalam detik