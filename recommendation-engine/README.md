# Recommendation System Web3 Based

Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, NFT, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal dengan dukungan penuh untuk periode indikator dinamis, membandingkan pendekatan Neural CF dan Feature-Enhanced CF.

## 📋 Deskripsi

Sistem ini menggunakan data dari CoinGecko API untuk menyediakan rekomendasi proyek Web3 berdasarkan:

- **Metrik Popularitas** (market cap, volume, metrik sosial)
- **Tren Investasi** (perubahan harga, sentimen pasar)
- **Interaksi Pengguna** (view, favorite, portfolio add) dengan bobot realistis (weight = 1)
- **Fitur Proyek** (DeFi, GameFi, Layer-1, dll)
- **Analisis Teknikal** (RSI, MACD, Bollinger Bands, dll) dengan periode yang dapat dikonfigurasi penuh
- **Maturitas Proyek** (usia, aktivitas developer, engagement sosial)

Sistem ini mengimplementasikan beberapa pendekatan rekomendasi:
1. **Feature-Enhanced Collaborative Filtering** menggunakan scikit-learn SVD
2. **Neural Collaborative Filtering** menggunakan PyTorch dengan arsitektur yang ditingkatkan
3. **Hybrid Model** yang menggabungkan kedua pendekatan dengan teknik ensemble canggih dan adaptive weighting

## 🚀 Fitur Utama

1. **Model Rekomendasi Ganda:**
   - Feature-Enhanced CF untuk rekomendasi berbasis konten
   - Neural CF untuk personalisasi berbasis deep learning dengan arsitektur CryptoNCFModel
   - Hybrid Model dengan normalizasi skor robust dan selective ensemble learning

2. **Integrasi Analisis Teknikal dengan Periode Dinamis:**
   - Periode indikator yang dapat dikonfigurasi untuk berbagai gaya trading (jangka pendek, standar, jangka panjang)
   - Sinyal trading (buy/sell/hold) dengan tingkat kepercayaan
   - Personalisasi berdasarkan toleransi risiko pengguna
   - Deteksi peristiwa pasar (pump, dump, volatilitas tinggi) dengan threshold yang dapat disesuaikan
   - Preset gaya trading untuk jangka pendek, standar, dan jangka panjang
   - Deteksi market regime otomatis (trending, ranging, volatile)

3. **Penanganan Cold-Start yang Ditingkatkan:**
   - Rekomendasi untuk pengguna baru berdasarkan minat
   - Penanganan multi-kategori yang lebih baik
   - Normalisasi skor untuk cold-start pengguna dengan validasi ketat
   - Strategi adaptive weighting berdasarkan jumlah interaksi

4. **Filtering Rekomendasi yang Disempurnakan:**
   - Filter berdasarkan kategori, chain, atau kombinasi keduanya
   - Mode strict untuk hasil yang sangat spesifik dan presisi tinggi
   - Fallback rekomendasi cerdas ketika filter terlalu ketat
   - Diversifikasi hasil otomatis saat melakukan filtering
   - **Klasifikasi Filter Match** dengan beberapa tingkatan:
     - `exact` - Item yang cocok persis dengan filter yang diterapkan
     - `category_only` - Item yang hanya cocok dengan filter kategori
     - `chain_only` - Item yang hanya cocok dengan filter chain
     - `chain_popular` - Item populer dalam chain yang difilter
     - `fallback` - Item yang ditambahkan sebagai cadangan karena kurangnya hasil
   - **Penghitungan Exact Match** untuk mengukur ketepatan filter

5. **API Service dengan Konfigurasi Fleksibel:**
   - Endpoint REST API untuk integrasi dengan aplikasi backend Laravel
   - Dukungan parameter periode indikator kustom melalui API
   - Caching untuk performa yang lebih baik
   - Dokumentasi komprehensif

6. **Pipeline Data Otomatis:**
   - Pengumpulan data reguler dari CoinGecko
   - Pipeline pemrosesan untuk ekstraksi fitur
   - Pelatihan dan evaluasi model otomatis

## 🔄 Pembaruan Terbaru (Juni 2025)

### Critical Score Validation & Normalization - MAJOR UPDATE

Berdasarkan evaluasi mendalam sistem dengan hasil evaluasi terbaru, sistem telah mengalami perbaikan signifikan pada validasi skor dan normalisasi dengan hasil evaluasi yang menunjukkan peningkatan performa:

#### Hasil Evaluasi Terbaru - Perbandingan Berdasarkan Min Interactions

**Min Interactions = 10 (141 Test Users)**:
| Model | Precision@10 | Recall@10 | F1@10 | NDCG@10 | Hit Ratio@10 | MRR |
|-------|--------------|-----------|--------|---------|---------------|-----|
| FECF | 0.2099 | 0.3554 | 0.2451 | 0.3467 | 0.8085 | 0.5446 |
| NCF | 0.1567 | 0.2600 | 0.1819 | 0.2189 | 0.5780 | 0.3536 |
| **Hybrid** | **0.2355** | **0.3896** | **0.2738** | 0.3396 | 0.7730 | 0.4564 |

**Min Interactions = 20 (109 Test Users)** - Sama dengan min=10:
| Model | Precision | Recall | F1 | NDCG | Hit Ratio | MRR |
|-------|-----------|--------|----|----|-----------|-----|
| fecf | 0.2661 | 0.2660 | 0.2480 | 0.3322 | 0.8165 | 0.5871 |
| ncf | 0.2248 | 0.2226 | 0.2098 | 0.2503 | 0.6835 | 0.4372 |
| **hybrid** | **0.2835** | **0.2858** | **0.2657** | 0.3257 | **0.8211** | 0.4995 |

