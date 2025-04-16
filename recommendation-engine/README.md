# Web3 Recommendation System

Sistem rekomendasi untuk proyek Web3 (cryptocurrency, token, NFT, DeFi) berbasis popularitas, tren investasi, dan analisis teknikal, membandingkan pendekatan Neural CF dan Feature-Enhanced CF.

## 📋 Deskripsi

Sistem ini menggunakan data dari CoinGecko API untuk menyediakan rekomendasi proyek Web3 berdasarkan:

- **Metrik Popularitas** (market cap, volume, metrik sosial)
- **Tren Investasi** (perubahan harga, sentimen pasar)
- **Interaksi Pengguna** (view, favorite, portfolio)
- **Fitur Proyek** (DeFi, GameFi, Layer-1, dll)
- **Analisis Teknikal** (RSI, MACD, Bollinger Bands, dll)
- **Maturitas Proyek** (usia, aktivitas developer, engagement sosial)

Sistem ini mengimplementasikan beberapa pendekatan rekomendasi:
1. **Feature-Enhanced Collaborative Filtering** menggunakan LightFM
2. **Neural Collaborative Filtering** menggunakan PyTorch
3. **Model Hybrid** yang menggabungkan kedua pendekatan

## 🚀 Fitur Utama

1. **Model Rekomendasi Ganda:**
   - Feature-Enhanced CF untuk rekomendasi berbasis konten
   - Neural CF untuk personalisasi berbasis deep learning
   - Model Hybrid untuk performa optimal

2. **Integrasi Analisis Teknikal:**
   - Sinyal trading (buy/sell/hold) dengan tingkat kepercayaan
   - Personalisasi berdasarkan toleransi risiko pengguna
   - Deteksi peristiwa pasar (pump, dump, volatilitas tinggi)

3. **Penanganan Cold-Start:**
   - Rekomendasi untuk pengguna baru berdasarkan minat
   - Rekomendasi berbasis fitur untuk proyek baru

4. **API Service:**
   - Endpoint REST API untuk integrasi dengan aplikasi backend Laravel
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

### Feature-Enhanced CF (LightFM)

Model Feature-Enhanced CF menggabungkan collaborative filtering dengan fitur proyek (kategori, blockchain, dll) untuk memberikan rekomendasi yang relevan, terutama berguna untuk skenario cold-start.

### Neural CF (PyTorch)

Model Neural CF menggunakan deep learning untuk menangkap pola kompleks dalam interaksi user-item, menggabungkan matrix factorization dengan jaringan multi-layer perceptron untuk akurasi yang lebih baik.

### Model Hybrid

Model Hybrid menggabungkan kekuatan kedua pendekatan:
- FECF untuk rekomendasi berbasis fitur dan penanganan cold-start
- NCF untuk personalisasi mendalam dengan pengguna yang memiliki riwayat interaksi yang cukup

## 📈 Analisis Teknikal

Komponen analisis teknikal menggunakan TA-Lib (atau implementasi berbasis pandas) untuk menghitung indikator teknikal dan menghasilkan sinyal trading:

- **Indikator Tren:** Moving Averages, MACD, ADX
- **Indikator Momentum:** RSI, Stochastic, CCI
- **Indikator Volatilitas:** Bollinger Bands, ATR
- **Indikator Volume:** OBV, MFI, Chaikin A/D

Sinyal dipersonalisasi berdasarkan toleransi risiko pengguna (rendah, menengah, tinggi).

## 🛠️ Instalasi

### Prasyarat

- Python 3.10 (LightFM tidak kompatibel dengan Python 3.12+)
- pip (Python package manager)
- CoinGecko API key (untuk pengumpulan data)
- PostgreSQL (untuk penyimpanan data)

### Langkah Instalasi

1. Clone repository:
	```bash
	git clone https://github.com/feyfry/web3-recommender-system.git
	cd web3-recommendation-system
	```

