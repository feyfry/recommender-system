@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header tetap sama -->
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
                interval: '1d',
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
                        interval: this.interval,
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
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.loading = false;
                        alert('Terjadi kesalahan saat menganalisis: ' + error.message);
                    });
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

        <!-- Hasil Analisis: Penggunaan Optional Chaining untuk Mencegah Error -->
        <div x-show="results" class="mt-8">
            <h2 class="text-xl font-bold mb-6 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-success"></i>
                Hasil Analisis <span x-text="results?.market_regime ? '- ' + results.market_regime.replaceAll('_', ' ').toUpperCase() : ''"></span>
            </h2>

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
                            <template x-if="results?.indicators.rsi !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">RSI</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.rsi > 70 ? 'text-danger' : (results?.indicators.rsi < 30 ? 'text-success' : '')"
                                        x-text="results?.indicators.rsi.toFixed(2)"></span>
                                </div>
                            </template>

                            <!-- MACD -->
                            <template x-if="results?.indicators.macd !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">MACD</span>
                                    <span class="font-medium" x-text="results?.indicators.macd.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="results?.indicators.macd_signal !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">MACD Signal</span>
                                    <span class="font-medium" x-text="results?.indicators.macd_signal.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="results?.indicators.macd_histogram !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">MACD Histogram</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.macd_histogram > 0 ? 'text-success' : 'text-danger'"
                                        x-text="results?.indicators.macd_histogram.toFixed(2)"></span>
                                </div>
                            </template>

                            <!-- Bollinger -->
                            <template x-if="results?.indicators.bollinger_percent !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">Bollinger %B</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.bollinger_percent > 1 ? 'text-danger' : (results?.indicators.bollinger_percent < 0 ? 'text-success' : '')"
                                        x-text="results?.indicators.bollinger_percent.toFixed(2)"></span>
                                </div>
                            </template>

                            <!-- Stochastic -->
                            <template x-if="results?.indicators.stochastic_k !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">Stochastic %K</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.stochastic_k > 80 ? 'text-danger' : (results?.indicators.stochastic_k < 20 ? 'text-success' : '')"
                                        x-text="results?.indicators.stochastic_k.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="results?.indicators.stochastic_d !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">Stochastic %D</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.stochastic_d > 80 ? 'text-danger' : (results?.indicators.stochastic_d < 20 ? 'text-success' : '')"
                                        x-text="results?.indicators.stochastic_d.toFixed(2)"></span>
                                </div>
                            </template>

                            <!-- ADX -->
                            <template x-if="results?.indicators.adx !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">ADX</span>
                                    <span class="font-medium"
                                        :class="results?.indicators.adx > 25 ? 'text-success' : ''"
                                        x-text="results?.indicators.adx.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="results?.indicators.plus_di !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">+DI</span>
                                    <span class="font-medium" x-text="results?.indicators.plus_di.toFixed(2)"></span>
                                </div>
                            </template>

                            <template x-if="results?.indicators.minus_di !== undefined">
                                <div class="flex justify-between p-2 border-b border-gray-200">
                                    <span class="uppercase">-DI</span>
                                    <span class="font-medium" x-text="results?.indicators.minus_di.toFixed(2)"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!results?.indicators || Object.keys(results?.indicators || {}).length === 0">
                        <p class="text-center text-gray-500">Tidak ada indikator yang tersedia</p>
                    </template>
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

    <!-- Info Panel (tidak diubah) -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-primary"></i>
            Tentang Analisis Teknikal
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
            <!-- Konten info panel tidak diubah -->
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

    <!-- Penjelasan Indikator (tidak diubah) -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-lightbulb mr-2 text-warning"></i>
            Penjelasan Indikator
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <!-- Konten penjelasan indikator tidak diubah -->
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
        </div>
    </div>
</div>
@endsection