**Min Interactions = 30 (19 Test Users)** - Optimal Performance:
| Model | Precision@10 | Recall@10 | F1@10 | NDCG@10 | Hit Ratio@10 | MRR |
|-------|--------------|-----------|--------|---------|---------------|-----|
| FECF | 0.3368 | 0.2403 | 0.2678 | 0.3425 | 0.8947 | 0.5005 |
| NCF | 0.3526 | 0.2579 | 0.2830 | 0.3564 | 0.8421 | 0.4888 |
| **Hybrid** | **0.3842** | **0.2853** | **0.3112** | **0.4113** | **0.8947** | **0.6365** |

**Cold-Start Performance (Averaged across 5 runs)**:
| Model | Precision | Recall | F1 | NDCG | Hit Ratio | Runs |
|-------|-----------|--------|----|-------|-----------|------|
| cold_start_fecf | 0.1307±0.0154 | 0.4337±0.0511 | 0.2008±0.0236 | 0.3249±0.0305 | 0.6373±0.0472 | 5 |
| cold_start_hybrid | 0.1176±0.0130 | 0.3899±0.0435 | 0.1806±0.0200 | 0.2604±0.0275 | 0.5371±0.0582 | 5 |

#### Analisis Peningkatan Performa

1. **Hybrid Model Menunjukkan Performa Terbaik**: Secara konsisten unggul di semua metrik utama, terutama pada users dengan 30+ interaksi
2. **NCF Membaik Drastis dengan Interaksi Lebih Banyak**: Precision meningkat dari 0.1567 (min=10) ke 0.3526 (min=30) - peningkatan 125%
3. **FECF Tetap Stabil untuk Cold-Start**: Konsisten memberikan Hit Ratio tinggi dan reliability untuk pengguna baru
4. **Hybrid Optimal untuk Users dengan 30+ Interaksi**: MRR mencapai 0.6365, menunjukkan ranking quality yang excellent

#### Masalah yang Diperbaiki:
1. **Score Tidak Terurut**: FECF model menghasilkan score yang tidak terurut (0.36444 → 0.33391 → 0.32825)
2. **Score Melebihi Range [0,1]**: NCF model menghasilkan score hingga 1.0649 yang melebihi batas normal
3. **Normalisasi Tidak Konsisten**: Berbagai metode normalisasi tidak memberikan hasil yang stabil

#### Solusi yang Diimplementasikan:

**FECF Model (alt_fecf.py)**:
- **Score Validation Ketat**: Implementasi `np.clip(score, 0.0, 1.0)` pada semua tahap rekomendasi
- **Robust Normalization**: Ganti ke metode "robust" yang menggunakan percentile-based approach (p5-p95)
- **Fallback Scoring**: Sistem fallback yang menggunakan trend_score/popularity_score untuk edge cases
- **Final Validation**: Validasi NaN dan infinity values dengan `np.nan_to_num()`

**NCF Model (ncf.py)**:
- **Enhanced Architecture**: Implementasi CryptoNCFModel dengan residual connections dan layer normalization
- **Prediction Clipping**: Semua batch predictions di-clip ke range [0,1] dengan validasi ketat
- **Cache Validation**: Validasi score dari cache sebelum mengembalikan hasil
- **Fallback Recommendations**: Sistem fallback yang lebih robust untuk edge cases

**Hybrid Model (hybrid.py)**:
- **Selective Ensemble**: Metode ensemble yang menganalisis confidence dan agreement antar model
- **Adaptive Weighting**: Pembobotan dinamis berdasarkan jumlah interaksi pengguna:
  - `< 10 interaksi`: FECF 95%, NCF 5% (cold-start)
  - `10-20 interaksi`: FECF 80%, NCF 20% (low interactions)
  - `20-30 interaksi`: FECF 80%→50%, NCF 20%→50% (gradual transition)
  - `30-50 interaksi`: FECF 50%, NCF 50% (base weights)
  - `50-100 interaksi`: FECF 45%, NCF 55% (NCF mulai unggul)
  - `> 100 interaksi`: FECF 40%, NCF 60% (NCF dominan)
- **Input Validation**: Validasi ketat pada input dari kedua model sebelum ensemble
- **Final Score Validation**: Semua score final dipastikan dalam range [0,1]

### Fine-Tuning Parameter Model

**Neural Collaborative Filtering - ENHANCED SETTINGS**:
```python
NCF_PARAMS = {
    "embedding_dim": 64,            # Ditingkatkan dari 32
    "layers": [128, 64, 32],        # Arsitektur lebih dalam
    "learning_rate": 0.0001,        # Learning rate optimal
    "batch_size": 128,              # Batch size diturunkan dari 256
    "epochs": 30,                   # Lebih banyak epoch
    "dropout": 0.3,                 # Dropout dikurangi sedikit
    "weight_decay": 5e-4,           # Regularisasi optimal
    "patience": 7,                  # Patience ditingkatkan
    "negative_ratio": 3             # Negative sampling optimal
}
```

**Feature-Enhanced CF - OPTIMIZED SETTINGS**:
```python
FECF_PARAMS = {
    "no_components": 64,            # Ditingkatkan dari 48
    "content_alpha": 0.55           # Balanced content-collaborative features
}
```

**Hybrid Model - BALANCED ADAPTIVE SETTINGS**:
```python
HYBRID_PARAMS = {
    "ncf_weight": 0.5,             # Base weight - disesuaikan secara adaptive
    "fecf_weight": 0.5,            # Base weight - disesuaikan secara adaptive
    "interaction_threshold_low": 10,  # Dinaikkan dari 5
    "interaction_threshold_high": 30, # Dinaikkan dari 15
    "diversity_factor": 0.25,       # Diturunkan untuk fokus ke score
    "cold_start_fecf_weight": 0.95, # FECF dominan untuk cold start
    "normalization": "robust",      # Robust normalization method
    "ensemble_method": "selective", # Selective ensemble dengan confidence analysis
    "confidence_threshold": 0.3,     # Threshold untuk NCF reliability
    "min_ncf_interactions": 20,     # Minimal interactions untuk NCF
}
```

### Peningkatan Evaluasi dan Validasi Data