2. Pastikan Python 3.10 terinstal:
	```bash
	# Periksa versi Python yang tersedia
	py --list

	# Jika Python 3.10 tidak terdaftar, Anda perlu menginstalnya terlebih dahulu
	```

3. Buat dan aktifkan lingkungan virtual dengan Python 3.10:
	```bash
	# Windows
	py -3.10 -m venv venv
	venv\Scripts\activate

	# Linux/Mac
	python3.10 -m venv venv
	source venv/bin/activate

	# Git Bash di Windows
	py -3.10 -m venv venv
	source venv/Scripts/activate
	```

4. Install dependensi dalam lingkungan virtual:
	```bash
	pip install -r requirements.txt
	```

5. Konfigurasi API key:
   - Buat file `.env` di direktori root
   - Tambahkan CoinGecko API key Anda:
     ```
     COINGECKO_API_KEY="your-api-key"
     ```

6. Setup database:
   - Buat database PostgreSQL
   - Update konfigurasi database di `config.py`

### Instalasi TA-Lib (Opsional)

TA-Lib memerlukan kompilasi library C dan bisa cukup rumit, terutama di Windows. Berikut panduan lengkap instalasinya.

---

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
python main.py collect --limit 500 --detail-limit 100
# tambahkan param --rate-limit 3 jika ingin menghindari rate limit lebih panjang, default sudah --rate-limit 2, tidak perlu di definisikan lagi

# Memproses data yang dikumpulkan
python main.py process --users 500

# Melatih model rekomendasi
python main.py train --fecf --ncf --hybrid

# Evaluasi model
python main.py evaluate --cold-start

# Menghasilkan rekomendasi untuk pengguna
python main.py recommend --user-id user_123 --model hybrid --num 10

# Menghasilkan sinyal trading untuk proyek
python main.py signals --project-id bitcoin --risk medium

# Menjalankan server API
python main.py api

# Menjalankan pipeline lengkap terorganisir
python main.py run
```

### Pipeline yang Terorganisir
Pipeline baru yang terorganisir memiliki langkah-langkah yang ditentukan dengan jelas dan status kemajuan yang mudah dipantau:

1. **Data Collection**: Mengumpulkan data dari CoinGecko API
2. **Data Processing**: Memproses data mentah dengan perhitungan metrik
3. **Building Matrices**: Membangun matriks user-item dan similarity
4. **Model Training**: Melatih model rekomendasi
5. **Sample Recommendations**: Generasi rekomendasi sampel (opsional)
6. **Analysis**: Analisis hasil rekomendasi (opsional)

Untuk menjalankan pipeline dengan opsi kustom:
```bash
# Jalankan pipeline lengkap
python main.py run

