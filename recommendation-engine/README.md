# Recommendation System Web3 Based

Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, NFT, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal dengan dukungan penuh untuk periode indikator dinamis, membandingkan pendekatan Neural CF dan Feature-Enhanced CF.

## 📋 Deskripsi

Sistem ini menggunakan data dari CoinGecko API untuk menyediakan rekomendasi proyek Web3 berdasarkan:

- **Metrik Popularitas** (market cap, volume, metrik sosial)
- **Tren Investasi** (perubahan harga, sentimen pasar)
- **Interaksi Pengguna** (view, favorite, portfolio)
- **Fitur Proyek** (DeFi, GameFi, Layer-1, dll)
- **Analisis Teknikal** (RSI, MACD, Bollinger Bands, dll) dengan periode yang dapat dikonfigurasi penuh
- **Maturitas Proyek** (usia, aktivitas developer, engagement sosial)

Sistem ini mengimplementasikan beberapa pendekatan rekomendasi:
1. **Feature-Enhanced Collaborative Filtering** menggunakan scikit-learn SVD
2. **Neural Collaborative Filtering** menggunakan PyTorch
3. **Model Hybrid** yang menggabungkan kedua pendekatan

## 🚀 Fitur Utama

1. **Model Rekomendasi Ganda:**
   - Feature-Enhanced CF untuk rekomendasi berbasis konten
   - Neural CF untuk personalisasi berbasis deep learning
   - Model Hybrid untuk performa optimal

2. **Integrasi Analisis Teknikal dengan Periode Dinamis:**
   - Periode indikator yang dapat dikonfigurasi untuk berbagai gaya trading (jangka pendek, standar, jangka panjang)
   - Sinyal trading (buy/sell/hold) dengan tingkat kepercayaan
   - Personalisasi berdasarkan toleransi risiko pengguna
   - Deteksi peristiwa pasar (pump, dump, volatilitas tinggi) dengan threshold yang dapat disesuaikan
   - Preset gaya trading untuk jangka pendek, standar, dan jangka panjang

3. **Penanganan Cold-Start:**
   - Rekomendasi untuk pengguna baru berdasarkan minat
   - Rekomendasi berbasis fitur untuk proyek baru

4. **API Service dengan Konfigurasi Fleksibel:**
   - Endpoint REST API untuk integrasi dengan aplikasi backend Laravel
   - Dukungan parameter periode indikator kustom melalui API
   - Caching untuk performa yang lebih baik
   - Dokumentasi komprehensif

5. **Pipeline Data Otomatis:**
   - Pengumpulan data reguler dari CoinGecko
   - Pipeline pemrosesan untuk ekstraksi fitur
   - Pelatihan dan evaluasi model otomatis

## 🏗️ Arsitektur Sistem

Sistem terdiri dari tiga komponen utama:

1. **Recommendation Engine** (Python + ML Libraries) - Core sistem rekomendasi
   - CoinGecko API Collector
   - Data Preprocessing
   - Collaborative Filtering
   - Matrix Builder
   - Model Training & Evaluation
   - Technical Analysis dengan periode indikator yang dapat dikonfigurasi

2. **Backend** (Laravel) - Mengelola aplikasi web dan logika bisnis
   - REST API Controllers
   - Authentication UI
   - Business Logic
   - Data Models

3. **Frontend** (Laravel Blade + React.js) - Antarmuka pengguna
   - User Interface
   - API Client
   - State Management
   - User Interaction Collection

4. **PostgreSQL Database** - Penyimpanan data utama sistem

## 📊 Model Rekomendasi

### Feature-Enhanced CF (Scikit-learn SVD)

Model Feature-Enhanced CF menggunakan SVD (Singular Value Decomposition) dari scikit-learn untuk matrix factorization dan menggabungkannya dengan content-based filtering berdasarkan fitur proyek (kategori, blockchain, dll). Implementasi ini menggantikan LightFM yang sebelumnya digunakan karena masalah kompatibilitas.