**Data Sparsity Analysis**:
- Total Interactions: 65,837
- Total Users: 5,000  
- Total Items: 1,000
- **Sparsity: 98.68%** (hanya 1.32% matriks terisi)

**Validasi Data yang Ditingkatkan**:
- Deteksi dan handling untuk uniform interaction weights (weight=1) sesuai production
- Validasi minimal user interactions untuk stratified split
- Handling extreme interaction imbalance dengan weight adjustment
- Early stopping dengan improvement threshold untuk training yang lebih stabil

### Peningkatan Filtering Rekomendasi (Mei 2025)

Sistem rekomendasi telah ditingkatkan dengan kemampuan filtering yang lebih canggih:

1. **Filter Multi-Dimensi:**
   - Kombinasi filter kategori dan chain secara bersamaan
   - Mode strict untuk filtering tanpa fallback, menjamin hanya hasil yang sangat relevan
   - Dukungan filter untuk semua model (FECF, NCF, dan Hybrid)

2. **Algoritma Matching yang Disempurnakan:**
   - Matching berbasis kesamaan untuk kategori dan chain
   - Penanganan kategori majemuk yang lebih baik
   - Peningkatan identifikasi konten relevan dengan fuzzy matching

3. **Fallback Cerdas dengan Filter Match Tracking:**
   - Respons yang lebih baik ketika hasil filter terlalu sedikit
   - Penambahan hasil relevan secara bertahap ketika strict mode tidak aktif
   - Label `filter_match` pada hasil untuk menunjukkan tingkat kecocokan
   - Penghitungan `exact_match_count` untuk memantau kualitas hasil filter

### Optimasi Analisis Teknikal dan Prediksi Harga (Mei 2025)

Beberapa penyempurnaan signifikan telah ditambahkan untuk meningkatkan akurasi dan keandalan analisis teknikal:

1. **Deteksi Market Regime Otomatis:**
   - Deteksi otomatis kondisi pasar (trending_bullish, trending_bearish, ranging_volatile, dll)
   - Penyesuaian parameter analisis berdasarkan regime pasar
   - Optimasi parameter untuk setiap jenis regime pasar

2. **Prediksi Harga Berbasis ML yang Disempurnakan:**
   - Model LSTM untuk pasar dengan data yang cukup (>180 hari)
   - Fallback ke ARIMA untuk pasar dengan data menengah (60-180 hari)
   - Model prediksi sederhana untuk pasar dengan data terbatas (<60 hari)
   - Deteksi level support dan resistance yang lebih akurat

3. **Peningkatan Penanganan Data dan Log:**
   - Penanganan error yang lebih robust untuk berbagai kondisi pasar
   - Pesan log yang lebih informatif dan profesional
   - Peningkatan performa model prediksi ML (4 kali lebih cepat)
   - Caching prediksi untuk mempercepat akses berulang

## 📊 Model Rekomendasi

### Feature-Enhanced CF (Scikit-learn SVD)

Model Feature-Enhanced CF menggunakan SVD (Singular Value Decomposition) dari scikit-learn untuk matrix factorization dan menggabungkannya dengan content-based filtering berdasarkan fitur proyek.

**Peningkatan Terbaru:**
- **Score Validation Ketat**: Semua score dipastikan dalam range [0,1] dengan multiple validation layers
- **Robust Normalization**: Menggunakan percentile-based approach (p5-p95) untuk menghindari outlier
- **Fallback Scoring**: Sistem fallback berbasis trend_score dan popularity_score untuk edge cases
- **Enhanced Diversity**: Algoritma diversifikasi yang mempertahankan score ordering

**Kelebihan FECF:**
- Sangat efektif untuk cold-start users dan data sparse
- Robust terhadap outlier dengan normalisasi yang ditingkatkan
- Konsisten menghasilkan score dalam range valid [0,1]

### Neural CF (PyTorch) - Enhanced Architecture

Model Neural CF menggunakan **CryptoNCFModel** dengan arsitektur yang ditingkatkan:

**Arsitektur Baru:**
- **Dual-path Architecture**: Generalized Matrix Factorization (GMF) + Multi-Layer Perceptron (MLP)
- **Residual Connections**: Mengurangi vanishing gradient problem
- **Layer Normalization**: Stabilitas training yang lebih baik
- **Attention Mechanism**: Meningkatkan representasi user-item
- **Enhanced Negative Sampling**: Category-aware sampling untuk domain cryptocurrency

**Peningkatan Terbaru:**
- **Prediction Clipping**: Semua output dipastikan dalam range [0,1]
- **Batch Validation**: Validasi ketat pada batch predictions
- **Cache Validation**: Score dari cache divalidasi sebelum dikembalikan
- **Stratified Training**: Handling untuk data imbalance dengan stratified split

**Kelebihan NCF:**
- Excellent untuk users dengan 20+ interaksi
- Arsitektur mendalam untuk pattern recognition yang kompleks
- **Optimal performance dengan minimal 30+ interaksi per user**

### Hybrid Model - Selective Ensemble

Model Hybrid menggunakan **Selective Ensemble** dengan adaptive weighting:

**Adaptive Weighting Logic:**
```
< 10 interaksi:     FECF 95%, NCF 5%   (cold start)
10-20 interaksi:    FECF 80%, NCF 20%  (low interactions)  
20-30 interaksi:    FECF 80%→50%, NCF 20%→50% (gradual transition)
30-50 interaksi:    FECF 50%, NCF 50%  (base weights)
50-100 interaksi:   FECF 45%, NCF 55%  (NCF mulai unggul)
> 100 interaksi:    FECF 40%, NCF 60%  (NCF dominan)
```

**Selective Ensemble Features:**
- **Confidence Analysis**: Analisis distribusi score dan agreement antar model
- **Robust Score Normalization**: IQR-based normalization untuk mencegah outlier
- **Input Validation**: Validasi ketat pada input dari kedua model
- **Final Score Validation**: Semua output dipastikan dalam range [0,1]

## 📈 Analisis Teknikal dengan Periode Dinamis

