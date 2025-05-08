@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-warning/20 p-2 clay-badge mr-3">
                <i class="fas fa-chart-line text-warning"></i>
            </div>
            Analisis Teknikal dengan Periode Dinamis
        </h1>
        <p class="text-lg">
            Analisis berbagai indikator teknikal dengan periode yang dapat disesuaikan untuk cryptocurrency. Pilih dari preset yang tersedia atau sesuaikan parameter indikator secara manual.
        </p>
    </div>

    <div class="clay-card p-6 mb-8 bg-info/5">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h2 class="text-xl font-bold mb-3 flex items-center">
                    <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                    Disclaimer:
                </h2>
                <p class="text-gray-600 mb-2">
                    Informasi berikut ini disediakan hanya untuk kepentingan SPOT TRADING. Tidak direkomendasikan untuk digunakan pada perdagangan berjangka (Futures), Margin Trading, atau instrumen derivatif lainnya. Mohon lakukan analisis dan pertimbangan pribadi sebelum mengambil keputusan. Risiko sepenuhnya menjadi tanggung jawab pengguna.
                </p>
            </div>
        </div>
    </div>

    <!-- Script untuk AlpineJS -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('technicalAnalysis', () => ({
                loading: false,
                projectId: '{{ $project ? $project->id : "" }}',
                tradingStyle: 'standard',
                riskTolerance: 'medium',
                days: 30,
                interval: '1d', // Default interval
                lookback: 5,     // Default lookback
                predictionDays: 7, // Default predictionDays
                windowSize: 14,    // Default windowSize
                pumpThreshold: 10, // Default pumpThreshold (%)
                dumpThreshold: 10, // Default dumpThreshold (%)
                volatilityThreshold: 15, // Default volatilityThreshold
                showAdvanced: false, // Toggle untuk advanced settings
                params: {
                    rsi_period: 14,
                    macd_fast: 12,
                    macd_slow: 26,
                    macd_signal: 9,
                    bb_period: 20,
                    stoch_k: 14,
                    stoch_d: 3,
                    ma_short: 20,
                    ma_medium: 50,
                    ma_long: 200
                },
                customParams: false,
                results: null,
                marketEvents: null,
                alertsData: null,
                predictionData: null,
                indicatorsData: null,
                loadingIndicators: false,
                loadingMarketEvents: false,
                loadingAlerts: false,
                loadingPrediction: false,
                activeTab: 'signals', // 'signals', 'indicators', 'events', 'alerts', 'prediction'
                presets: {
                    'short_term': {
                        rsi_period: 7,
                        macd_fast: 8,
                        macd_slow: 17,
                        macd_signal: 9,
                        bb_period: 10,
                        stoch_k: 7,
                        stoch_d: 3,
                        ma_short: 10,
                        ma_medium: 30,
                        ma_long: 60
                    },
                    'standard': {
                        rsi_period: 14,
                        macd_fast: 12,
                        macd_slow: 26,
                        macd_signal: 9,
                        bb_period: 20,
                        stoch_k: 14,
                        stoch_d: 3,
                        ma_short: 20,
                        ma_medium: 50,
                        ma_long: 200
                    },
                    'long_term': {
                        rsi_period: 21,
                        macd_fast: 19,
                        macd_slow: 39,
                        macd_signal: 9,
                        bb_period: 30,
                        stoch_k: 21,
                        stoch_d: 7,
                        ma_short: 50,
                        ma_medium: 100,
                        ma_long: 200
                    }
                },
                init() {
                    this.applyPreset();
                },
                applyPreset() {
                    if (!this.customParams) {
                        const style = this.tradingStyle;
                        Object.keys(this.presets[style]).forEach(key => {
                            this.params[key] = this.presets[style][key];
                        });
                    }
                },
                analyze() {
                    if (!this.projectId) {
                        alert('Silakan pilih proyek terlebih dahulu');
                        return;
                    }

                    this.loading = true;
                    this.results = null;

                    const requestData = {
                        project_id: this.projectId,
                        days: this.days,
                        interval: this.interval, // Sekarang menggunakan interval dari UI
                        risk_tolerance: this.riskTolerance,
                        trading_style: this.tradingStyle
                    };

                    if (this.customParams) {
                        Object.keys(this.params).forEach(key => {
                            requestData[key] = this.params[key];
                        });
                    }

                    fetch('{{ route("panel.technical-analysis.trading-signals") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(requestData)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.error) {
                            throw new Error(data.message || 'Terjadi kesalahan pada analisis');
                        }
                        this.results = data;
                        this.loading = false;

                        // After getting signals, load additional data in the background
                        this.loadIndicators();
                        this.loadMarketEvents();
                        this.loadAlerts();
                        this.loadPricePrediction();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.loading = false;
                        alert('Terjadi kesalahan saat menganalisis: ' + error.message);
                    });
                },
                loadIndicators() {
                    if (!this.projectId) return;

                    this.loadingIndicators = true;

                    const requestData = {
                        project_id: this.projectId,
                        days: this.days,
                        interval: this.interval, // Gunakan interval dari UI
                        indicators: ["rsi", "macd", "bollinger", "sma", "stochastic", "adx", "atr", "ichimoku"],
                        trading_style: this.tradingStyle
                    };

                    if (this.customParams) {
                        requestData.periods = {};
                        Object.keys(this.params).forEach(key => {
                            requestData.periods[key] = this.params[key];
                        });
                    }

                    fetch('{{ route("panel.technical-analysis.indicators") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(requestData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.indicatorsData = data;
                        this.loadingIndicators = false;
                    })
                    .catch(error => {
                        console.error('Error loading indicators:', error);
                        this.loadingIndicators = false;
                    });
                },
                loadMarketEvents() {
                    if (!this.projectId) return;

                    this.loadingMarketEvents = true;

                    // Tambahkan parameter window_size dan thresholds jika digunakan
                    let url = `{{ url("panel/technical-analysis/market-events") }}/${this.projectId}?days=${this.days}&interval=${this.interval}`;

                    // Tambahkan parameter lanjutan jika advanced settings diaktifkan
                    if (this.showAdvanced) {
                        url += `&window_size=${this.windowSize}`;

                        // Buat objek thresholds jika nilai tidak default
                        const thresholds = {};
                        if (this.pumpThreshold !== 10) thresholds.pump = this.pumpThreshold;
                        if (this.dumpThreshold !== 10) thresholds.dump = this.dumpThreshold;
                        if (this.volatilityThreshold !== 15) thresholds.volatility = this.volatilityThreshold;

                        // Tambahkan ke URL jika ada thresholds yang berbeda dari default
                        if (Object.keys(thresholds).length > 0) {
                            url += `&thresholds=${encodeURIComponent(JSON.stringify(thresholds))}`;
                        }
                    }

                    fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        this.marketEvents = data;
                        this.loadingMarketEvents = false;
                    })
                    .catch(error => {
                        console.error('Error loading market events:', error);
                        this.loadingMarketEvents = false;
                    });
                },
                loadAlerts() {
                    if (!this.projectId) return;

                    this.loadingAlerts = true;

                    let url = `{{ url("panel/technical-analysis/alerts") }}/${this.projectId}?days=${this.days}&interval=${this.interval}&lookback=${this.lookback}&trading_style=${this.tradingStyle}`;

                    // Tambahkan periods jika menggunakan custom params
                    if (this.customParams) {
                        const periods = {};
                        Object.keys(this.params).forEach(key => {
                            periods[key] = this.params[key];
                        });

                        if (Object.keys(periods).length > 0) {
                            url += `&periods=${encodeURIComponent(JSON.stringify(periods))}`;
                        }
                    }

                    fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        this.alertsData = data;
                        this.loadingAlerts = false;
                    })
                    .catch(error => {
                        console.error('Error loading alerts:', error);
                        this.loadingAlerts = false;
                    });
                },
                loadPricePrediction() {
                    if (!this.projectId) return;

                    this.loadingPrediction = true;

                    // Sekarang menggunakan predictionDays dari UI
                    fetch(`{{ url("panel/technical-analysis/price-prediction") }}/${this.projectId}?days=${this.days}&interval=${this.interval}&prediction_days=${this.predictionDays}`)
                    .then(response => response.json())
                    .then(data => {
                        this.predictionData = data;
                        this.loadingPrediction = false;
                    })
                    .catch(error => {
                        console.error('Error loading price prediction:', error);
                        this.loadingPrediction = false;
                    });
                },
                setActiveTab(tab) {
                    this.activeTab = tab;

                    // Load data if not already loaded
                    if (tab === 'indicators' && !this.indicatorsData && !this.loadingIndicators) {
                        this.loadIndicators();
                    } else if (tab === 'events' && !this.marketEvents && !this.loadingMarketEvents) {
                        this.loadMarketEvents();
                    } else if (tab === 'alerts' && !this.alertsData && !this.loadingAlerts) {
                        this.loadAlerts();
                    } else if (tab === 'prediction' && !this.predictionData && !this.loadingPrediction) {
                        this.loadPricePrediction();
                    }
                }
            }));
        });
    </script>

    <!-- Project Selection dan Konfigurasi Periode -->
    <div class="clay-card p-6 mb-8" x-data="technicalAnalysis">
        <!-- Project Selection -->
        <div class="mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-project-diagram mr-2 text-primary"></i>
                Pilih Proyek
            </h2>

            <!-- Tambahkan dropdown interval di bawah dropdown days -->
            <div class="flex flex-col md:flex-row gap-4">
                <div class="md:w-3/4">
                    <label for="project_id" class="block mb-1 font-medium">Proyek Cryptocurrency:</label>
                    <select id="project_id" x-model="projectId" class="clay-select w-full">
                        <option value="" hidden>-- Pilih Proyek --</option>

                        @php
                            $currentGroup = null;
                        @endphp

                        @foreach($topProjects as $project)
                            @if($project->group !== $currentGroup)
                                @if($currentGroup !== null)
                                    </optgroup>
                                @endif
                                <optgroup label="{{ $project->group }}">
                                @php
                                    $currentGroup = $project->group;
                                @endphp
                            @endif
                            <option value="{{ $project->id }}">{{ $project->name }} ({{ $project->symbol }})</option>
                        @endforeach

                        @if($currentGroup !== null)
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="md:w-1/4">
                    <label for="days" class="block mb-1 font-medium">Jumlah Hari Data:</label>
                    <select id="days" x-model="days" class="clay-select w-full">
                        <option value="7">7 hari</option>
                        <option value="14">14 hari</option>
                        <option value="30" selected>30 hari</option>
                        <option value="60">60 hari</option>
                        <option value="90">90 hari</option>
                        <option value="180">180 hari</option>
                        <option value="365">365 hari</option>
                    </select>
                </div>
            </div>

            <!-- Tambahkan baris baru untuk Interval dan Parameter Lanjutan -->
            <div class="flex flex-col md:flex-row gap-4 mt-4">
                <div class="md:w-1/3">
                    <label for="interval" class="block mb-1 font-medium">Interval Data:</label>
                    <select id="interval" x-model="interval" class="clay-select w-full">
                        <option value="1d" selected>Harian (1d)</option>
                        <option value="4h">4 Jam (4h)</option>
                        <option value="1h">1 Jam (1h)</option>
                        <option value="15m">15 Menit (15m)</option>
                    </select>
                </div>
                <div class="md:w-1/3">
                    <label for="lookback" class="block mb-1 font-medium">Periode Lookback Alert:</label>
                    <select id="lookback" x-model="lookback" class="clay-select w-full">
                        <option value="3">3 periode</option>
                        <option value="5" selected>5 periode</option>
                        <option value="10">10 periode</option>
                        <option value="15">15 periode</option>
                    </select>
                </div>
                <div class="md:w-1/3">
                    <label for="prediction_days" class="block mb-1 font-medium">Hari Prediksi:</label>
                    <select id="prediction_days" x-model="predictionDays" class="clay-select w-full">
                        <option value="3">3 hari</option>
                        <option value="7" selected>7 hari</option>
                        <option value="14">14 hari</option>
                        <option value="30">30 hari</option>
                    </select>
                </div>
            </div>

            <!-- Opsional: Tambahkan bagian Advanced Settings yang bisa ditoggle -->
            <div class="mt-4">
                <div class="clay-checkbox-container">
                    <input type="checkbox" id="show_advanced" x-model="showAdvanced" class="clay-checkbox">
                    <label for="show_advanced">Tampilkan Parameter Lanjutan</label>
                </div>

                <div x-show="showAdvanced" class="clay-card bg-secondary/5 p-4 mt-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="window_size" class="block mb-1 font-medium">Window Size (Market Events):</label>
                            <input type="number" id="window_size" x-model="windowSize" min="5" max="30" class="clay-input w-full">
                            <p class="text-xs text-gray-500 mt-1">Ukuran jendela untuk mendeteksi perubahan signifikan</p>
                        </div>
                        <div>
                            <label for="pump_threshold" class="block mb-1 font-medium">Pump Threshold (%):</label>
                            <input type="number" id="pump_threshold" x-model="pumpThreshold" min="1" max="50" step="0.5" class="clay-input w-full">
                            <p class="text-xs text-gray-500 mt-1">Persentase kenaikan minimum untuk dianggap pump</p>
                        </div>
                        <div>
                            <label for="dump_threshold" class="block mb-1 font-medium">Dump Threshold (%):</label>
                            <input type="number" id="dump_threshold" x-model="dumpThreshold" min="1" max="50" step="0.5" class="clay-input w-full">
                            <p class="text-xs text-gray-500 mt-1">Persentase penurunan minimum untuk dianggap dump</p>
                        </div>
                        <div>
                            <label for="volatility_threshold" class="block mb-1 font-medium">Volatility Threshold:</label>
                            <input type="number" id="volatility_threshold" x-model="volatilityThreshold" min="1" max="50" step="0.5" class="clay-input w-full">
                            <p class="text-xs text-gray-500 mt-1">Tingkat volatilitas minimum untuk dianggap volatil</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trading Style dan Risk Tolerance -->
        <div class="mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-sliders-h mr-2 text-secondary"></i>
                Gaya Trading & Risiko
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="trading_style" class="block mb-1 font-medium">Gaya Trading:</label>
                    <select id="trading_style" x-model="tradingStyle" @change="applyPreset()" class="clay-select w-full">
                        <option value="short_term">Short-Term Trading</option>
                        <option value="standard" selected>Standard Trading</option>
                        <option value="long_term">Long-Term Trading</option>
                    </select>

                    <div class="mt-2 text-sm text-gray-600">
                        <template x-if="tradingStyle === 'short_term'">
                            <span>Periode indikator yang lebih pendek untuk trading jangka pendek, lebih sensitif terhadap pergerakan harga.</span>
                        </template>
                        <template x-if="tradingStyle === 'standard'">
                            <span>Periode standar untuk analisis teknikal yang umum digunakan di industri.</span>
                        </template>
                        <template x-if="tradingStyle === 'long_term'">
                            <span>Periode yang lebih panjang untuk perspektif jangka panjang, mengurangi noise dan fokus pada tren utama.</span>
                        </template>
                    </div>
                </div>

                <div>
                    <label for="risk_tolerance" class="block mb-1 font-medium">Toleransi Risiko:</label>
                    <select id="risk_tolerance" x-model="riskTolerance" class="clay-select w-full">
                        <option value="low">Rendah</option>
                        <option value="medium" selected>Sedang</option>
                        <option value="high">Tinggi</option>
                    </select>

                    <div class="mt-2 text-sm text-gray-600">
                        <template x-if="riskTolerance === 'low'">
                            <span>Mengutamakan sinyal yang lebih konservatif dengan konfirmasi lebih kuat.</span>
                        </template>
                        <template x-if="riskTolerance === 'medium'">
                            <span>Keseimbangan antara sinyal konservatif dan agresif.</span>
                        </template>
                        <template x-if="riskTolerance === 'high'">
                            <span>Mengutamakan sinyal lebih awal dengan konfirmasi lebih rendah.</span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Periode Indikator -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fas fa-chart-bar mr-2 text-info"></i>
                    Periode Indikator Teknikal
                </h2>

                <div class="clay-checkbox-container">
                    <input type="checkbox" id="custom_params" x-model="customParams" class="clay-checkbox">
                    <label for="custom_params">Kustomisasi Parameter</label>
                </div>
            </div>

            <div x-show="customParams" class="clay-card bg-info/10 p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- RSI -->
                    <div>
                        <label for="rsi_period" class="block mb-1 font-medium">RSI Period:</label>
                        <input type="number" id="rsi_period" x-model="params.rsi_period" min="2" max="100" class="clay-input w-full">
                    </div>

                    <!-- MACD -->
                    <div>
                        <label for="macd_fast" class="block mb-1 font-medium">MACD Fast:</label>
                        <input type="number" id="macd_fast" x-model="params.macd_fast" min="2" max="100" class="clay-input w-full">
                    </div>

                    <div>
                        <label for="macd_slow" class="block mb-1 font-medium">MACD Slow:</label>
                        <input type="number" id="macd_slow" x-model="params.macd_slow" min="2" max="100" class="clay-input w-full">
                    </div>

                    <div>
                        <label for="macd_signal" class="block mb-1 font-medium">MACD Signal:</label>
                        <input type="number" id="macd_signal" x-model="params.macd_signal" min="2" max="100" class="clay-input w-full">
                    </div>

                    <!-- Bollinger Bands -->
                    <div>
                        <label for="bb_period" class="block mb-1 font-medium">Bollinger Bands Period:</label>
                        <input type="number" id="bb_period" x-model="params.bb_period" min="2" max="100" class="clay-input w-full">
                    </div>

                    <!-- Stochastic -->
                    <div>
                        <label for="stoch_k" class="block mb-1 font-medium">Stochastic %K:</label>
                        <input type="number" id="stoch_k" x-model="params.stoch_k" min="1" max="100" class="clay-input w-full">
                    </div>

                    <div>
                        <label for="stoch_d" class="block mb-1 font-medium">Stochastic %D:</label>
                        <input type="number" id="stoch_d" x-model="params.stoch_d" min="1" max="100" class="clay-input w-full">
                    </div>

                    <!-- Moving Averages -->
                    <div>
                        <label for="ma_short" class="block mb-1 font-medium">MA Short:</label>
                        <input type="number" id="ma_short" x-model="params.ma_short" min="2" max="100" class="clay-input w-full">
                    </div>

                    <div>
                        <label for="ma_medium" class="block mb-1 font-medium">MA Medium:</label>
                        <input type="number" id="ma_medium" x-model="params.ma_medium" min="10" max="200" class="clay-input w-full">
                    </div>

                    <div>
                        <label for="ma_long" class="block mb-1 font-medium">MA Long:</label>
                        <input type="number" id="ma_long" x-model="params.ma_long" min="20" max="500" class="clay-input w-full">
                    </div>
                </div>
            </div>

            <div x-show="!customParams" class="clay-card bg-secondary/10 p-4">
                <p class="text-center">Menggunakan preset <span class="font-bold" x-text="tradingStyle === 'short_term' ? 'Short-Term Trading' : (tradingStyle === 'standard' ? 'Standard Trading' : 'Long-Term Trading')"></span></p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="text-sm space-y-1">
                        <p><span class="font-medium">RSI:</span> <span x-text="params.rsi_period"></span> periode</p>
                        <p><span class="font-medium">MACD:</span> <span x-text="params.macd_fast + '-' + params.macd_slow + '-' + params.macd_signal"></span></p>
                    </div>
                    <div class="text-sm space-y-1">
                        <p><span class="font-medium">Bollinger:</span> <span x-text="params.bb_period"></span> periode</p>
                        <p><span class="font-medium">Stochastic:</span> <span x-text="params.stoch_k + '/' + params.stoch_d"></span></p>
                    </div>
                    <div class="text-sm space-y-1">
                        <p><span class="font-medium">MA:</span> <span x-text="params.ma_short + '-' + params.ma_medium + '-' + params.ma_long"></span></p>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <button @click="analyze()" class="clay-button clay-button-info px-8 py-3" :disabled="loading || !projectId">
                    <template x-if="loading">
                        <span><i class="fas fa-spinner fa-spin mr-2"></i> Menganalisis...</span>
                    </template>
                    <template x-if="!loading">
                        <span><i class="fas fa-chart-line mr-2"></i> Analisis Proyek</span>
                    </template>
                </button>
            </div>
        </div>

        <!-- Hasil Analisis -->
        <div x-show="results" class="mt-8">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-success"></i>
                Hasil Analisis <span x-text="results?.market_regime ? '- ' + results.market_regime.replaceAll('_', ' ').toUpperCase() : ''"></span>
            </h2>

            <!-- Tab Navigation -->
            <div class="mb-6 flex border-b border-gray-200 overflow-x-auto">
                <button
                    @click="setActiveTab('signals')"
                    :class="activeTab === 'signals' ? 'border-b-2 border-primary text-primary font-bold' : 'text-gray-600 hover:text-primary'"
                    class="px-4 py-2 focus:outline-none">
                    <i class="fas fa-signal mr-1"></i> Sinyal Trading
                </button>
                <button
                    @click="setActiveTab('indicators')"
                    :class="activeTab === 'indicators' ? 'border-b-2 border-primary text-primary font-bold' : 'text-gray-600 hover:text-primary'"
                    class="px-4 py-2 focus:outline-none">
                    <i class="fas fa-chart-bar mr-1"></i> Indikator Teknikal
                </button>
                <button
                    @click="setActiveTab('events')"
                    :class="activeTab === 'events' ? 'border-b-2 border-primary text-primary font-bold' : 'text-gray-600 hover:text-primary'"
                    class="px-4 py-2 focus:outline-none">
                    <i class="fas fa-exclamation-circle mr-1"></i> Market Events
                </button>
                <button
                    @click="setActiveTab('alerts')"
                    :class="activeTab === 'alerts' ? 'border-b-2 border-primary text-primary font-bold' : 'text-gray-600 hover:text-primary'"
                    class="px-4 py-2 focus:outline-none">
                    <i class="fas fa-bell mr-1"></i> Alerts
                </button>
                <button
                    @click="setActiveTab('prediction')"
                    :class="activeTab === 'prediction' ? 'border-b-2 border-primary text-primary font-bold' : 'text-gray-600 hover:text-primary'"
                    class="px-4 py-2 focus:outline-none">
                    <i class="fas fa-crystal-ball mr-1"></i> Prediksi Harga
                </button>
            </div>

            <!-- Tab Content - Signals -->
            <div x-show="activeTab === 'signals'">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Trading Signal -->
                    <div :class="'clay-card ' + (
                        results?.action == 'buy' ? 'bg-success/10' :
                        (results?.action == 'sell' ? 'bg-danger/10' : 'bg-warning/10')
                    ) + ' p-4'">
                        <div class="text-center">
                            <div class="mb-2">
                                <template x-if="results?.action == 'buy'">
                                    <i class="fas fa-arrow-circle-up text-6xl text-success"></i>
                                </template>
                                <template x-if="results?.action == 'sell'">
                                    <i class="fas fa-arrow-circle-down text-6xl text-danger"></i>
                                </template>
                                <template x-if="results?.action == 'hold'">
                                    <i class="fas fa-minus-circle text-6xl text-warning"></i>
                                </template>
                                <template x-if="results?.action != 'buy' && results?.action != 'sell' && results?.action != 'hold'">
                                    <i class="fas fa-question-circle text-6xl text-info"></i>
                                </template>
                            </div>
                            <div class="font-bold text-2xl capitalize mb-2" x-text="results?.action || ''"></div>
                            <div class="text-sm mb-2">
                                Kepercayaan:
                                <span class="font-medium" x-text="results?.confidence ? Math.round(results.confidence * 100) + '%' : '0%'"></span>
                                <template x-if="results?.strong_signal">
                                    <span class="clay-badge clay-badge-success ml-2">Sinyal Kuat</span>
                                </template>
                            </div>

                            <div class="mt-3">
                                <span class="text-sm">Arah Tren: </span>
                                <span class="font-medium capitalize" x-text="results?.trend_direction || 'neutral'"></span>
                            </div>

                            <template x-if="results?.target_price && results?.target_price > 0">
                                <div class="mt-3">
                                    <div class="text-sm font-medium mb-1">Target Harga:</div>
                                    <div :class="'clay-badge ' + (results?.action == 'buy' ? 'clay-badge-success' : 'clay-badge-warning') + ' py-1 px-2 mb-1'">
                                        Target 1: $<span x-text="results?.target_price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </div>
                                    <template x-if="results?.target_2 && results?.target_2 > 0">
                                        <div :class="'clay-badge ' + (results?.action == 'buy' ? 'clay-badge-success' : 'clay-badge-warning') + ' py-1 px-2'">
                                            Target 2: $<span x-text="results?.target_2.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="results?.support_1 || results?.support_2">
                                <div class="mt-3">
                                    <div class="text-sm font-medium mb-1">Support Levels:</div>
                                    <template x-if="results?.support_1">
                                        <div class="clay-badge clay-badge-info py-1 px-2 mb-1">
                                            Support 1: $<span x-text="results?.support_1.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </div>
                                    </template>
                                    <template x-if="results?.support_2">
                                        <div class="clay-badge clay-badge-info py-1 px-2">
                                            Support 2: $<span x-text="results?.support_2.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <template x-if="results?.personalized_message">
                            <div class="mt-4 text-sm p-3 clay-card">
                                <p x-text="results?.personalized_message"></p>
                            </div>
                        </template>
                    </div>

                    <!-- Evidence / Indikasi -->
                    <div class="clay-card bg-info/5 p-4">
                        <h3 class="font-bold mb-3">Indikasi Sinyal:</h3>
                        <template x-if="results?.evidence && results?.evidence.length > 0">
                            <ul class="space-y-2 text-sm">
                                <template x-for="(evidence, index) in results?.evidence" :key="index">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-info mt-1 mr-2"></i>
                                        <span x-text="evidence"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <template x-if="results?.reversal_signals && results?.reversal_signals.length > 0">
                            <div class="mt-4">
                                <h4 class="font-medium mb-2">Sinyal Pembalikan:</h4>
                                <ul class="space-y-2 text-sm">
                                    <template x-for="(signal, index) in results?.reversal_signals" :key="index">
                                        <li class="flex items-start">
                                            <i class="fas fa-exchange-alt text-warning mt-1 mr-2"></i>
                                            <span x-text="signal"></span>
                                        </li>
                                    </template>
                                </ul>
                                <div class="mt-2">
                                    <span class="text-sm">Probabilitas pembalikan: </span>
                                    <span class="font-medium" x-text="results?.reversal_probability ? Math.round(results.reversal_probability * 100) + '%' : '0%'"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="(!results?.evidence || results?.evidence.length === 0) && (!results?.reversal_signals || results?.reversal_signals.length === 0)">
                            <p class="text-center text-gray-500">Tidak ada indikasi yang tersedia</p>
                        </template>

                        <template x-if="results?.buy_score !== undefined || results?.sell_score !== undefined">
                            <div class="mt-4 p-3 clay-card">
                                <div class="flex justify-between mb-2">
                                    <span class="text-sm font-medium">Buy Score:</span>
                                    <span class="text-sm" x-text="results?.buy_score ? Math.round(results.buy_score * 100) + '%' : '0%'"></span>
                                </div>
                                <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-success" :style="`width: ${results?.buy_score ? Math.round(results.buy_score * 100) : 0}%`"></div>
                                </div>

                                <div class="flex justify-between mt-3 mb-2">
                                    <span class="text-sm font-medium">Sell Score:</span>
                                    <span class="text-sm" x-text="results?.sell_score ? Math.round(results.sell_score * 100) + '%' : '0%'"></span>
                                </div>
                                <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-danger" :style="`width: ${results?.sell_score ? Math.round(results.sell_score * 100) : 0}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Indikator Teknikal -->
                    <div class="clay-card bg-primary/5 p-4">
                        <h3 class="font-bold mb-3">Indikator Teknikal:</h3>
                        <template x-if="results?.indicators && Object.keys(results?.indicators).length > 0">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between p-2 bg-primary/10 rounded mb-3">
                                    <span class="font-medium">INDIKATOR</span>
                                    <span class="font-medium">NILAI</span>
                                </div>

                                <!-- RSI -->
                                <template x-if="results?.indicators?.rsi !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">RSI</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.rsi > 70 ? 'text-danger' : (results?.indicators?.rsi < 30 ? 'text-success' : '')"
                                            x-text="results?.indicators?.rsi !== null && results?.indicators?.rsi !== undefined ?
                                                    results?.indicators?.rsi.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <!-- MACD -->
                                <template x-if="results?.indicators?.macd !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">MACD</span>
                                        <span class="font-medium"
                                            x-text="results?.indicators?.macd !== null && results?.indicators?.macd !== undefined ?
                                                    results?.indicators?.macd.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <template x-if="results?.indicators?.macd_signal !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">MACD Signal</span>
                                        <span class="font-medium"
                                            x-text="results?.indicators?.macd_signal !== null && results?.indicators?.macd_signal !== undefined ?
                                                    results?.indicators?.macd_signal.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <template x-if="results?.indicators?.macd_histogram !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">MACD Histogram</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.macd_histogram > 0 ? 'text-success' : 'text-danger'"
                                            x-text="results?.indicators?.macd_histogram !== null && results?.indicators?.macd_histogram !== undefined ?
                                                    results?.indicators?.macd_histogram.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <!-- Bollinger -->
                                <template x-if="results?.indicators?.bollinger_percent !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">Bollinger %B</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.bollinger_percent > 1 ? 'text-danger' : (results?.indicators?.bollinger_percent < 0 ? 'text-success' : '')"
                                            x-text="results?.indicators?.bollinger_percent !== null && results?.indicators?.bollinger_percent !== undefined ?
                                                    results?.indicators?.bollinger_percent.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <!-- Stochastic -->
                                <template x-if="results?.indicators?.stochastic_k !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">Stochastic %K</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.stochastic_k > 80 ? 'text-danger' : (results?.indicators?.stochastic_k < 20 ? 'text-success' : '')"
                                            x-text="results?.indicators?.stochastic_k !== null && results?.indicators?.stochastic_k !== undefined ?
                                                    results?.indicators?.stochastic_k.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <template x-if="results?.indicators?.stochastic_d !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">Stochastic %D</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.stochastic_d > 80 ? 'text-danger' : (results?.indicators?.stochastic_d < 20 ? 'text-success' : '')"
                                            x-text="results?.indicators?.stochastic_d !== null && results?.indicators?.stochastic_d !== undefined ?
                                                    results?.indicators?.stochastic_d.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <!-- ADX -->
                                <template x-if="results?.indicators?.adx !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">ADX</span>
                                        <span class="font-medium"
                                            :class="results?.indicators?.adx > 25 ? 'text-success' : ''"
                                            x-text="results?.indicators?.adx !== null && results?.indicators?.adx !== undefined ?
                                                    results?.indicators?.adx.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <template x-if="results?.indicators?.plus_di !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">+DI</span>
                                        <span class="font-medium"
                                            x-text="results?.indicators?.plus_di !== null && results?.indicators?.plus_di !== undefined ?
                                                    results?.indicators?.plus_di.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>

                                <template x-if="results?.indicators?.minus_di !== undefined">
                                    <div class="flex justify-between p-2 border-b border-gray-200">
                                        <span class="uppercase">-DI</span>
                                        <span class="font-medium"
                                            x-text="results?.indicators?.minus_di !== null && results?.indicators?.minus_di !== undefined ?
                                                    results?.indicators?.minus_di.toFixed(2) : 'N/A'"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!results?.indicators || Object.keys(results?.indicators || {}).length === 0">
                            <p class="text-center text-gray-500">Tidak ada indikator yang tersedia</p>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tab Content - Indicators -->
            <div x-show="activeTab === 'indicators'">
                <div x-show="loadingIndicators" class="text-center py-6">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <p class="mt-2">Memuat indikator teknikal...</p>
                </div>

                <div x-show="!loadingIndicators && indicatorsData">
                    <div class="clay-card p-4 mb-4 bg-info/5">
                        <h3 class="font-bold mb-3">Detail Indikator Teknikal</h3>

                        <!-- Trend Indicators -->
                        <template x-if="indicatorsData?.indicators?.trend">
                            <div class="clay-card p-3 bg-primary/5 mb-4">
                                <h4 class="font-bold mb-2 text-primary">Indikator Tren</h4>

                                <!-- Moving Averages -->
                                <template x-if="indicatorsData.indicators.trend.moving_averages">
                                    <div class="mb-4">
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">Moving Averages:</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Indicator</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(value, key) in indicatorsData.indicators.trend.moving_averages" :key="key">
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm uppercase" x-text="key"></td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="typeof value === 'number' ? value.toFixed(2) : value"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>

                                <!-- MACD -->
                                <template x-if="indicatorsData?.indicators?.trend?.macd">
                                    <div class="mb-4">
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">MACD:</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">MACD Line</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        x-text="indicatorsData.indicators.trend.macd.value !== null && indicatorsData.indicators.trend.macd.value !== undefined ?
                                                                indicatorsData.indicators.trend.macd.value.toFixed(2) : 'N/A'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Signal Line</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        x-text="indicatorsData.indicators.trend.macd.signal !== null && indicatorsData.indicators.trend.macd.signal !== undefined ?
                                                                indicatorsData.indicators.trend.macd.signal.toFixed(2) : 'N/A'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Histogram</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        :class="indicatorsData.indicators.trend.macd.histogram > 0 ? 'text-success' :
                                                            (indicatorsData.indicators.trend.macd.histogram < 0 ? 'text-danger' : '')"
                                                        x-text="indicatorsData.indicators.trend.macd.histogram !== null && indicatorsData.indicators.trend.macd.histogram !== undefined ?
                                                                indicatorsData.indicators.trend.macd.histogram.toFixed(2) : 'N/A'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Signal Type</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize"
                                                        x-text="indicatorsData.indicators.trend.macd.signal_type || 'N/A'"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>

                                <!-- ADX -->
                                <template x-if="indicatorsData.indicators.trend.adx">
                                    <div>
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">ADX (Trend Strength):</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">ADX</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.trend.adx.value.toFixed(2)"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">+DI</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.trend.adx.plus_di.toFixed(2)"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">-DI</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.trend.adx.minus_di.toFixed(2)"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Trend Strength</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.trend.adx.trend_strength"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Direction</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize"
                                                         :class="indicatorsData.indicators.trend.adx.trend_direction === 'bullish' ? 'text-success' : 'text-danger'"
                                                         x-text="indicatorsData.indicators.trend.adx.trend_direction"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Momentum Indicators -->
                        <template x-if="indicatorsData?.indicators?.momentum">
                            <div class="clay-card p-3 bg-success/5 mb-4">
                                <h4 class="font-bold mb-2 text-success">Indikator Momentum</h4>

                                <!-- RSI -->
                                <template x-if="indicatorsData?.indicators?.momentum?.rsi">
                                    <div class="mb-4">
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">RSI:</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Value</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        :class="indicatorsData.indicators.momentum.rsi.value > 70 ? 'text-danger' :
                                                            (indicatorsData.indicators.momentum.rsi.value < 30 ? 'text-success' : '')"
                                                        x-text="indicatorsData.indicators.momentum.rsi.value !== null && indicatorsData.indicators.momentum.rsi.value !== undefined ?
                                                                indicatorsData.indicators.momentum.rsi.value.toFixed(2) : 'N/A'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Signal</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize"
                                                        x-text="indicatorsData.indicators.momentum.rsi.signal || 'N/A'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Period</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        x-text="indicatorsData.indicators.momentum.rsi.period || 'N/A'"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>

                                <!-- Stochastic -->
                                <template x-if="indicatorsData.indicators.momentum.stochastic">
                                    <div class="mb-4">
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">Stochastic Oscillator:</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">%K</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        :class="indicatorsData.indicators.momentum.stochastic.k > 80 ? 'text-danger' :
                                                        (indicatorsData.indicators.momentum.stochastic.k < 20 ? 'text-success' : '')"
                                                        x-text="indicatorsData.indicators.momentum.stochastic.k.toFixed(2)"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">%D</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        :class="indicatorsData.indicators.momentum.stochastic.d > 80 ? 'text-danger' :
                                                        (indicatorsData.indicators.momentum.stochastic.d < 20 ? 'text-success' : '')"
                                                        x-text="indicatorsData.indicators.momentum.stochastic.d.toFixed(2)"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Signal</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.momentum.stochastic.signal"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>

                                <!-- Other Momentum Indicators -->
                                <template x-if="indicatorsData.indicators.momentum.roc">
                                    <div>
                                        <h5 class="font-medium text-sm mb-2 border-b pb-1">ROC (Rate of Change):</h5>
                                        <table class="min-w-full bg-white">
                                            <thead>
                                                <tr class="bg-gray-100">
                                                    <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                    <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Value</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium"
                                                        :class="indicatorsData.indicators.momentum.roc.value > 0 ? 'text-success' : 'text-danger'"
                                                        x-text="indicatorsData.indicators.momentum.roc.value.toFixed(2) + '%'"></td>
                                                </tr>
                                                <tr class="border-b">
                                                    <td class="py-1 px-2 text-sm">Signal</td>
                                                    <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.momentum.roc.signal"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Volatility Indicators -->
                            <template x-if="indicatorsData?.indicators?.volatility">
                                <div class="clay-card p-3 bg-warning/5">
                                    <h4 class="font-bold mb-2 text-warning">Indikator Volatilitas</h4>

                                    <!-- Bollinger Bands -->
                                    <template x-if="indicatorsData?.indicators?.volatility?.bollinger">
                                        <div class="mb-4">
                                            <h5 class="font-medium text-sm mb-2 border-b pb-1">Bollinger Bands:</h5>
                                            <table class="min-w-full bg-white">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                        <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Upper Band</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.upper !== null &&
                                                                    indicatorsData.indicators.volatility.bollinger.upper !== undefined ?
                                                                    indicatorsData.indicators.volatility.bollinger.upper.toFixed(2) : 'N/A'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Middle Band</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.middle !== null &&
                                                                    indicatorsData.indicators.volatility.bollinger.middle !== undefined ?
                                                                    indicatorsData.indicators.volatility.bollinger.middle.toFixed(2) : 'N/A'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Lower Band</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.lower !== null &&
                                                                    indicatorsData.indicators.volatility.bollinger.lower !== undefined ?
                                                                    indicatorsData.indicators.volatility.bollinger.lower.toFixed(2) : 'N/A'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">%B</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            :class="indicatorsData.indicators.volatility.bollinger.percent_b > 1 ? 'text-danger' :
                                                                (indicatorsData.indicators.volatility.bollinger.percent_b < 0 ? 'text-success' : '')"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.percent_b !== null &&
                                                                    indicatorsData.indicators.volatility.bollinger.percent_b !== undefined ?
                                                                    indicatorsData.indicators.volatility.bollinger.percent_b.toFixed(2) : 'N/A'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Bandwidth</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.bandwidth !== null &&
                                                                    indicatorsData.indicators.volatility.bollinger.bandwidth !== undefined ?
                                                                    indicatorsData.indicators.volatility.bollinger.bandwidth.toFixed(4) : 'N/A'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Signal</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize"
                                                            x-text="indicatorsData.indicators.volatility.bollinger.signal || 'N/A'"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>

                                    <!-- ATR -->
                                    <template x-if="indicatorsData.indicators.volatility.atr">
                                        <div>
                                            <h5 class="font-medium text-sm mb-2 border-b pb-1">ATR (Average True Range):</h5>
                                            <table class="min-w-full bg-white">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                        <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Value</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.volatility.atr.value.toFixed(4)"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Percent</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.volatility.atr.percent.toFixed(2) + '%'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Volatility</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.volatility.atr.volatility"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Volume or Ichimoku or Market Condition -->
                            <template x-if="indicatorsData?.indicators?.volume || indicatorsData?.indicators?.ichimoku || indicatorsData?.indicators?.market_condition">
                                <div class="clay-card p-3 bg-secondary/5">
                                    <template x-if="indicatorsData?.indicators?.volume">
                                        <div class="mb-4">
                                            <h4 class="font-bold mb-2 text-secondary">Indikator Volume</h4>
                                            <table class="min-w-full bg-white">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                        <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Volume</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.volume.volume.value.toLocaleString()"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Volume Ratio</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.volume.volume.ratio.toFixed(2) + 'x'"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Signal</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.volume.volume.signal"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>

                                    <template x-if="indicatorsData?.indicators?.ichimoku">
                                        <div class="mb-4">
                                            <h4 class="font-bold mb-2 text-secondary">Ichimoku Cloud</h4>
                                            <table class="min-w-full bg-white">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                        <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Tenkan</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.ichimoku.tenkan.toFixed(2)"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Kijun</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.ichimoku.kijun.toFixed(2)"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Signal</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.ichimoku.signal"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Cloud Color</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium"
                                                            :class="indicatorsData.indicators.ichimoku.cloud_green ? 'text-success' :
                                                                   (indicatorsData.indicators.ichimoku.cloud_red ? 'text-danger' : '')"
                                                            x-text="indicatorsData.indicators.ichimoku.cloud_green ? 'GREEN' :
                                                                   (indicatorsData.indicators.ichimoku.cloud_red ? 'RED' : 'NEUTRAL')"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>

                                    <template x-if="indicatorsData?.indicators?.market_condition">
                                        <div>
                                            <h4 class="font-bold mb-2 text-secondary">Kondisi Pasar</h4>
                                            <table class="min-w-full bg-white">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="py-1 px-2 text-left text-xs font-medium text-gray-600 uppercase">Parameter</th>
                                                        <th class="py-1 px-2 text-right text-xs font-medium text-gray-600 uppercase">Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Regime</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize" x-text="indicatorsData.indicators.market_condition.market_regime.replace('_', ' ')"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Trend Strength</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="indicatorsData.indicators.market_condition.trend_strength.toFixed(2)"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Trend Direction</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium capitalize"
                                                            :class="indicatorsData.indicators.market_condition.trend_direction === 'bullish' ? 'text-success' :
                                                                   (indicatorsData.indicators.market_condition.trend_direction === 'bearish' ? 'text-danger' : '')"
                                                            x-text="indicatorsData.indicators.market_condition.trend_direction"></td>
                                                    </tr>
                                                    <tr class="border-b">
                                                        <td class="py-1 px-2 text-sm">Volatility</td>
                                                        <td class="py-1 px-2 text-sm text-right font-medium" x-text="(indicatorsData.indicators.market_condition.volatility * 100).toFixed(2) + '%'"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content - Market Events -->
            <div x-show="activeTab === 'events'">
                <div x-show="loadingMarketEvents" class="text-center py-6">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <p class="mt-2">Memuat peristiwa pasar...</p>
                </div>

                <div x-show="!loadingMarketEvents && marketEvents">
                    <div class="clay-card p-4 mb-4 bg-primary/5">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                            <h3 class="font-bold mb-3 md:mb-0">Peristiwa Pasar Terbaru</h3>
                            <div>
                                <span class="clay-badge clay-badge-info px-3 py-1">
                                    Regime: <span class="font-bold capitalize" x-text="marketEvents?.market_regime?.replace('_', ' ')"></span>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Latest Event -->
                            <div>
                                <h4 class="font-medium mb-2">Peristiwa Terbaru:</h4>
                                <div class="p-3 clay-card" :class="{
                                    'bg-success/10': marketEvents?.latest_event === 'pump',
                                    'bg-danger/10': marketEvents?.latest_event === 'dump',
                                    'bg-warning/10': marketEvents?.latest_event === 'high_volatility',
                                    'bg-info/10': marketEvents?.latest_event === 'volume_spike',
                                    'bg-secondary/10': marketEvents?.latest_event === 'normal'
                                }">
                                    <div class="flex items-center">
                                        <div class="mr-3">
                                            <template x-if="marketEvents?.latest_event === 'pump'">
                                                <i class="fas fa-arrow-circle-up text-3xl text-success"></i>
                                            </template>
                                            <template x-if="marketEvents?.latest_event === 'dump'">
                                                <i class="fas fa-arrow-circle-down text-3xl text-danger"></i>
                                            </template>
                                            <template x-if="marketEvents?.latest_event === 'high_volatility'">
                                                <i class="fas fa-bolt text-3xl text-warning"></i>
                                            </template>
                                            <template x-if="marketEvents?.latest_event === 'volume_spike'">
                                                <i class="fas fa-chart-bar text-3xl text-info"></i>
                                            </template>
                                            <template x-if="marketEvents?.latest_event === 'normal'">
                                                <i class="fas fa-minus-circle text-3xl text-secondary"></i>
                                            </template>
                                        </div>
                                        <div>
                                            <div class="font-bold text-lg capitalize" x-text="marketEvents?.latest_event?.replace('_', ' ')"></div>
                                            <div class="text-sm text-gray-600" x-text="'Harga Saat Ini: $' + marketEvents?.close_price?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Event Counts -->
                            <div>
                                <h4 class="font-medium mb-2">Jumlah Peristiwa:</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="p-2 clay-card bg-success/10 text-center">
                                        <div class="font-bold text-lg text-success" x-text="marketEvents?.event_counts?.pump || 0"></div>
                                        <div class="text-xs text-gray-600">Pump</div>
                                    </div>
                                    <div class="p-2 clay-card bg-danger/10 text-center">
                                        <div class="font-bold text-lg text-danger" x-text="marketEvents?.event_counts?.dump || 0"></div>
                                        <div class="text-xs text-gray-600">Dump</div>
                                    </div>
                                    <div class="p-2 clay-card bg-warning/10 text-center">
                                        <div class="font-bold text-lg text-warning" x-text="marketEvents?.event_counts?.high_volatility || 0"></div>
                                        <div class="text-xs text-gray-600">Volatilitas Tinggi</div>
                                    </div>
                                    <div class="p-2 clay-card bg-info/10 text-center">
                                        <div class="font-bold text-lg text-info" x-text="marketEvents?.event_counts?.volume_spike || 0"></div>
                                        <div class="text-xs text-gray-600">Lonjakan Volume</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Events -->
                        <template x-if="marketEvents?.recent_events && Object.keys(marketEvents.recent_events).length > 0">
                            <div class="mt-6">
                                <h4 class="font-medium mb-2">Peristiwa Terbaru:</h4>
                                <div class="clay-card p-3">
                                    <div class="space-y-2">
                                        <template x-for="(events, type) in marketEvents.recent_events" :key="type">
                                            <template x-if="events && events.length > 0">
                                                <div>
                                                    <div class="font-medium capitalize mb-1" x-text="type.replace('_', ' ')"></div>
                                                    <ul class="pl-5 text-sm space-y-1">
                                                        <template x-for="(event, index) in events.slice(0, 3)" :key="index">
                                                            <li class="text-gray-600" x-text="event"></li>
                                                        </template>
                                                    </ul>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tab Content - Alerts -->
            <div x-show="activeTab === 'alerts'">
                <div x-show="loadingAlerts" class="text-center py-6">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <p class="mt-2">Memuat alert teknikal...</p>
                </div>

                <div x-show="!loadingAlerts && alertsData">
                    <div class="clay-card p-4 mb-4 bg-warning/5">
                        <h3 class="font-bold mb-3">Alert Teknikal</h3>

                        <div class="p-3 clay-card bg-white mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <div class="font-medium">Market Regime:</div>
                                <div class="clay-badge clay-badge-info px-3 py-1 capitalize">
                                    <span x-text="alertsData?.market_regime?.replace('_', ' ')"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="font-medium">Periode:</div>
                                <div class="text-gray-600" x-text="alertsData?.period"></div>
                            </div>
                        </div>

                        <template x-if="alertsData?.alerts && alertsData.alerts.length > 0">
                            <div class="space-y-3">
                                <template x-for="(alert, index) in alertsData.alerts" :key="index">
                                    <div class="p-3 clay-card" :class="{
                                        'bg-success/10': alert.signal === 'buy',
                                        'bg-danger/10': alert.signal === 'sell',
                                        'bg-warning/10': alert.signal === 'neutral'
                                    }">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-medium" x-text="alert.message"></div>
                                                <div class="text-xs text-gray-600" x-text="alert.date"></div>
                                            </div>
                                            <div>
                                                <span class="clay-badge clay-badge-info px-3 py-1 capitalize">
                                                    <span x-text="alert.signal"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!alertsData?.alerts || alertsData.alerts.length === 0">
                            <div class="text-center p-4">
                                <p class="text-gray-600">Tidak ada alert yang tersedia untuk periode ini</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tab Content - Price Prediction -->
            <div x-show="activeTab === 'prediction'">
                <div x-show="loadingPrediction" class="text-center py-6">
                    <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                    <p class="mt-2">Memuat prediksi harga...</p>
                </div>

                <div x-show="!loadingPrediction && predictionData">
                    <div class="clay-card p-4 mb-4 bg-primary/5">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                            <h3 class="font-bold mb-3 md:mb-0">Prediksi Harga</h3>
                            <div>
                                <span class="clay-badge px-3 py-1" :class="{
                                    'clay-badge-success': predictionData?.prediction_direction === 'up',
                                    'clay-badge-danger': predictionData?.prediction_direction === 'down',
                                    'clay-badge-warning': predictionData?.prediction_direction === 'sideways'
                                }">
                                    Model: <span class="font-medium" x-text="predictionData?.model_type"></span>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Prediction Overview -->
                            <div class="clay-card p-3 bg-white">
                                <h4 class="font-medium mb-3">Ringkasan Prediksi</h4>

                                <div class="p-3 clay-card mb-3" :class="{
                                    'bg-success/10': predictionData?.prediction_direction === 'up',
                                    'bg-danger/10': predictionData?.prediction_direction === 'down',
                                    'bg-warning/10': predictionData?.prediction_direction === 'sideways'
                                }">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm text-gray-600">Arah Prediksi:</div>
                                            <div class="font-bold text-lg capitalize" x-text="predictionData?.prediction_direction"></div>
                                        </div>
                                        <div>
                                            <template x-if="predictionData?.prediction_direction === 'up'">
                                                <i class="fas fa-arrow-circle-up text-3xl text-success"></i>
                                            </template>
                                            <template x-if="predictionData?.prediction_direction === 'down'">
                                                <i class="fas fa-arrow-circle-down text-3xl text-danger"></i>
                                            </template>
                                            <template x-if="predictionData?.prediction_direction === 'sideways'">
                                                <i class="fas fa-minus-circle text-3xl text-warning"></i>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="mt-2">
                                        <div class="text-sm text-gray-600">Perubahan yang Diprediksi:</div>
                                        <div class="font-bold" x-text="predictionData?.predicted_change_percent.toFixed(2) + '%'"></div>
                                    </div>

                                    <div class="mt-2">
                                        <div class="text-sm text-gray-600">Kepercayaan:</div>
                                        <div class="font-medium" x-text="(predictionData?.confidence * 100).toFixed(0) + '%'"></div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="p-2 clay-card bg-info/5">
                                        <div class="text-xs text-gray-600">Harga Saat Ini:</div>
                                        <div class="font-bold">$<span x-text="predictionData?.current_price?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></div>
                                    </div>

                                    <div class="p-2 clay-card bg-info/5">
                                        <div class="text-xs text-gray-600">Market Regime:</div>
                                        <div class="font-medium capitalize" x-text="predictionData?.market_regime?.replace('_', ' ')"></div>
                                    </div>

                                    <div class="p-2 clay-card bg-info/5">
                                        <div class="text-xs text-gray-600">Probabilitas Pembalikan:</div>
                                        <div class="font-medium" x-text="predictionData?.reversal_probability ? (predictionData.reversal_probability * 100).toFixed(0) + '%' : 'N/A'"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Support & Resistance -->
                            <div class="clay-card p-3 bg-white">
                                <h4 class="font-medium mb-3">Level Support & Resistance</h4>

                                <div class="space-y-3">
                                    <template x-if="predictionData?.resistance_levels && Object.keys(predictionData.resistance_levels).length > 0">
                                        <div class="p-2 clay-card bg-danger/5">
                                            <h5 class="font-medium text-sm text-danger mb-2">Resistance Levels</h5>
                                            <div class="grid grid-cols-2 gap-2">
                                                <template x-for="(value, level) in predictionData.resistance_levels" :key="level">
                                                    <template x-if="value">
                                                        <div class="flex justify-between items-center">
                                                            <span class="capitalize" x-text="level.replace('_', ' ')"></span>
                                                            <span class="font-medium">$<span x-text="value?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="predictionData?.support_levels && Object.keys(predictionData.support_levels).length > 0">
                                        <div class="p-2 clay-card bg-success/5">
                                            <h5 class="font-medium text-sm text-success mb-2">Support Levels</h5>
                                            <div class="grid grid-cols-2 gap-2">
                                                <template x-for="(value, level) in predictionData.support_levels" :key="level">
                                                    <template x-if="value">
                                                        <div class="flex justify-between items-center">
                                                            <span class="capitalize" x-text="level.replace('_', ' ')"></span>
                                                            <span class="font-medium">$<span x-text="value?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Predictions Table -->
                        <template x-if="predictionData?.predictions && predictionData.predictions.length > 0">
                            <div class="mt-6">
                                <h4 class="font-medium mb-3">Prediksi Harga Selanjutnya</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white clay-card">
                                        <thead>
                                            <tr>
                                                <th class="py-2 px-4 border-b text-left">Tanggal</th>
                                                <th class="py-2 px-4 border-b text-right">Harga Prediksi</th>
                                                <th class="py-2 px-4 border-b text-right">Kepercayaan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(prediction, index) in predictionData.predictions" :key="index">
                                                <tr :class="index % 2 === 0 ? 'bg-gray-50' : ''">
                                                    <td class="py-2 px-4 border-b" x-text="prediction.date"></td>
                                                    <td class="py-2 px-4 border-b text-right font-medium">$<span x-text="prediction.value?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></td>
                                                    <td class="py-2 px-4 border-b text-right" x-text="(prediction.confidence * 100).toFixed(0) + '%'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Periode Indikator yang Digunakan -->
            <div class="mt-6 clay-card bg-secondary/5 p-4">
                <h3 class="font-bold mb-2">Periode Indikator yang Digunakan:</h3>
                <template x-if="results?.indicator_periods && Object.keys(results?.indicator_periods).length > 0">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <template x-for="(value, period) in results?.indicator_periods" :key="period">
                            <div>
                                <span class="font-medium" x-text="period.replace(/_/g, ' ').toUpperCase() + ':'"></span>
                                <span x-text="value"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!results?.indicator_periods || Object.keys(results?.indicator_periods || {}).length === 0">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <div>
                            <span class="font-medium">RSI PERIOD:</span>
                            <span x-text="params.rsi_period"></span>
                        </div>
                        <div>
                            <span class="font-medium">MACD FAST:</span>
                            <span x-text="params.macd_fast"></span>
                        </div>
                        <div>
                            <span class="font-medium">MACD SLOW:</span>
                            <span x-text="params.macd_slow"></span>
                        </div>
                        <div>
                            <span class="font-medium">MACD SIGNAL:</span>
                            <span x-text="params.macd_signal"></span>
                        </div>
                        <div>
                            <span class="font-medium">BB PERIOD:</span>
                            <span x-text="params.bb_period"></span>
                        </div>
                        <div>
                            <span class="font-medium">STOCH K:</span>
                            <span x-text="params.stoch_k"></span>
                        </div>
                        <div>
                            <span class="font-medium">STOCH D:</span>
                            <span x-text="params.stoch_d"></span>
                        </div>
                        <div>
                            <span class="font-medium">MA SHORT:</span>
                            <span x-text="params.ma_short"></span>
                        </div>
                        <div>
                            <span class="font-medium">MA MEDIUM:</span>
                            <span x-text="params.ma_medium"></span>
                        </div>
                        <div>
                            <span class="font-medium">MA LONG:</span>
                            <span x-text="params.ma_long"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Info Panel -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-primary"></i>
            Tentang Analisis Teknikal
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
            <div class="clay-card bg-primary/10 p-4">
                <h3 class="font-bold mb-2">Periode Indikator</h3>
                <p>
                    Periode indikator menentukan seberapa banyak data historis yang digunakan dalam perhitungan indikator.
                    Periode yang lebih pendek akan lebih responsif terhadap perubahan harga baru-baru ini, tetapi juga lebih
                    rentan terhadap noise dan false signals. Periode yang lebih panjang akan lebih stabil tetapi bisa
                    terlambat memberikan sinyal perubahan tren.
                </p>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Preset Gaya Trading</h3>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-bolt text-warning mt-1 mr-2"></i>
                        <span><strong>Short-Term:</strong> Untuk day trading atau swing trading, menggunakan periode yang lebih pendek untuk menangkap pergerakan jangka pendek.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-balance-scale text-warning mt-1 mr-2"></i>
                        <span><strong>Standard:</strong> Nilai standard yang umumnya digunakan dalam analisis teknikal.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-chart-line text-warning mt-1 mr-2"></i>
                        <span><strong>Long-Term:</strong> Untuk posisi jangka panjang, menggunakan periode yang lebih panjang untuk mengurangi noise dan fokus pada tren utama.</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Tips Penggunaan</h3>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Gunakan beberapa indikator yang berbeda untuk konfirmasi.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Sesuaikan periode berdasarkan volatilitas pasar dan time frame trading Anda.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Uji berbagai periode untuk menemukan yang paling cocok dengan gaya trading Anda.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                        <span>Kombinasikan dengan analisis fundamental untuk hasil terbaik.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Penjelasan Indikator -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Penjelasan Indikator
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">RSI (Relative Strength Index)</h3>
                <p class="mb-2">
                    Indikator momentum yang mengukur kecepatan dan perubahan pergerakan harga. RSI bergerak antara 0-100.
                </p>
                <ul class="space-y-1">
                    <li>- Nilai di atas 70: Kondisi overbought (terlalu banyak dibeli, berpotensi turun)</li>
                    <li>- Nilai di bawah 30: Kondisi oversold (terlalu banyak dijual, berpotensi naik)</li>
                    <li>- Periode umum: 14 (standard), 7 (short-term), 21 (long-term)</li>
                </ul>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">MACD (Moving Average Convergence Divergence)</h3>
                <p class="mb-2">
                    Indikator trend-following yang menunjukkan hubungan antara dua moving average.
                </p>
                <ul class="space-y-1">
                    <li>- MACD Line: Selisih antara MA Cepat dan MA Lambat</li>
                    <li>- Signal Line: EMA dari MACD Line</li>
                    <li>- Sinyal beli: MACD Line memotong di atas Signal Line</li>
                    <li>- Sinyal jual: MACD Line memotong di bawah Signal Line</li>
                    <li>- Periode umum: 12-26-9 (standard), 8-17-9 (short-term), 19-39-9 (long-term)</li>
                </ul>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">Bollinger Bands</h3>
                <p class="mb-2">
                    Indikator volatilitas yang terdiri dari moving average dan dua band standar deviasi.
                </p>
                <ul class="space-y-1">
                    <li>- Middle Band: SMA (biasanya 20 periode)</li>
                    <li>- Upper/Lower Band: Middle Band ± (2 × standar deviasi)</li>
                    <li>- Squeeze: Band menyempit (volatilitas rendah, potensi breakout)</li>
                    <li>- Harga menyentuh band: Potensi reversal atau lanjutan tren</li>
                    <li>- Periode umum: 20 (standard), 10 (short-term), 30 (long-term)</li>
                </ul>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">Moving Averages</h3>
                <p class="mb-2">
                    Rata-rata harga dalam periode tertentu, digunakan untuk mengidentifikasi tren.
                </p>
                <ul class="space-y-1">
                    <li>- MA Pendek > MA Panjang: Tren naik (bullish)</li>
                    <li>- MA Pendek < MA Panjang: Tren turun (bearish)</li>
                    <li>- Crossover: Perubahan tren potensial</li>
                    <li>- MA sebagai support/resistance: Level harga penting</li>
                    <li>- Periode umum: 20-50-200 (standard), 10-30-60 (short-term), 50-100-200 (long-term)</li>
                </ul>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">Stochastic Oscillator</h3>
                <p class="mb-2">
                    Indikator momentum yang membandingkan harga penutupan dengan rentang harga dalam periode tertentu.
                </p>
                <ul class="space-y-1">
                    <li>- %K: Line utama (kecepatan perubahan)</li>
                    <li>- %D: SMA dari %K</li>
                    <li>- Nilai di atas 80: Kondisi overbought</li>
                    <li>- Nilai di bawah 20: Kondisi oversold</li>
                    <li>- Crossover di area ekstrem: Sinyal untuk entry/exit</li>
                    <li>- Periode umum: %K=14, %D=3 (standard), %K=7, %D=3 (short-term)</li>
                </ul>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-2">ADX (Average Directional Index)</h3>
                <p class="mb-2">
                    Mengukur kekuatan tren terlepas dari arahnya. Nilai ADX antara 0-100.
                </p>
                <ul class="space-y-1">
                    <li>- ADX > 25: Tren kuat</li>
                    <li>- ADX < 20: Tren lemah (sideways/ranging)</li>
                    <li>- +DI > -DI: Tren bullish</li>
                    <li>- -DI > +DI: Tren bearish</li>
                    <li>- Crossover +DI dan -DI: Potensi sinyal entry/exit</li>
                    <li>- Periode umum: 14 (standard), 7 (short-term), 21 (long-term)</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Market Regimes Explanation -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-globe mr-2 text-primary"></i>
            Penjelasan Market Regime
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div class="clay-card bg-success/10 p-4">
                <h3 class="font-bold mb-2">Trending Markets</h3>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-up text-success mt-1 mr-2"></i>
                        <span><strong>Trending Bullish:</strong> Tren naik dengan volatilitas normal. Gunakan indikator tren seperti Moving Averages dan MACD.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-down text-danger mt-1 mr-2"></i>
                        <span><strong>Trending Bearish:</strong> Tren turun dengan volatilitas normal. Perhatikan level support dan RSI untuk potential reversals.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-bolt text-warning mt-1 mr-2"></i>
                        <span><strong>Trending Volatile:</strong> Tren kuat dengan volatilitas tinggi. Gunakan stop loss yang lebih lebar dan take profit yang agresif.</span>
                    </li>
                </ul>
            </div>

            <div class="clay-card bg-warning/10 p-4">
                <h3 class="font-bold mb-2">Ranging Markets</h3>
                <ul class="space-y-2">
                    <li class="flex items-start">
                        <i class="fas fa-arrows-alt-h text-info mt-1 mr-2"></i>
                        <span><strong>Ranging Low Volatility:</strong> Pasar sideways dengan volatilitas rendah. Gunakan oscillator seperti RSI dan Stochastic.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-exchange-alt text-warning mt-1 mr-2"></i>
                        <span><strong>Ranging Volatile:</strong> Pasar sideways dengan volatilitas tinggi. Efektif untuk strategi range-bound dengan stop loss yang cukup.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-compress-arrows-alt text-secondary mt-1 mr-2"></i>
                        <span><strong>Volatile Sideways:</strong> Pasar dengan volatilitas ekstrem tanpa arah yang jelas. Hati-hati dengan false breakouts.</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-4 p-4 clay-card bg-info/5 text-sm">
            <p>
                <strong>Strategi adaptif berdasarkan regime:</strong> Market regime mempengaruhi efektivitas indikator teknikal. Sistem trading adaptif mengubah parameternya secara dinamis untuk menyesuaikan dengan kondisi pasar saat ini. Untuk trending markets, indikator seperti Moving Averages dan MACD bekerja lebih baik. Untuk ranging markets, oscillator seperti RSI dan Stochastic lebih efektif.
            </p>
        </div>
    </div>
</div>
@endsection