**Kelebihan FECF:**
- Efektif untuk pengguna baru (cold-start) dengan data interaksi terbatas
- Mampu merekomendasikan item berdasarkan kesamaan fitur seperti kategori dan chain
- Dapat menghasilkan rekomendasi berkualitas bahkan dengan data interaksi yang sparse

### Neural CF (PyTorch)

Model Neural CF menggunakan deep learning untuk menangkap pola kompleks dalam interaksi user-item, menggabungkan matrix factorization dengan jaringan multi-layer perceptron untuk akurasi yang lebih baik. Model menggunakan embeddings untuk user dan item, serta layer MLP untuk mempelajari pola interaksi non-linear.

**Kelebihan NCF:**
- Sangat baik dalam personalisasi untuk pengguna dengan banyak interaksi (>20)
- Dapat menangkap pola dan preferensi kompleks yang tidak tertangkap oleh model tradisional
- Memberikan prediksi yang lebih akurat untuk pengguna yang aktif

**Keterbatasan NCF:**
- Memerlukan jumlah interaksi minimum untuk memberikan rekomendasi yang akurat
- Kurang efektif pada cold-start problem dibandingkan dengan FECF

### Model Hybrid

Model Hybrid menggabungkan kekuatan kedua pendekatan dengan strategi filter-then-rerank:

1. Menggunakan FECF untuk menghasilkan kandidat awal (filtering)
2. Menggunakan NCF untuk memperbaiki peringkat kandidat (reranking)
3. Menerapkan diversifikasi kategori untuk memastikan keragaman rekomendasi

Model Hybrid menerapkan pembobotan dinamis berdasarkan jumlah interaksi pengguna:
- **Pengguna Cold-Start (0 interaksi)**: Mengandalkan rekomendasi populer dan pengelompokan kategori
- **Pengguna dengan Interaksi Terbatas (1-20)**: Bobot FECF lebih dominan dengan kontribusi NCF yang meningkat secara bertahap
- **Pengguna dengan Interaksi Optimal (>20)**: Bobot seimbang antara FECF dan NCF

## 📈 Evaluasi Model dan Metrik Performa

Sistem ini menggunakan berbagai metrik standar untuk evaluasi kualitas rekomendasi:

| Metrik | Deskripsi | Interpretasi |
|--------|-----------|--------------|
| **Precision** | Persentase rekomendasi yang relevan | Semakin tinggi = semakin akurat rekomendasi yang diberikan |
| **Recall** | Persentase item relevan yang berhasil ditemukan | Semakin tinggi = semakin lengkap rekomendasi yang diberikan |
| **F1** | Rata-rata harmonik dari precision dan recall | Keseimbangan antara akurasi dan kelengkapan |
| **NDCG** | Normalized Discounted Cumulative Gain | Mengukur kualitas urutan rekomendasi (peringkat) |
| **Hit Ratio** | Persentase pengguna yang mendapat minimal satu rekomendasi relevan | Mengukur cakupan layanan pada populasi pengguna |
| **MRR** | Mean Reciprocal Rank | Mengukur seberapa cepat sistem menemukan rekomendasi pertama yang relevan |

## 💡 Karakteristik Domain Cryptocurrency dalam Rekomendasi

Domain cryptocurrency memiliki karakteristik unik yang mempengaruhi kinerja sistem rekomendasi:

1. **Volatilitas Tinggi**: Perubahan harga dan popularitas yang cepat membuat pola interaksi berubah-ubah
2. **Pengaruh Eksternal**: Keputusan investasi dipengaruhi oleh berita, media sosial, dan sentimen pasar
3. **Data Sparsity**: Pengguna cenderung berinteraksi dengan sedikit token, menghasilkan matriks yang sparse
4. **Dominasi Popularitas**: Proyek populer (Bitcoin, Ethereum) mendominasi interaksi, menciptakan distribusi long-tail
5. **Konteks Temporal**: Waktu sangat mempengaruhi relevansi rekomendasi dalam domain crypto