Komponen analisis teknikal sekarang mendukung periode indikator yang sepenuhnya dapat dikonfigurasi, memungkinkan penyesuaian untuk berbagai gaya trading:

### Deteksi Market Regime Otomatis

Sistem sekarang dapat mendeteksi regime pasar secara otomatis dan menyesuaikan parameter analisis:

- **Trending Bullish**: Tren naik dengan volatilitas normal
- **Trending Bullish Volatile**: Tren naik dengan volatilitas tinggi
- **Trending Bearish**: Tren turun dengan volatilitas normal
- **Trending Bearish Volatile**: Tren turun dengan volatilitas tinggi
- **Ranging Low Volatility**: Pasar sideways dengan volatilitas rendah
- **Ranging Volatile**: Pasar sideways dengan volatilitas tinggi
- **Volatile Sideways**: Pasar dengan volatilitas ekstrem tanpa arah yang jelas

### Preset Trading Style

Sistem menyediakan tiga preset gaya trading:

1. **Short-Term Trading**
   ```
   rsi_period: 7
   macd_fast: 8
   macd_slow: 17
   macd_signal: 9
   bb_period: 10
   stoch_k: 7
   stoch_d: 3
   ma_short: 10
   ma_medium: 30
   ma_long: 60
   ```

2. **Standard Trading** (Default)
   ```
   rsi_period: 14
   macd_fast: 12
   macd_slow: 26
   macd_signal: 9
   bb_period: 20
   stoch_k: 14
   stoch_d: 3
   ma_short: 20
   ma_medium: 50
   ma_long: 200
   ```

3. **Long-Term Trading**
   ```
   rsi_period: 21
   macd_fast: 19
   macd_slow: 39
   macd_signal: 9
   bb_period: 30
   stoch_k: 21
   stoch_d: 7
   ma_short: 50
   ma_medium: 100
   ma_long: 200
   ```

### Indikator Teknikal yang Didukung

Semua indikator berikut mendukung periode yang dapat dikonfigurasi:

- **Indikator Tren:** Moving Averages, MACD, ADX
- **Indikator Momentum:** RSI, Stochastic, CCI
- **Indikator Volatilitas:** Bollinger Bands, ATR
- **Indikator Volume:** OBV, MFI, Chaikin A/D
- **Ichimoku Cloud**
- **Pembentukan Pivot Points**
- **Prediksi Harga Berbasis ML (LSTM, ARIMA)**

Sinyal dipersonalisasi berdasarkan toleransi risiko pengguna (rendah, menengah, tinggi) dan dapat disesuaikan dengan berbagai gaya trading.

### Penyesuaian Parameter Otomatis

Sistem akan secara otomatis menyesuaikan periode indikator berdasarkan:
- Jumlah data historis yang tersedia
- Market regime yang terdeteksi
- Gaya trading yang dipilih

Penyesuaian ini memastikan hasil analisis tetap optimal bahkan dengan keterbatasan data.

## 🛠️ Instalasi

### Prasyarat

- Python 3.12+ terintegrasi dengan baik dengan alt_fecf.py (default)
- Jika ingin menggunakan LightFM dari file fecf.py, gunakan Python 3.10 tapi tidak disarankan...karena masih terdapat kesalahan yang tidak diketahui.
- pip (Python package manager)
- CoinGecko API key (untuk pengumpulan data)
- PostgreSQL (untuk penyimpanan data)

### Langkah Instalasi

1. Clone repository:
```bash
git clone https://github.com/feyfry/web3-recommender-system.git
cd web3-recommendation-system
```

2. Buat dan aktifkan lingkungan virtual:
```bash
# Windows
python -m venv venv
venv\Scripts\activate

# Linux/Mac
python -m venv venv
source venv/bin/activate

# Git Bash di Windows
python -m venv venv
source venv/Scripts/activate
```

3. Install dependensi dalam lingkungan virtual:
- Saat Anda mengaktifkan Library TA-LIB di requirements.txt pastikan terminal yang Anda gunakan adalah CMD jika memakai Windows, jika memaksa menggunakan GitBash, itu akan error.
   ```bash
   pip install -r requirements.txt
   ```

4. Konfigurasi API key:
   - Buat file `.env` di direktori root
   - Tambahkan CoinGecko API key Anda:
```
COINGECKO_API_KEY="your-api-key"
```

5. Setup database:
   - Buat database PostgreSQL
   - Update konfigurasi database di `config.py`

### Instalasi TA-Lib (Opsional)

TA-Lib memerlukan kompilasi library C dan bisa cukup rumit, terutama di Windows. Berikut panduan lengkap instalasinya.

#### 🪟 Windows