# Jalankan pipeline tanpa rekomendasi sampel dan analisis
python main.py run --skip-recommendations --skip-analysis
```

### Menggunakan API

Server API dapat dijalankan dengan:
```bash
python main.py api
```

Endpoint API:
- `/recommend/projects` - Mendapatkan rekomendasi proyek
- `/recommend/trending` - Mendapatkan proyek trending
- `/recommend/popular` - Mendapatkan proyek populer
- `/recommend/similar/{project_id}` - Mendapatkan proyek serupa
- `/analysis/trading-signals` - Mendapatkan sinyal trading
- `/analysis/indicators` - Mendapatkan indikator teknikal
- `/analysis/market-events/{project_id}` - Mendeteksi peristiwa pasar
- `/analysis/alerts/{project_id}` - Mendapatkan alert teknikal
- `/analysis/price-prediction/{project_id}` - Mendapatkan prediksi harga

### Integrasi dengan Laravel

Recommendation Engine ini dirancang untuk diintegrasikan dengan aplikasi Laravel dengan menghubungkan API endpoints ke backend Laravel. Dalam arsitektur ini:

1. Recommendation Engine berjalan sebagai layanan terpisah
2. Backend Laravel memanggil API engine untuk mendapatkan rekomendasi
3. Frontend Laravel Blade + React menampilkan rekomendasi kepada pengguna

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
│   ├── features/         # Feature engineering
│   │   ├── market_features.py  # Fitur berbasis market
│   │   └── technical_features.py  # Fitur teknikal
│   │
│   ├── models/           # Model rekomendasi
│   │   ├── fecf.py       # Feature-Enhanced CF
│   │   ├── ncf.py        # Neural CF
│   │   ├── hybrid.py     # Model Hybrid
│   │   └── eval.py       # Evaluasi model
│   │
│   ├── technical/        # Analisis teknikal
│   │   ├── indicators.py # Indikator teknikal
│   │   └── signals.py    # Interpretasi sinyal
│   │
│   └── api/              # Endpoint API
│       ├── main.py       # Entrypoint API
│       ├── recommend.py  # Endpoint rekomendasi
│       └── analysis.py   # Endpoint analisis
│
├── logs/                 # File log
├── config.py             # Konfigurasi sistem
├── main.py               # Entrypoint utama
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

Laporan evaluasi disimpan di `data/models/`.

## 🔍 Pemecahan Masalah

1. **Masalah Kompatibilitas Python**
   - LightFM tidak kompatibel dengan Python 3.12+, gunakan Python 3.10
   - Jika menggunakan sistem dengan multiple versi Python:
     ```bash
     # Windows
     py -3.10 -m pip install -r requirements.txt
     
     # Linux/Mac
     python3.10 -m pip install -r requirements.txt
     ```

2. **Rate Limiting CoinGecko API**
   - Gunakan delay yang lebih panjang antar request:
     ```bash
     python main.py collect --rate-limit 3
     ```
   - Pertimbangkan untuk mendapatkan API key untuk limit yang lebih tinggi

3. **Masalah Instalasi TA-Lib**
   - Jika mengalami kesulitan menginstal TA-Lib, aktifkan fallback ke pandas-ta dengan mengedit `config.py`:
     ```python
     USE_TALIB = False  # Ubah ke False untuk menggunakan pandas-ta sebagai alternatif
     ```
   - Pastikan kompiler C tersedia di sistem Anda (Visual C++ di Windows, GCC di Linux)

4. **Masalah Memori dengan Dataset Besar**
   - Proses data dalam batch:
     ```bash
     python main.py process --batch-size 1000
     ```
   - Kurangi jumlah koin yang dikumpulkan:
     ```bash
     python main.py collect --limit 250
     ```

5. **Error Pelatihan Model NCF**
   - Jika Anda mengalami error "stack expects each tensor to be equal size" saat melatih model:
     ```bash
     # Melatih model secara terpisah
     python main.py train --fecf  # Hanya melatih FECF
     
     # Atau gunakan batch size yang lebih kecil
     python main.py train --ncf --batch-size 64
     ```
   - Model ini telah diperbarui untuk menangani batch yang tidak konsisten

6. **Masalah Lingkungan Virtual**
   - Jika terjadi konflik dependensi, buat lingkungan virtual baru:
     ```bash
     deactivate  # Keluar dari venv saat ini jika ada
     rm -rf venv  # Hapus venv lama
     py -3.10 -m venv venv  # Buat venv baru dengan Python 3.10
     venv\Scripts\activate  # Aktifkan venv
     pip install -r requirements.txt  # Install dependensi
     ```

## 📝 Lisensi

Didistribusikan di bawah Lisensi MIT. Lihat `LICENSE` untuk informasi lebih lanjut.

## 📬 Kontak

Nama Anda - email@example.com

Link Proyek: [https://github.com/feyfry/web3-recommendation-system](https://github.com/feyfry/web3-recommendation-system)

## 🙏 Pengakuan

- [CoinGecko API](https://www.coingecko.com/en/api) untuk data cryptocurrency
- [LightFM](https://github.com/lyst/lightfm) untuk implementasi Feature-Enhanced CF
- [PyTorch](https://pytorch.org/) untuk implementasi Neural CF
- [TA-Lib](https://github.com/mrjbq7/ta-lib) untuk indikator teknikal
- [FastAPI](https://fastapi.tiangolo.com/) untuk layanan API