Karakteristik ini menjelaskan mengapa:
- FECF bisa bersaing dengan model yang lebih kompleks seperti Hybrid
- NCF mengalami tantangan dalam memberikan rekomendasi personalisasi
- Strategi cold-start yang kuat sangat penting dalam domain ini

## 📝 Tantangan dan Perbaikan Potensial

Beberapa tantangan dan perbaikan potensial untuk sistem rekomendasi ini:

1. **Pembobotan Dinamis**: 
   - Mengganti hardcoded weight dengan pembobotan dinamis berdasarkan jumlah interaksi pengguna
   - Menyesuaikan bobot FECF, NCF, dan faktor diversitas secara adaptif
   - Menerapkan transisi halus antara pengguna cold-start dan pengguna yang sudah mapan

2. **Strategi Cold-Start yang Lebih Baik**:
   - Meningkatkan diversifikasi kategori untuk pengguna baru
   - Menerapkan exploratory recommendations dengan elemen randomness
   - Menggunakan pendekatan cluster-based untuk mencocokkan pengguna baru dengan grup yang serupa

3. **Peningkatan Kualitas NCF**:
   - Memperluas model dengan informasi kontekstual
   - Meningkatkan negative sampling untuk domain yang sparse
   - Menerapkan teknik regularisasi yang lebih kuat

4. **Integrasi Sinyal Teknikal dan Tren**:
   - Menggabungkan sinyal teknikal ke dalam proses rekomendasi
   - Memasukkan tren sosial media dan sentimen
   - Mempertimbangkan volatilitas dan momentum dalam pemberian peringkat

## 📈 Analisis Teknikal dengan Periode Dinamis

Komponen analisis teknikal sekarang mendukung periode indikator yang sepenuhnya dapat dikonfigurasi, memungkinkan penyesuaian untuk berbagai gaya trading:

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

Sinyal dipersonalisasi berdasarkan toleransi risiko pengguna (rendah, menengah, tinggi) dan dapat disesuaikan dengan berbagai gaya trading.

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

#### 📌 Catatan Tambahan:
- Pastikan pip dan python yang Anda pakai dari virtual environment (venv).
- Untuk cek versi Python:
```
python --version
```
- Untuk cek lokasi pip:
```
where pip
```

## 🚀 Penggunaan

### Command Line Interface

Project ini menyediakan CLI komprehensif untuk semua fungsi utama:

```bash
# Mengumpulkan data dari CoinGecko
python main.py collect --limit 500 --detail-limit 500
# tambahkan param --rate-limit 3 jika ingin menghindari rate limit lebih panjang

# Memproses data yang dikumpulkan
python main.py process --users 2000

# Melatih model rekomendasi
python main.py train --fecf --ncf --hybrid

# Evaluasi model
python main.py evaluate --cold-start

# Menghasilkan rekomendasi untuk pengguna
python main.py recommend --user-id user_1 --model fecf --num 10

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

Secara default, API akan berjalan di `http://0.0.0.0:8000`.

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
  "risk_tolerance": "medium"
}
```

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
      "price_usd": 50000,
      "price_change_24h": 2.5,
      "price_change_7d": -1.2,
      "market_cap": 1000000000000,
      "volume_24h": 30000000000,
      "popularity_score": 98.5,
      "trend_score": 85.2,
      "category": "layer-1",
      "chain": "bitcoin",
      "recommendation_score": 0.95
    }
  ],
  "timestamp": "2025-04-19T10:30:00Z",
  "is_cold_start": false,
  "category_filter": "defi",
  "chain_filter": "ethereum",
  "execution_time": 0.125
}
```

#### 2. Dapatkan Proyek Trending

**Endpoint:** `GET /recommend/trending`