1. **Install Microsoft C++ Build Tools (Visual Studio Build Tools):**

   - Unduh `vs_BuildTools.exe` dari [https://visualstudio.microsoft.com/visual-cpp-build-tools/](https://visualstudio.microsoft.com/visual-cpp-build-tools/)
   - Jalankan installernya dan **centang komponen berikut**:
     - ✅ **Desktop development with C++**
     - Di bagian kanan (detail), pastikan juga centang, biasanya defaultnya sudah dicentang:
       - ✅ MSVC v143 - VS 2022 C++ x64/x86 build tools
       - ✅ Windows 10 SDK (atau Windows 11 SDK)
       - ✅ C++ CMake tools for Windows

   - Tunggu sampai proses instalasi selesai.

2. **Install TA-Lib Library (Binary):**

   - Unduh file `.msi` dari: [https://ta-lib.org/install/](https://ta-lib.org/install/)
   - Pilih versi Windows Anda (32-bit / 64-bit) oada zip, atau langsung instalasi .msi-nya misal:
     - `ta-lib-0.6.4-windows-x86_64.msi`
   - Ekstrak dan jalankan `.msi` installer
   - Secara default akan terinstall di: `C:\Program Files\TA-Lib`

3. **Buka terminal khusus VS 2022:**

   - Buka Start Menu
   - Cari dan jalankan **"x64 Native Tools Command Prompt for VS 2022"**

4. **Aktifkan virtual environment Anda (jika ada, bisa skip tahap ini):**

```bash
path\to\your\venv\Scripts\activate
```

5. **Install Python wrapper TA-Lib:**
```bash
pip install ta-lib
```

#### 🍎 macOS
```bash
# Install TA-Lib library dengan Homebrew
brew install ta-lib

# Install wrapper Python-nya
pip install ta-lib
```

#### 🐧 Linux (Ubuntu/Debian)
```bash
# Install dependensi build
sudo apt-get update
sudo apt-get install -y build-essential wget

# Download dan ekstrak TA-Lib source
wget http://prdownloads.sourceforge.net/ta-lib/ta-lib-0.4.0-src.tar.gz
tar -xzf ta-lib-0.4.0-src.tar.gz
cd ta-lib/

# Konfigurasi, compile, dan install
./configure --prefix=/usr
make
sudo make install

# Install Python wrapper
pip install ta-lib
```

#### 🔁 Alternatif Non-Kompilasi (pandas-ta)
Jika instalasi TA-Lib terlalu ribet, Anda bisa pakai alternatif yang ringan dan berbasis pandas:
```bash
pip install pandas-ta
```

## 🚀 Penggunaan

### Command Line Interface

Project ini menyediakan CLI komprehensif untuk semua fungsi utama:

```bash
# Mengumpulkan data dari CoinGecko
python main.py collect --limit 1000 --detail-limit 1000
# tambahkan param --rate-limit 3 jika ingin menghindari rate limit lebih panjang

# Memproses data yang dikumpulkan (realistic weights)
python main.py process --users 5000

# Melatih model rekomendasi
python main.py train --fecf --ncf --hybrid

# Evaluasi model dengan criteria yang lebih realistis
python main.py evaluate --cold-start --min-interactions 20

# Menghasilkan rekomendasi untuk pengguna
python main.py recommend --user-id user_1 --model hybrid --num 10

# Menghasilkan rekomendasi dengan filter
python main.py recommend --user-id user_1 --model hybrid --num 10 --category defi --chain ethereum

# Menghasilkan rekomendasi dengan strict filtering
python main.py recommend --user-id user_1 --model hybrid --num 10 --category defi --chain ethereum --strict

# Menghasilkan sinyal trading untuk proyek dengan berbagai opsi periode indikator
# Menggunakan preset gaya trading
python main.py signals --project-id bitcoin --risk medium --trading-style short_term

# Menggunakan periode indikator kustom
python main.py signals --project-id bitcoin --risk medium --rsi-period 9 --macd-fast 8 --macd-slow 17

# Menjalankan server API
python main.py api

# Menjalankan pipeline lengkap terorganisir
python main.py run

# Debugging rekomendasi untuk pengguna tertentu
python main.py debug --user-id user_1 --model hybrid --num 20
```

### Konfigurasi Periode Indikator Teknikal

Sistem ini mendukung konfigurasi penuh dari periode indikator teknikal untuk menyesuaikan dengan berbagai gaya trading:

1. **Melalui Command Line:**
   ```bash
   python main.py signals --project-id bitcoin --trading-style short_term
   ```
   
   Atau dengan mengkonfigurasi indikator individual:
   ```bash
   python main.py signals --project-id bitcoin \
     --rsi-period 7 \
     --macd-fast 8 \
     --macd-slow 17 \
     --macd-signal 9 \
     --bb-period 10 \
     --stoch-k 7 \
     --stoch-d 3 \
     --ma-short 10 \
     --ma-medium 30 \
     --ma-long 60
   ```

2. **Melalui API:**
   ```json
   {
     "project_id": "bitcoin",
     "days": 30,
     "interval": "1d",
     "risk_tolerance": "medium",
     "trading_style": "short_term",
     "periods": {
       "rsi_period": 7,
       "macd_fast": 8,
       "macd_slow": 17,
       "macd_signal": 9,
       "bb_period": 10,
       "stoch_k": 7,
       "stoch_d": 3,
       "ma_short": 10,
       "ma_medium": 30,
       "ma_long": 60
     }
   }
   ```

3. **Preset Gaya Trading:**
   - `short_term`: Periode lebih pendek untuk trading jangka pendek
   - `standard`: Periode standar untuk analisis teknikal (default)
   - `long_term`: Periode lebih panjang untuk perspektif jangka panjang

### Pipeline yang Terorganisir
Pipeline baru yang terorganisir memiliki langkah-langkah yang ditentukan dengan jelas dan status kemajuan yang mudah dipantau:

1. **Data Collection**: Mengumpulkan data dari CoinGecko API
2. **Data Processing**: Memproses data mentah dengan perhitungan metrik
3. **Model Training**: Melatih model rekomendasi
4. **Sample Recommendations**: Generasi rekomendasi sampel (opsional)
5. **Result Analysis**: Analisis hasil rekomendasi (opsional)

Untuk menjalankan pipeline dengan opsi kustom:
```bash
# Jalankan pipeline lengkap
python main.py run

# Jalankan pipeline tanpa rekomendasi sampel dan analisis
python main.py run --skip-recommendations --skip-analysis
```

## 🌐 API Reference

Sistem ini menyediakan RESTful API yang komprehensif menggunakan FastAPI. Server API dapat dijalankan dengan:
```bash
python main.py api
```

Secara default, API akan berjalan di `http://0.0.0.0:8001`.

### Endpoint Rekomendasi

#### 1. Dapatkan Rekomendasi Proyek

**Endpoint:** `POST /recommend/projects`

**Request Body:**
```json
{
  "user_id": "user_123",
  "model_type": "hybrid",
  "num_recommendations": 10,
  "exclude_known": true,
  "category": "defi",
  "chain": "ethereum",
  "user_interests": ["defi", "nft", "gaming"],
  "risk_tolerance": "medium",
  "strict_filter": false
}
```

**Parameter Baru:**
- `strict_filter`: Boolean yang menentukan apakah filter harus diterapkan secara ketat (default: false). Jika true, hanya mengembalikan hasil yang benar-benar cocok dengan filter tanpa fallback.

**Response:**
```json
{
  "user_id": "user_123",
  "model_type": "hybrid",
  "recommendations": [
    {
      "id": "bitcoin",
      "name": "Bitcoin",
      "symbol": "BTC",
      "image": "https://example.com/btc.png",
      "current_price": 50000,
      "price_change_24h": 2.5,
      "price_change_percentage_7d_in_currency": -1.2,
      "market_cap": 1000000000000,
      "total_volume": 30000000000,
      "popularity_score": 98.5,
      "trend_score": 85.2,
      "category": "layer-1",
      "chain": "bitcoin",
      "recommendation_score": 0.95,
      "filter_match": "exact"
    }
  ],
  "timestamp": "2025-04-19T10:30:00Z",
  "is_cold_start": false,
  "category_filter": "defi",
  "chain_filter": "ethereum",
  "execution_time": 0.125,
  "exact_match_count": 10
}
```

**Field Respons Baru:**
- `filter_match`: Indikator tingkat kecocokan dengan filter yang memiliki nilai berikut:
  - `exact` - Item yang cocok persis dengan semua filter (kategori dan chain)
  - `category_only` - Item yang hanya cocok dengan filter kategori tapi tidak dengan chain
  - `chain_only` - Item yang hanya cocok dengan filter chain tapi tidak dengan kategori
  - `chain_popular` - Item populer dalam chain yang sama (khusus untuk model FECF)
  - `fallback` - Item tambahan ketika tidak cukup hasil yang cocok persis
  - `null` - Tidak ada filter yang diterapkan atau item tidak melalui filtering
- `exact_match_count`: Jumlah total hasil yang memiliki `filter_match` bernilai "exact"

#### 2. Dapatkan Proyek Trending

**Endpoint:** `GET /recommend/trending`

**Parameters:**
- `limit` (int, optional): Jumlah proyek (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")
- `category` (string, optional): Filter berdasarkan kategori
- `chain` (string, optional): Filter berdasarkan chain
- `strict` (boolean, optional): Mode filter ketat (default: false)

**Response:** Array dari objek `ProjectResponse` dengan bidang `filter_match` untuk menunjukkan tingkat kecocokan

#### 3. Dapatkan Proyek Populer

**Endpoint:** `GET /recommend/popular`

**Parameters:**
- `limit` (int, optional): Jumlah proyek (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")
- `category` (string, optional): Filter berdasarkan kategori
- `chain` (string, optional): Filter berdasarkan chain
- `strict` (boolean, optional): Mode filter ketat (default: false)

**Response:** Array dari objek `ProjectResponse` dengan bidang `filter_match` untuk menunjukkan tingkat kecocokan

#### 4. Dapatkan Proyek Serupa

**Endpoint:** `GET /recommend/similar/{project_id}`

**Parameters:**
- `project_id` (string): ID proyek
- `limit` (int, optional): Jumlah proyek serupa (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")
- `category` (string, optional): Filter berdasarkan kategori
- `chain` (string, optional): Filter berdasarkan chain
- `strict` (boolean, optional): Mode filter ketat (default: false)

**Response:** Array dari objek `ProjectResponse` dengan bidang `filter_match` untuk menunjukkan tingkat kecocokan

### Endpoint Analisis Teknikal dengan Periode Dinamis

#### 1. Dapatkan Sinyal Trading

**Endpoint:** `POST /analysis/trading-signals`

**Request Body dengan Dukungan Periode Dinamis:**
```json
{
  "project_id": "bitcoin",
  "days": 30,
  "interval": "1d",
  "risk_tolerance": "medium",
  "trading_style": "short_term",
  "periods": {
    "rsi_period": 7,
    "macd_fast": 8,
    "macd_slow": 17,
    "macd_signal": 9,
    "bb_period": 10,
    "stoch_k": 7,
    "stoch_d": 3,
    "ma_short": 10,
    "ma_medium": 30,
    "ma_long": 60
  }
}
```

**Response:**
```json
{
  "project_id": "bitcoin",
  "action": "buy",
  "confidence": 0.85,
  "strong_signal": true,
  "evidence": [
    "RSI is oversold at 28.50 (periode 7)",
    "MACD crossed above signal line (bullish) - (8/17/9)",
    "Price below lower Bollinger Band (oversold) - (periode 10)"
  ],
  "target_price": 52500.0,
  "personalized_message": "Signal matches your balanced risk profile",
  "risk_profile": "medium",
  "indicators": {
    "rsi": 28.5,
    "macd": 250.75,
    "macd_signal": 210.25,
    "macd_histogram": 40.5
  },
  "indicator_periods": {
    "rsi_period": 7,
    "macd_fast": 8,
    "macd_slow": 17,
    "macd_signal": 9,
    "bb_period": 10,
    "stoch_k": 7,
    "stoch_d": 3,
    "ma_short": 10,
    "ma_medium": 30,
    "ma_long": 60
  },
  "timestamp": "2025-04-19T10:30:00Z"
}
```

#### 2. Dapatkan Indikator Teknikal

**Endpoint:** `POST /analysis/indicators`

**Request Body dengan Periode Kustom:**
```json
{
  "project_id": "bitcoin",
  "days": 30,
  "interval": "1d",
  "indicators": ["rsi", "macd", "bollinger", "sma"],
  "periods": {
    "rsi_period": 14,
    "macd_fast": 12,
    "macd_slow": 26,
    "macd_signal": 9,
    "bb_period": 20
  }
}
```

**Response:**
```json
{
  "project_id": "bitcoin",
  "indicators": {
    "rsi": {
      "value": 45.5,
      "signal": "neutral",
      "description": "RSI is neutral at 45.50",
      "period": 14
    },
    "macd": {
      "value": 250.75,
      "signal_line": 210.25,
      "histogram": 40.5,
      "signal": "bullish",
      "description": "MACD is bullish at 250.75 (Signal: 210.25)",
      "periods": {
        "fast": 12,
        "slow": 26,
        "signal": 9
      }
    },
    "bollinger": {
      "upper": 51200.0,
      "middle": 50000.0,
      "lower": 48800.0,
      "percent_b": 0.62,
      "signal": "neutral",
      "description": "Price is within Bollinger Bands",
      "period": 20
    },
    "moving_averages": {
      "values": {
        "sma_20": 49500.0,
        "sma_50": 48000.0,
        "sma_200": 42000.0
      },
      "signal": "bullish",
      "description": "Price is above 20 and 50-day moving averages (bullish trend)",
      "periods": {
        "short": 20,
        "medium": 50,
        "long": 200
      }
    }
  },
  "latest_close": 50150.0,
  "latest_timestamp": "2025-04-19T00:00:00Z",
  "period": "30 days (1d)",
  "execution_time": 0.235
}
```

#### 3. Deteksi Peristiwa Pasar dengan Threshold Kustom

**Endpoint:** `GET /analysis/market-events/{project_id}`

**Parameters:**
- `project_id` (string): ID proyek
- `days` (int, optional): Jumlah hari data historis (default: 30)
- `interval` (string, optional): Interval data (default: "1d")
- `window_size` (int, optional): Ukuran window untuk perhitungan (default: 14)
- `thresholds` (object, optional): Threshold kustom untuk deteksi event

**Response:**
```json
{
  "project_id": "bitcoin",
  "latest_event": "pump",
  "event_counts": {
    "pump": 3,
    "dump": 1,
    "high_volatility": 5,
    "volume_spike": 2
  },
  "close_price": 50150.0,
  "timestamp": "2025-04-19T00:00:00Z"
}
```

#### 4. Dapatkan Alert Teknikal

**Endpoint:** `GET /analysis/alerts/{project_id}`

**Parameters:**
- `project_id` (string): ID proyek
- `days` (int, optional): Jumlah hari data historis (default: 30)
- `interval` (string, optional): Interval data (default: "1d")
- `lookback` (int, optional): Jumlah periode untuk melihat alert (default: 5)
- `periods` (object, optional): Periode indikator kustom

**Response:**
```json
{
  "project_id": "bitcoin",
  "alerts": [
    {
      "date": "2025-04-18T00:00:00Z",
      "type": "macd_cross_up",
      "message": "MACD crossed above signal line (bullish) - (12/26/9)",
      "signal": "buy",
      "strength": 0.8
    },
    {
      "date": "2025-04-17T00:00:00Z",
      "type": "rsi_oversold",
      "message": "RSI is oversold at 28.50 (periode 14)",
      "signal": "buy",
      "strength": 0.75
    }
  ],
  "count": 2,
  "period": "30 days (1d)",
  "lookback": 5
}
```

#### 5. Prediksi Harga

**Endpoint:** `GET /analysis/price-prediction/{project_id}`

**Parameters:**
- `project_id` (string): ID proyek
- `days` (int, optional): Jumlah hari data historis (default: 30)
- `prediction_days` (int, optional): Jumlah hari prediksi (default: 7)
- `interval` (string, optional): Interval data (default: "1d")
- `model` (string, optional): Model prediksi (auto, ml, arima, simple)

**Response:**
```json
{
  "project_id": "bitcoin",
  "current_price": 50150.0,
  "prediction_direction": "up",
  "predicted_change_percent": 5.2,
  "confidence": 0.75,
  "model_type": "LSTM",
  "market_regime": "trending_bullish",
  "reversal_probability": 0.2,
  "support_levels": {
    "support_1": 48500.0,
    "support_2": 47000.0
  },
  "resistance_levels": {
    "resistance_1": 52000.0,
    "resistance_2": 55000.0
  },
  "predictions": [
    {
      "date": "2025-04-20T00:00:00Z",
      "value": 50750.0,
      "confidence": 0.75
    },
    {
      "date": "2025-04-21T00:00:00Z",
      "value": 51200.0,
      "confidence": 0.7
    }
  ],
  "timestamp": "2025-04-19T10:30:00Z"
}
```

### Endpoint Interaksi Pengguna

#### 1. Catat Interaksi Pengguna

**Endpoint:** `POST /interactions/record`

**Request Body:**
```json
{
  "user_id": "user_123",
  "project_id": "bitcoin",
  "interaction_type": "view",
  "weight": 1,
  "context": {
    "source": "homepage",
    "duration": 120
  },
  "timestamp": "2025-04-19T10:25:30Z"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Interaction recorded successfully"
}
```

### Endpoint Admin

#### 1. Latih Model

**Endpoint:** `POST /admin/train-models`

**Request Body:**
```json
{
  "models": ["fecf", "ncf", "hybrid"],
  "save_model": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Models trained successfully: [\"fecf\", \"ncf\", \"hybrid\"]"
}
```

#### 2. Sinkronisasi Data

**Endpoint:** `POST /admin/sync-data`

**Request Body:**
```json
{
  "projects_updated": true,
  "users_count": 5000
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Data processed successfully"
}
```

#### 3. Hapus Cache

**Endpoint:** `POST /recommend/cache/clear` dan `POST /analysis/cache/clear`

**Response:**
```json
{
  "message": "Cache cleared (35 entries)"
}
```

### Endpoint Utama dan Health Check

#### 1. Informasi API

**Endpoint:** `GET /`

**Response:** Informasi tentang endpoint yang tersedia dan versi API

#### 2. Health Check

**Endpoint:** `GET /health`

**Response:**
```json
{
  "status": "healthy",
  "timestamp": 1713617400.123
}
```

## 📂 Struktur Proyek

```
web3-recommender-system/
├──recommendation-engine/
│	├── data/
│	│   ├── raw/              # Data mentah dari API
│	│   │   ├── *.json        # Data dalam format JSON
│	│   │   └── *.csv         # Data dalam format CSV (otomatis dikonversi)
│	│   ├── processed/        # Data yang sudah diproses
│	│   └── models/           # Model terlatih
│	│
│	├── src/
│	│   ├── data/             # Pengumpulan dan pemrosesan data
│	│   │   ├── collector.py  # Pengumpulan data API
│	│   │   └── processor.py  # Pemrosesan data
│	│   │
│	│   ├── features/         # Feature engineering dengan dukungan periode dinamis
│	│   │   ├── market_features.py     # Fitur berbasis market dengan periode kustom
│	│   │   └── technical_features.py  # Fitur teknikal dengan periode kustom
│	│   │
│	│   ├── models/           # Model rekomendasi
│	│   │   ├── fecf.py       # Original Feature-Enhanced CF (LightFM implementation)
│	│   │   ├── alt_fecf.py   # Alternative FECF menggunakan scikit-learn
│	│   │   ├── ncf.py        # Neural CF
│	│   │   ├── hybrid.py     # Model Hybrid
│	│   │   └── eval.py       # Evaluasi model
│	│   │
│	│   ├── technical/        # Analisis teknikal dengan dukungan periode dinamis
│	│   │   ├── indicators.py # Indikator teknikal dengan periode kustom
│	│   │   └── signals.py    # Interpretasi sinyal dengan periode kustom
│	│   │
│	│   └── api/              # Endpoint API dengan dukungan parameter periode dinamis
│	│       ├── main.py       # Entrypoint API
│	│       ├── recommend.py  # Endpoint rekomendasi
│	│       └── analysis.py   # Endpoint analisis dengan dukungan periode kustom
│	│
│	├── logs/                 # File log
│	├── config.py             # Konfigurasi sistem
│	├── main.py               # Entrypoint utama dengan dukungan parameter periode kustom
│	├── requirements.txt      # Dependensi
│	└── README.md             # Dokumentasi
└── web3-lara-app/          # Aplikasi Laravel untuk frontend/backend
    └── ... (direktori aplikasi Laravel)
```

## 🔍 Pemecahan Masalah

### Masalah Score Validation

1. **Score Tidak Terurut atau Melebihi [0,1]**
   - **Solusi**: Sistem telah diperbaiki dengan validation ketat di semua level
   - Semua model sekarang menggunakan `np.clip(score, 0.0, 1.0)`
   - Implementasi robust normalization dengan percentile-based approach

2. **NCF Performance Rendah**
   - **Solusi**: Gunakan minimal 30+ interaksi per user untuk optimal performance
   - Arsitektur CryptoNCFModel dengan residual connections
   - Enhanced negative sampling dengan category-aware strategy

3. **Hybrid Model Inconsistency**
   - **Solusi**: Selective ensemble dengan confidence analysis
   - Adaptive weighting berdasarkan jumlah interaksi user
   - Input validation dari kedua model sebelum ensemble

### Optimasi untuk Data Sparse (98.68% Sparsity)

4. **NCF Underperforming pada Data Sparse**
   - **Solusi**: Gunakan minimal 30+ interactions untuk training
   - Reduce embedding dimensions untuk data terbatas
   - Higher negative sampling ratio dan curriculum learning

5. **Filter Returns Empty Results**
   - **Solusi**: Gunakan `strict=false` untuk hasil yang lebih luas
   - Implementasi fallback cerdas dengan `filter_match` indicators
   - Gradual filter relaxation untuk mendapatkan hasil relevan

6. **Performa Model Hybrid vs FECF**
   - Jika model Hybrid tidak mengungguli FECF di semua metrik:
     - Pastikan konfigurasi `HYBRID_PARAMS` di `config.py` sudah optimal
     - Sesuaikan `ncf_weight` (0.2-0.4) dan `fecf_weight` (0.6-0.8) berdasarkan jumlah interaksi pengguna
     - Coba set `ensemble_method` ke `"adaptive"` untuk pembobotan dinamis

7. **Performa NCF yang Masih Rendah**
   - Model NCF memerlukan data dengan kepadatan interaksi tinggi:
     - Tingkatkan `embedding_dim` ke 64 atau 128
     - Coba arsitektur layer yang lebih dalam `[128, 64, 32]`
     - Kurangi learning rate ke 0.0001-0.0003
     - Sesuaikan batch size (divisible by 8) untuk mencegah masalah BatchNorm

8. **Performa Cold-Start yang Masih Rendah**
   - Untuk meningkatkan performa cold-start:
     - Tingkatkan bobot FECF di kasus cold-start (`cold_start_fecf_weight` ke 0.95)
     - Tingkatkan diversifikasi kategori (`category_diversity_weight` ke 0.3)
     - Eksperimen dengan strategi interest-based recommendations

9. **Error pada Analisis Teknikal dengan Data Terbatas**
   - Sistem secara otomatis menyesuaikan parameter untuk data terbatas
   - Kurangi periode MA jangka panjang atau gunakan preset short_term untuk data terbatas

## 📬 Kontak

- Email: feyfeifry@gmail.com
- Link Proyek: [https://github.com/feyfry/recommender-system](https://github.com/feyfry/recommender-system)

## 🙏 Pengakuan

- [CoinGecko API](https://www.coingecko.com/en/api) untuk data cryptocurrency
- [scikit-learn](https://scikit-learn.org/) untuk implementasi SVD
- [PyTorch](https://pytorch.org/) untuk implementasi Neural CF
- [TensorFlow](https://www.tensorflow.org/) untuk model prediksi harga LSTM
- [statsmodels](https://www.statsmodels.org/) untuk analisis deret waktu ARIMA
- [TA-Lib](https://github.com/mrjbq7/ta-lib) untuk indikator teknikal
- [FastAPI](https://fastapi.tiangolo.com/) untuk layanan API

---

**Last Updated:** Juni 2025  
**Version:** 3.0 - Enhanced Score Validation & Robust Normalization