**Parameters:**
- `limit` (int, optional): Jumlah proyek (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")

**Response:** Array dari objek `ProjectResponse`

#### 3. Dapatkan Proyek Populer

**Endpoint:** `GET /recommend/popular`

**Parameters:**
- `limit` (int, optional): Jumlah proyek (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")

**Response:** Array dari objek `ProjectResponse`

#### 4. Dapatkan Proyek Serupa

**Endpoint:** `GET /recommend/similar/{project_id}`

**Parameters:**
- `project_id` (string): ID proyek
- `limit` (int, optional): Jumlah proyek serupa (default: 10)
- `model_type` (string, optional): Model yang digunakan (default: "fecf")

**Response:** Array dari objek `ProjectResponse`

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
- `periods` (object, optional): Periode indikator kustom untuk analisis

**Response:**
```json
{
  "project_id": "bitcoin",
  "current_price": 50150.0,
  "prediction_direction": "up",
  "predicted_change_percent": 5.2,
  "confidence": 0.75,
  "predictions": [
    {
      "date": "2025-04-20T00:00:00Z",
      "predicted_price": 50750.0,
      "confidence": 0.75
    },
    {
      "date": "2025-04-21T00:00:00Z",
      "predicted_price": 51200.0,
      "confidence": 0.7
    }
  ],
  "data_source": "Real market data"
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

## 🔄 Integrasi dengan Laravel

Recommendation Engine ini dirancang untuk diintegrasikan dengan aplikasi Laravel dengan menghubungkan API endpoints ke backend Laravel. Dalam arsitektur ini:

1. Recommendation Engine berjalan sebagai layanan terpisah
2. Backend Laravel memanggil API engine untuk mendapatkan rekomendasi
3. Frontend Laravel Blade + React menampilkan rekomendasi kepada pengguna

### Contoh integrasi dari Laravel ke API engine:

```php
// Example Laravel Controller
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommendationController extends Controller
{
    private $apiUrl = 'http://localhost:8000';
    
    public function getRecommendations(Request $request)
    {
        $user = auth()->user();
        
        $response = Http::post($this->apiUrl . '/recommend/projects', [
            'user_id' => $user->id,
            'model_type' => 'hybrid',
            'num_recommendations' => 10,
            'exclude_known' => true,
            'category' => $request->input('category'),
            'chain' => $request->input('chain'),
            'risk_tolerance' => $user->risk_profile ?? 'medium'
        ]);
        
        if ($response->successful()) {
            return view('recommendations', [
                'recommendations' => $response->json('recommendations')
            ]);
        }
        
        return back()->with('error', 'Failed to get recommendations');
    }
    
    // Contoh mendapatkan sinyal trading dengan periode kustom
    public function getTradingSignals(Request $request, $projectId)
    {
        $user = auth()->user();
        $tradingStyle = $request->input('trading_style', 'standard');
        
        $payload = [
            'project_id' => $projectId,
            'days' => 30,
            'interval' => '1d',
            'risk_tolerance' => $user->risk_profile ?? 'medium',
            'trading_style' => $tradingStyle
        ];
        
        // Jika ada periode kustom individual
        $customPeriods = $request->input('custom_periods');
        if ($customPeriods) {
            $payload['periods'] = $customPeriods;
        }
        
        $response = Http::post($this->apiUrl . '/analysis/trading-signals', $payload);
        
        if ($response->successful()) {
            return view('trading_signals', [
                'signals' => $response->json()
            ]);
        }
        
        return back()->with('error', 'Failed to get trading signals');
    }
    
    public function recordInteraction(Request $request)
    {
        $user = auth()->user();
        
        $response = Http::post($this->apiUrl . '/interactions/record', [
            'user_id' => $user->id,
            'project_id' => $request->input('project_id'),
            'interaction_type' => $request->input('type'),
            'weight' => 1,
            'timestamp' => now()->toIso8601String()
        ]);
        
        return $response->successful() 
            ? response()->json(['status' => 'success']) 
            : response()->json(['status' => 'error'], 500);
    }
}
```

## 📂 Struktur Proyek

```
web3-recommendation-system/
├── data/
│   ├── raw/              # Data mentah dari API
│   │   ├── *.json        # Data dalam format JSON
│   │   └── *.csv         # Data dalam format CSV (otomatis dikonversi)
│   ├── processed/        # Data yang sudah diproses
│   └── models/           # Model terlatih
│
├── src/
│   ├── data/             # Pengumpulan dan pemrosesan data
│   │   ├── collector.py  # Pengumpulan data API
│   │   └── processor.py  # Pemrosesan data
│   │
│   ├── features/         # Feature engineering dengan dukungan periode dinamis
│   │   ├── market_features.py     # Fitur berbasis market dengan periode kustom
│   │   └── technical_features.py  # Fitur teknikal dengan periode kustom
│   │
│   ├── models/           # Model rekomendasi
│   │   ├── fecf.py       # Original Feature-Enhanced CF (LightFM implementation)
│   │   ├── alt_fecf.py   # Alternative FECF menggunakan scikit-learn
│   │   ├── ncf.py        # Neural CF
│   │   ├── hybrid.py     # Model Hybrid
│   │   └── eval.py       # Evaluasi model
│   │
│   ├── technical/        # Analisis teknikal dengan dukungan periode dinamis
│   │   ├── indicators.py # Indikator teknikal dengan periode kustom
│   │   └── signals.py    # Interpretasi sinyal dengan periode kustom
│   │
│   └── api/              # Endpoint API dengan dukungan parameter periode dinamis
│       ├── main.py       # Entrypoint API
│       ├── recommend.py  # Endpoint rekomendasi
│       └── analysis.py   # Endpoint analisis dengan dukungan periode kustom
│
├── logs/                 # File log
├── config.py             # Konfigurasi sistem
├── main.py               # Entrypoint utama dengan dukungan parameter periode kustom
├── requirements.txt      # Dependensi
└── README.md             # Dokumentasi
```

## 📊 Metrik Evaluasi

Model dievaluasi menggunakan metrik standar sistem rekomendasi:
- **Precision@K**: Rasio proyek relevan dari K rekomendasi
- **Recall@K**: Rasio proyek relevan yang berhasil direkomendasikan
- **NDCG@K**: Normalized Discounted Cumulative Gain (mempertimbangkan ranking)
- **MRR**: Mean Reciprocal Rank
- **Hit Ratio**: Rasio pengguna yang menerima minimal satu rekomendasi relevan
- **MAP@K**: Mean Average Precision at K

Laporan evaluasi disimpan di `data/models/` dalam format JSON, markdown, atau teks biasa.

## 🔍 Pemecahan Masalah

1. **Performa NCF Rendah**
   - NCF membutuhkan lebih banyak data pengguna untuk bekerja optimal
   - Tingkatkan parameter epochs di `config.py`
   ```python
   NCF_PARAMS = {
       "embedding_dim": 64,
       "layers": [128, 64, 32, 16],
       "learning_rate": 0.001,
       "batch_size": 128,  # Kurangi dari 256
       "epochs": 50,       # Tingkatkan dari 20
       "val_ratio": 0.2,
       "dropout": 0.2,
       "weight_decay": 1e-4
   }
   ```
   - Gunakan jumlah users yang lebih banyak saat memproses data:
   ```bash
   python main.py process --users 5000
   ```

2. **Menggunakan Implementasi Alternatif (alt_fecf vs fecf)**
   - Sistem secara default menggunakan implementasi scikit-learn SVD (`alt_fecf.py`)
   - Jika ingin menggunakan implementasi LightFM original, aktifkan di `src/models/hybrid.py`:
   ```python
   from src.models.fecf import FeatureEnhancedCF  # Uncomment untuk menggunakan LightFM
   # from src.models.alt_fecf import FeatureEnhancedCF  # Comment jika menggunakan LightFM
   ```
   - Jika menggunakan LightFM, pastikan untuk menginstall LightFM:
   ```bash
   pip install lightfm
   ```
   - Perhatikan bahwa LightFM memerlukan Python 3.10 (tidak kompatibel dengan Python 3.12+)

3. **Rate Limiting CoinGecko API**
   - Gunakan delay yang lebih panjang antar request:
     ```bash
     python main.py collect --rate-limit 3
     ```
   - Pertimbangkan untuk mendapatkan API key untuk limit yang lebih tinggi

4. **Masalah Instalasi TA-Lib**
   - Pastikan kompiler C tersedia di sistem Anda (Visual C++ di Windows, GCC di Linux)

5. **Masalah Memori dengan Dataset Besar**
   - Proses data dalam batch:
     ```bash
     python main.py process --batch-size 1000
     ```
   - Kurangi jumlah koin yang dikumpulkan:
     ```bash
     python main.py collect --limit 250
     ```

6. **Penyesuaian Model Hybrid**
   - Jika NCF tetap berkinerja buruk, sesuaikan bobot hybrid:
   ```python
   HYBRID_PARAMS = {
       "ncf_weight": 0.3,   # Kurangi dari 0.5
       "fecf_weight": 0.7   # Tingkatkan dari 0.5
   }
   ```

7. **Model Loading Issues**
   - Jika model tidak dimuat dengan benar, periksa file model di direktori `data/models/`
   - Gunakan opsi debug untuk melihat detil loading proses model:
   ```bash
   python main.py debug --user-id user_1 --model hybrid --num 10
   ```

8. **Caching Issues pada API**
   - Jika API memberikan hasil yang tidak diperbarui, hapus cache:
   ```bash
   curl -X POST http://localhost:8000/recommend/cache/clear
   curl -X POST http://localhost:8000/analysis/cache/clear
   ```

9. **Masalah dengan Indikator Teknikal**
   - Jika Anda mengalami hasil analisis teknikal yang tidak konsisten, coba pastikan data cukup untuk indikator yang dipilih:
   ```bash
   # Tingkatkan jumlah hari data yang diminta
   python main.py signals --project-id bitcoin --days 60
   ```
   - Untuk periode indikator yang lebih panjang, pastikan untuk menggunakan jumlah data historis yang lebih banyak

10. **Meningkatkan Kualitas Hybrid Model**
    - Jika model Hybrid tidak memberikan peningkatan signifikan, pastikan bobot dinamis sudah diimplementasikan:
    ```python
    # Di hybrid.py, pastikan menggunakan bobot dinamis
    def recommend_for_user(self, user_id: str, n: int = 10, exclude_known: bool = True):
        # ...
        # Count user interactions
        user_interactions = self.user_item_matrix.loc[user_id]
        user_interaction_count = (user_interactions > 0).sum()
        
        # Get parameters from config
        interaction_threshold_low = self.params.get('interaction_threshold_low', 5)
        interaction_threshold_high = self.params.get('interaction_threshold_high', 20)
        
        # Adjust weights dynamically based on interaction count
        if user_interaction_count < interaction_threshold_low:
            effective_fecf_weight = 0.8
            effective_ncf_weight = 0.1
        elif user_interaction_count < interaction_threshold_high:
            ratio = (user_interaction_count - interaction_threshold_low) / (interaction_threshold_high - interaction_threshold_low)
            effective_fecf_weight = 0.8 - (0.3 * ratio)  # Gradually decrease FECF weight
            effective_ncf_weight = 0.1 + (0.4 * ratio)   # Gradually increase NCF weight
        else:
            effective_fecf_weight = 0.5
            effective_ncf_weight = 0.5
        # ...
    ```

## 📬 Kontak

Nama Anda - feyfeifry@gmail.com

Link Proyek: [https://github.com/feyfry/recommender-system](https://github.com/feyfry/recommender-system)

## 🙏 Pengakuan

- [CoinGecko API](https://www.coingecko.com/en/api) untuk data cryptocurrency
- [scikit-learn](https://scikit-learn.org/) untuk implementasi SVD
- [PyTorch](https://pytorch.org/) untuk implementasi Neural CF
- [TA-Lib](https://github.com/mrjbq7/ta-lib) untuk indikator teknikal
- [FastAPI](https://fastapi.tiangolo.com/) untuk layanan API