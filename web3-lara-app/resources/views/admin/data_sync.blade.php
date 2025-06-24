@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="clay-card p-6 mb-8">
        <h1 class="text-3xl font-bold mb-4 flex items-center">
            <div class="bg-warning/20 p-2 clay-badge mr-3">
                <i class="fas fa-sync text-warning"></i>
            </div>
            Sinkronisasi Data
        </h1>
        <p class="text-lg">
            Sinkronisasi data antara aplikasi Laravel dan engine rekomendasi Python.
        </p>
    </div>

    <!-- Status & Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Project Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-project-diagram mr-2 text-success"></i>
                Statistik Proyek
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Proyek:</span>
                    <span class="font-bold">{{ $projectStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Diperbarui 24 Jam:</span>
                    <span class="font-bold">{{ $projectStats['recently_updated'] ?? 0 }}</span>
                </div>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.projects') }}" class="clay-button clay-button-success py-1.5 px-3 text-sm">
                    Lihat Semua Proyek
                </a>
            </div>
        </div>

        <!-- Memory Cache Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-memory mr-2 text-primary"></i>
                Cache Memory
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Estimasi:</span>
                    <span class="font-bold">{{ $cacheStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Cache Valid:</span>
                    <span class="font-bold">{{ $cacheStats['valid'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Cache Expired:</span>
                    <span class="font-bold">{{ $cacheStats['expired'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Hit Rate:</span>
                    <span class="font-bold">{{ number_format($cacheStats['hit_rate'] ?? 0, 1) }}%</span>
                </div>
            </div>
            <div class="mt-4">
                <button onclick="document.getElementById('clear-cache-modal').classList.remove('hidden')" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                    Hapus Cache
                </button>
            </div>
            @if($cacheStats['type'] === 'memory_cache')
            <div class="mt-2">
                <p class="text-xs text-info">💡 Cache disimpan di memory</p>
            </div>
            @endif
        </div>

        <!-- Endpoint Usage -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-chart-bar mr-2 text-info"></i>
                Penggunaan Endpoint
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Endpoint:</span>
                    <span class="font-bold">{{ count($endpointUsage ?? []) }}</span>
                </div>

                @php
                    $topEndpoints = isset($endpointUsage) && count($endpointUsage) > 0 ?
                        $endpointUsage->take(3) : collect([]);
                @endphp

                @foreach($topEndpoints as $endpoint)
                <div class="flex justify-between">
                    <span class="truncate max-w-[150px]">{{ $endpoint->endpoint }}</span>
                    <span class="font-bold">{{ $endpoint->count }}</span>
                </div>
                @endforeach
            </div>
            <div class="mt-4">
                <button onclick="toggleEndpointList()" class="clay-button clay-button-info py-1.5 px-3 text-sm">
                    Detail Endpoint
                </button>
            </div>
        </div>

        <!-- Sync Status -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2 text-warning"></i>
                Status Sinkronisasi
            </h2>
            <div class="space-y-2">
                @php
                    // Cek status file
                    $projectsFileExists = file_exists(base_path('../recommendation-engine/data/processed/projects.csv'));
                    $interactionsFileExists = file_exists(base_path('../recommendation-engine/data/processed/interactions.csv'));
                    $featuresFileExists = file_exists(base_path('../recommendation-engine/data/processed/features.csv'));
                @endphp
                <div class="flex justify-between">
                    <span>Projects CSV:</span>
                    <span class="font-bold {{ $projectsFileExists ? 'text-success' : 'text-danger' }}">
                        {{ $projectsFileExists ? 'Tersedia' : 'Tidak Ada' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Interactions CSV:</span>
                    <span class="font-bold {{ $interactionsFileExists ? 'text-success' : 'text-danger' }}">
                        {{ $interactionsFileExists ? 'Tersedia' : 'Tidak Ada' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Features CSV:</span>
                    <span class="font-bold {{ $featuresFileExists ? 'text-success' : 'text-danger' }}">
                        {{ $featuresFileExists ? 'Tersedia' : 'Tidak Ada' }}
                    </span>
                </div>
            </div>
            <div class="mt-4">
                <button onclick="document.getElementById('sync-data-modal').classList.remove('hidden')" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                    Sinkronisasi Data
                </button>
            </div>
        </div>
    </div>

    <!-- API Connection Test - UPDATED dengan endpoint baru -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-plug mr-2 text-info"></i>
            Tes Koneksi API
        </h2>

        <div class="mb-6">
            <div class="clay-card bg-primary/5 p-4">
                <h3 class="font-bold mb-3">Status API Engine</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="clay-card bg-primary/10 p-3 text-center api-test" data-endpoint="root">
                        <div class="status-indicator">
                            <i class="fas fa-circle-notch fa-spin text-2xl text-primary mb-2"></i>
                        </div>
                        <div class="font-bold">Root Endpoint</div>
                        <p class="text-xs mt-1">GET /</p>
                    </div>
                    <div class="clay-card bg-success/10 p-3 text-center api-test" data-endpoint="health">
                        <div class="status-indicator">
                            <i class="fas fa-circle-notch fa-spin text-2xl text-success mb-2"></i>
                        </div>
                        <div class="font-bold">Health Check</div>
                        <p class="text-xs mt-1">GET /health</p>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="text-lg font-bold mb-4">Recommendation Endpoints</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="clay-card bg-primary/10 p-4 text-center api-test" data-endpoint="trending">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-primary mb-2"></i>
                </div>
                <div class="font-bold">Trending</div>
                <p class="text-xs mt-1">GET /recommend/trending</p>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center api-test" data-endpoint="popular">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-success mb-2"></i>
                </div>
                <div class="font-bold">Popular</div>
                <p class="text-xs mt-1">GET /recommend/popular</p>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center api-test" data-endpoint="projects">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-warning mb-2"></i>
                </div>
                <div class="font-bold">Recommendations</div>
                <p class="text-xs mt-1">POST /recommend/projects</p>
            </div>

            <div class="clay-card bg-info/10 p-4 text-center api-test" data-endpoint="similar">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-info mb-2"></i>
                </div>
                <div class="font-bold">Similar Projects</div>
                <p class="text-xs mt-1">GET /recommend/similar/{id}</p>
            </div>
        </div>

        <h3 class="text-lg font-bold mb-4">Analysis Endpoints</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="clay-card bg-secondary/10 p-4 text-center api-test" data-endpoint="trading-signals">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-secondary mb-2"></i>
                </div>
                <div class="font-bold">Trading Signals</div>
                <p class="text-xs mt-1">POST /analysis/trading-signals</p>
            </div>

            <div class="clay-card bg-primary/10 p-4 text-center api-test" data-endpoint="indicators">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-primary mb-2"></i>
                </div>
                <div class="font-bold">Technical Indicators</div>
                <p class="text-xs mt-1">POST /analysis/indicators</p>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center api-test" data-endpoint="market-events">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-success mb-2"></i>
                </div>
                <div class="font-bold">Market Events</div>
                <p class="text-xs mt-1">GET /analysis/market-events/{id}</p>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center api-test" data-endpoint="alerts">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-warning mb-2"></i>
                </div>
                <div class="font-bold">Price Alerts</div>
                <p class="text-xs mt-1">GET /analysis/alerts/{id}</p>
            </div>

            <div class="clay-card bg-info/10 p-4 text-center api-test" data-endpoint="price-prediction">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-info mb-2"></i>
                </div>
                <div class="font-bold">Price Prediction</div>
                <p class="text-xs mt-1">GET /analysis/price-prediction/{id}</p>
            </div>
        </div>

        <!-- NEW: Blockchain Analytics Endpoints -->
        <h3 class="text-lg font-bold mb-4">🚀 Multi-Chain Blockchain Analytics</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="clay-card bg-purple/10 p-4 text-center api-test" data-endpoint="blockchain-portfolio">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-purple mb-2"></i>
                </div>
                <div class="font-bold">Portfolio Analysis</div>
                <p class="text-xs mt-1">GET /blockchain/portfolio/{wallet}</p>
            </div>

            <div class="clay-card bg-indigo/10 p-4 text-center api-test" data-endpoint="blockchain-analytics">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-indigo mb-2"></i>
                </div>
                <div class="font-bold">Multi-Chain Analytics</div>
                <p class="text-xs mt-1">GET /blockchain/analytics/{wallet}</p>
            </div>

            <div class="clay-card bg-teal/10 p-4 text-center api-test" data-endpoint="blockchain-transactions">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-teal mb-2"></i>
                </div>
                <div class="font-bold">Transactions</div>
                <p class="text-xs mt-1">GET /blockchain/transactions/{wallet}</p>
            </div>

            <div class="clay-card bg-cyan/10 p-4 text-center api-test" data-endpoint="blockchain-health">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-cyan mb-2"></i>
                </div>
                <div class="font-bold">Blockchain Health</div>
                <p class="text-xs mt-1">GET /blockchain/health</p>
            </div>
        </div>

        <h3 class="text-lg font-bold mb-4">Admin & Data Endpoints</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center api-test" data-endpoint="record-interaction">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-primary mb-2"></i>
                </div>
                <div class="font-bold">Record Interaction</div>
                <p class="text-xs mt-1">POST /interactions/record</p>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center api-test" data-endpoint="train-models">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-success mb-2"></i>
                </div>
                <div class="font-bold">Train Models</div>
                <p class="text-xs mt-1">POST /admin/train-models</p>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center api-test" data-endpoint="sync-data">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-warning mb-2"></i>
                </div>
                <div class="font-bold">Sync Data</div>
                <p class="text-xs mt-1">POST /admin/sync-data</p>
            </div>

            <div class="clay-card bg-info/10 p-4 text-center api-test" data-endpoint="rec-cache-clear">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-info mb-2"></i>
                </div>
                <div class="font-bold">Clear Recommendation Cache</div>
                <p class="text-xs mt-1">POST /recommend/cache/clear</p>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center api-test" data-endpoint="analysis-cache-clear">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-secondary mb-2"></i>
                </div>
                <div class="font-bold">Clear Analysis Cache</div>
                <p class="text-xs mt-1">POST /analysis/cache/clear</p>
            </div>

            <div class="clay-card bg-purple/10 p-4 text-center api-test" data-endpoint="blockchain-cache-clear">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-2xl text-purple mb-2"></i>
                </div>
                <div class="font-bold">Clear Blockchain Cache</div>
                <p class="text-xs mt-1">POST /blockchain/cache/clear</p>
            </div>
        </div>
    </div>

    <!-- Sync Options -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Production Pipeline -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-rocket mr-2 text-success"></i>
                Production Pipeline
            </h2>
            <p class="text-sm text-gray-600 mb-4">
                Jalankan production pipeline lengkap yang mencakup: collect data, update projects,
                train models, evaluate, dan auto import ke database Laravel.
            </p>
            <div class="clay-card bg-success/5 p-4 mb-4">
                <h3 class="font-bold mb-2">Production Command (Baru):</h3>
                <div class="space-y-2 font-mono text-xs">
                    <div class="clay-card bg-success/10 p-2">
                        python main.py run --production --evaluate
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-600">
                    ✅ Preserve existing interactions<br>
                    ✅ Update projects dengan data terbaru<br>
                    ✅ Train semua models (FECF, NCF, Hybrid)<br>
                    ✅ Evaluate performa models<br>
                    ✅ Auto import ke Laravel database
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="document.getElementById('production-pipeline-modal').classList.remove('hidden')" class="clay-button clay-button-success">
                    <i class="fas fa-rocket mr-2"></i> Run Production Pipeline
                </button>
            </div>
        </div>

        <!-- Train Models -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-brain mr-2 text-secondary"></i>
                Latih Model
            </h2>
            <p class="text-sm text-gray-600 mb-4">
                Melatih model rekomendasi menggunakan data terbaru yang telah disinkronkan.
                Model yang dapat dilatih: FECF, NCF, dan Hybrid.
            </p>
            <div class="clay-card bg-secondary/5 p-4 mb-4">
                <h3 class="font-bold mb-2">Performa Model Terbaru:</h3>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="clay-card bg-secondary/10 p-2">
                        <div class="font-bold">FECF</div>
                        <div>NDCG: {{ number_format($modelEvaluation['fecf']['ndcg'], 4) }}</div>
                        <div>Hit Ratio: {{ number_format($modelEvaluation['fecf']['hit_ratio'], 4) }}</div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-2">
                        <div class="font-bold">NCF</div>
                        <div>NDCG: {{ number_format($modelEvaluation['ncf']['ndcg'], 4) }}</div>
                        <div>Hit Ratio: {{ number_format($modelEvaluation['ncf']['hit_ratio'], 4) }}</div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-2">
                        <div class="font-bold">Hybrid</div>
                        <div>NDCG: {{ number_format($modelEvaluation['hybrid']['ndcg'], 4) }}</div>
                        <div>Hit Ratio: {{ number_format($modelEvaluation['hybrid']['hit_ratio'], 4) }}</div>
                    </div>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="document.getElementById('train-models-modal').classList.remove('hidden')" class="clay-button clay-button-secondary">
                    <i class="fas fa-brain mr-2"></i> Latih Model
                </button>
            </div>
        </div>
    </div>

    <!-- Cache Data -->
    <div class="clay-card p-6 mb-8" id="endpoint-list" style="display: none;">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-chart-line mr-2 text-primary"></i>
            Estimasi Penggunaan Endpoint
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">Endpoint</th>
                        <th class="py-3 px-4 text-left">Estimasi Usage</th>
                        <th class="py-3 px-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($endpointUsage as $endpoint)
                    <tr>
                        <td class="py-3 px-4 font-medium">{{ $endpoint->endpoint }}</td>
                        <td class="py-3 px-4">{{ $endpoint->count }}</td>
                        <td class="py-3 px-4">
                            <span class="clay-badge clay-badge-success text-xs">Active</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Estimasi penggunaan endpoint.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import/Export Data -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-exchange-alt mr-2 text-success"></i>
            Import/Export Data
        </h2>

        <!-- Tampilkan status file CSV -->
        @php
            $basePath = base_path('../recommendation-engine/data/processed/');
            $projectsCsvExists = file_exists($basePath . 'projects.csv');
            $interactionsCsvExists = file_exists($basePath . 'interactions.csv');
            $projectsCsvDate = $projectsCsvExists ? date("Y-m-d H:i:s", filemtime($basePath . 'projects.csv')) : null;
            $interactionsCsvDate = $interactionsCsvExists ? date("Y-m-d H:i:s", filemtime($basePath . 'interactions.csv')) : null;
        @endphp

        <div class="clay-card bg-info/10 p-4 mb-6">
            <h3 class="font-bold mb-2">Status File CSV:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p><strong>projects.csv:</strong>
                        @if($projectsCsvExists)
                            <span class="text-success">✓ Ada</span>
                            <small class="text-gray-600">(Terakhir diupdate: {{ $projectsCsvDate }})</small>
                        @else
                            <span class="text-danger">✗ Tidak Ada</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p><strong>interactions.csv:</strong>
                        @if($interactionsCsvExists)
                            <span class="text-success">✓ Ada</span>
                            <small class="text-gray-600">(Terakhir diupdate: {{ $interactionsCsvDate }})</small>
                        @else
                            <span class="text-danger">✗ Tidak Ada</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="clay-card bg-primary/5 p-4">
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-file-import mr-2 text-primary"></i>
                    Import Proyek
                </h3>
                <p class="text-sm mb-4">
                    Import data proyek dari file CSV engine rekomendasi ke database Laravel.
                </p>
                <div class="text-center">
                    <form action="{{ route('admin.import-command') }}" method="POST" id="import-projects-form">
                        @csrf
                        <input type="hidden" name="command" value="recommend:import --projects">
                        <button type="submit"
                                class="clay-button clay-button-primary py-1.5 px-3 text-sm import-button"
                                {{ !$projectsCsvExists ? 'disabled' : '' }}>
                            Import Proyek
                        </button>
                    </form>
                    @if(!$projectsCsvExists)
                        <p class="text-xs text-danger mt-2">File projects.csv tidak ditemukan!</p>
                    @endif
                </div>
            </div>

            <div class="clay-card bg-warning/5 p-4">
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-file-import mr-2 text-warning"></i>
                    Import Interaksi
                </h3>
                <p class="text-sm mb-4">
                    Import data interaksi dari file CSV engine rekomendasi ke database Laravel.
                </p>
                <div class="text-center">
                    <form action="{{ route('admin.import-command') }}" method="POST" id="import-interactions-form">
                        @csrf
                        <input type="hidden" name="command" value="recommend:import --interactions">
                        <button type="submit"
                                class="clay-button clay-button-warning py-1.5 px-3 text-sm import-button"
                                {{ !$interactionsCsvExists ? 'disabled' : '' }}>
                            Import Interaksi
                        </button>
                    </form>
                    @if(!$interactionsCsvExists)
                        <p class="text-xs text-danger mt-2">File interactions.csv tidak ditemukan!</p>
                    @endif
                </div>
            </div>

            <div class="clay-card bg-info/5 p-4">
                <h3 class="font-bold mb-3 flex items-center">
                    <i class="fas fa-file-export mr-2 text-info"></i>
                    Export Data
                </h3>
                <p class="text-sm mb-4">
                    Export data dari database Laravel ke file CSV untuk engine rekomendasi.
                </p>
                <div class="text-center">
                    <form action="{{ route('admin.import-command') }}" method="POST" id="export-data-form">
                        @csrf
                        <input type="hidden" name="command" value="recommend:sync --full">
                        <button type="submit" class="clay-button clay-button-info py-1.5 px-3 text-sm import-button">
                            Export Semua Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- UPDATED: CRON Jobs Configuration -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-clock mr-2 text-info"></i>
            Jadwal Tugas Otomatis (CRON Jobs)
        </h2>

        <div class="clay-card bg-info/10 p-6 mb-6">
            <h3 class="font-bold mb-3">Konfigurasi CRON Jobs (Updated)</h3>
            <p class="text-sm mb-4">
                Jadwal tugas otomatis telah diperbarui menggunakan production pipeline:
            </p>
            <div class="overflow-x-auto">
                <table class="clay-table min-w-full">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 text-left">Command</th>
                            <th class="py-2 px-4 text-left">Jadwal</th>
                            <th class="py-2 px-4 text-left">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-success/5">
                            <td class="py-2 px-4 font-mono text-xs">
                                python main.py run --production --evaluate
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-success">12 jam sekali</span>
                            </td>
                            <td class="py-2 px-4">
                                Production pipeline lengkap + auto import
                                <div class="text-xs text-gray-600 mt-1">
                                    ✅ Update projects<br>
                                    ✅ Train models<br>
                                    ✅ Evaluate performance<br>
                                    ✅ Auto import to Laravel
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                recommend:sync --interactions
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">4 jam sekali</span>
                            </td>
                            <td class="py-2 px-4">Export interaksi dari Laravel ke engine</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                cache:api-clear --expired
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">Setiap jam</span>
                            </td>
                            <td class="py-2 px-4">Membersihkan cache memory kadaluwarsa</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('admin.dashboard') }}" class="clay-button clay-button-info">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Production Pipeline Modal -->
<div id="production-pipeline-modal" class="fixed inset-0 z-50 hidden">
    <div class="clay-modal-backdrop"></div>
    <div class="clay-modal max-w-md">
        <div class="clay-modal-header">
            <h3 class="text-xl font-bold">Run Production Pipeline</h3>
        </div>
        <form action="{{ route('admin.run-production-pipeline') }}" method="POST">
            @csrf
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <div class="clay-card bg-warning/10 p-4">
                        <h4 class="font-bold mb-2">⚠️ Production Pipeline</h4>
                        <p class="text-sm">
                            Ini akan menjalankan pipeline production lengkap yang mencakup:
                        </p>
                        <ul class="text-sm mt-2 space-y-1">
                            <li>✅ Collect data terbaru dari CoinGecko</li>
                            <li>✅ Update projects (preserve existing interactions)</li>
                            <li>✅ Train semua models (FECF, NCF, Hybrid)</li>
                            <li>✅ Evaluate model performance</li>
                            <li>✅ Auto import hasil ke Laravel database</li>
                        </ul>
                    </div>

                    <div class="clay-card bg-info/10 p-4">
                        <p class="text-sm">
                            <strong>Estimasi waktu:</strong> 10-15 menit<br>
                            <strong>Auto Import:</strong> Ya (otomatis setelah pipeline selesai)
                        </p>
                    </div>
                </div>
            </div>
            <div class="clay-modal-footer">
                <button type="button" class="clay-button" onclick="document.getElementById('production-pipeline-modal').classList.add('hidden')">
                    Batal
                </button>
                <button type="submit" class="clay-button clay-button-success">
                    <i class="fas fa-rocket mr-1"></i> Run Production Pipeline
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Sync Data Modal -->
<div id="sync-data-modal" class="fixed inset-0 z-50 hidden">
    <div class="clay-modal-backdrop"></div>
    <div class="clay-modal max-w-md">
        <div class="clay-modal-header">
            <h3 class="text-xl font-bold">Sinkronisasi Data</h3>
        </div>
        <form action="{{ route('admin.trigger-data-sync') }}" method="POST">
            @csrf
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <p>Pilih jenis data yang ingin disinkronkan:</p>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="sync-all" name="sync_type" value="all" class="clay-checkbox" checked>
                        <label for="sync-all">Semua Data</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="sync-projects" name="sync_type" value="projects" class="clay-checkbox">
                        <label for="sync-projects">Hanya Proyek</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="sync-interactions" name="sync_type" value="interactions" class="clay-checkbox">
                        <label for="sync-interactions">Hanya Interaksi</label>
                    </div>
                </div>
            </div>
            <div class="clay-modal-footer">
                <button type="button" class="clay-button" onclick="document.getElementById('sync-data-modal').classList.add('hidden')">
                    Batal
                </button>
                <button type="submit" class="clay-button clay-button-success">
                    <i class="fas fa-sync mr-1"></i> Sinkronisasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Train Models Modal -->
<div id="train-models-modal" class="fixed inset-0 z-50 hidden">
    <div class="clay-modal-backdrop"></div>
    <div class="clay-modal max-w-md">
        <div class="clay-modal-header">
            <h3 class="text-xl font-bold">Latih Model Rekomendasi</h3>
        </div>
        <form action="{{ route('admin.train-models') }}" method="POST">
            @csrf
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <p>Pilih model yang ingin dilatih:</p>

                    <div class="clay-checkbox-container">
                        <input type="checkbox" id="model-fecf" name="models[]" value="fecf" class="clay-checkbox" checked>
                        <label for="model-fecf">Feature-Enhanced CF (FECF)</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="checkbox" id="model-ncf" name="models[]" value="ncf" class="clay-checkbox" checked>
                        <label for="model-ncf">Neural CF (NCF)</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="checkbox" id="model-hybrid" name="models[]" value="hybrid" class="clay-checkbox" checked>
                        <label for="model-hybrid">Hybrid Model</label>
                    </div>

                    <div class="text-sm text-gray-600 mt-4">
                        <p class="font-bold">Catatan:</p>
                        <p>Pelatihan model membutuhkan waktu yang cukup lama terutama untuk Neural CF. Proses ini berjalan secara asynchronous, dan Anda dapat melanjutkan pekerjaan lain selama model dilatih.</p>
                    </div>
                </div>
            </div>
            <div class="clay-modal-footer">
                <button type="button" class="clay-button" onclick="document.getElementById('train-models-modal').classList.add('hidden')">
                    Batal
                </button>
                <button type="submit" class="clay-button clay-button-secondary">
                    <i class="fas fa-brain mr-1"></i> Latih Model
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Clear Cache Modal -->
<div id="clear-cache-modal" class="fixed inset-0 z-50 hidden">
    <div class="clay-modal-backdrop"></div>
    <div class="clay-modal max-w-md">
        <div class="clay-modal-header">
            <h3 class="text-xl font-bold">Hapus Cache Memory</h3>
        </div>
        <form action="{{ route('admin.clear-api-cache') }}" method="POST">
            @csrf
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <p>Pilih opsi pembersihan cache memory:</p>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-all" name="cache_option" value="all" class="clay-checkbox" checked>
                        <label for="cache-all">Semua Cache Memory</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-expired" name="cache_option" value="expired" class="clay-checkbox">
                        <label for="cache-expired">Cache Keys Terpilih</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-maintenance" name="cache_option" value="maintenance" class="clay-checkbox">
                        <label for="cache-maintenance">Maintenance Cache + OpCache</label>
                    </div>

                    <div class="text-xs text-gray-600">
                        💡 Cache disimpan di memory Laravel, bukan database
                    </div>
                </div>
            </div>
            <div class="clay-modal-footer">
                <button type="button" class="clay-button" onclick="document.getElementById('clear-cache-modal').classList.add('hidden')">
                    Batal
                </button>
                <button type="submit" class="clay-button clay-button-primary">
                    <i class="fas fa-trash-alt mr-1"></i> Hapus Cache
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function toggleEndpointList() {
        const endpointList = document.getElementById('endpoint-list');
        if (endpointList.style.display === 'none') {
            endpointList.style.display = 'block';
        } else {
            endpointList.style.display = 'none';
        }
    }

    // Loading indicator untuk form import/export
    document.addEventListener('DOMContentLoaded', function() {
        // Handle form submission dengan loading indicator
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = form.querySelector('button[type="submit"]');

                if (submitButton && !submitButton.disabled) {
                    // Disable button
                    submitButton.disabled = true;

                    // Simpan teks original dan tambahkan spinner + teks loading
                    const originalHTML = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
                }
            });
        });
    });

    // API Connection Test
    document.addEventListener('DOMContentLoaded', function() {
        const apiUrl = "{{ env('RECOMMENDATION_API_URL', 'http://localhost:8001') }}";

        // Fungsi untuk menguji endpoint dan memperbarui UI
        function testEndpoint(endpoint, url, method, data = null, isDestructive = false) {
            const element = document.querySelector(`.api-test[data-endpoint="${endpoint}"] .status-indicator`);
            if (!element) return;

            // PERUBAHAN: Jika endpoint destructive (berbahaya), jangan lakukan test otomatis
            if (isDestructive) {
                element.innerHTML = `<button onclick="manualTestEndpoint('${endpoint}', '${url}', '${method}')"
                    class="clay-button clay-button-primary py-1 px-2 text-xs">
                    Tes Manual
                </button>`;
                return;
            }

            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                // Tambahkan body hanya jika method adalah POST/PUT
                ...(method !== 'GET' && data ? { body: JSON.stringify(data) } : {})
            };

            // Menambahkan timeout yang lebih lama (10 detik)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            options.signal = controller.signal;

            fetch(url, options)
                .then(response => {
                    clearTimeout(timeoutId);
                    if (response.ok) {
                        element.innerHTML = `<i class="fas fa-check-circle text-3xl text-success mb-2"></i>`;
                    } else {
                        element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
                        console.error(`Error ${method} ${url}: ${response.status} ${response.statusText}`);
                    }
                    return response.text().then(text => {
                        try {
                            return text ? JSON.parse(text) : {};
                        } catch (e) {
                            return {};
                        }
                    });
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    console.error(`Error testing ${endpoint} (${url}):`, error);
                    element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
                });
        }

        // TAMBAHAN: Fungsi untuk test manual endpoint berbahaya
        window.manualTestEndpoint = function(endpoint, url, method) {
            if (!confirm(`Anda yakin ingin menguji endpoint ${endpoint}? Endpoint ini dapat menyebabkan perubahan pada sistem.`)) {
                return;
            }

            const element = document.querySelector(`.api-test[data-endpoint="${endpoint}"] .status-indicator`);
            if (!element) return;

            element.innerHTML = `<i class="fas fa-circle-notch fa-spin text-2xl text-primary mb-2"></i>`;

            // Penanganan khusus untuk endpoint tertentu
            let data = {};
            if (endpoint === 'train-models') {
                data = {
                    models: ['fecf'],
                    save_model: false,
                    force: false,
                    test_only: true  // Flag khusus untuk testing
                };
            } else if (endpoint === 'record-interaction') {
                data = {
                    user_id: 'test_user',
                    project_id: 'bitcoin',
                    interaction_type: 'view',
                    weight: 1,
                    test_only: true  // Flag khusus untuk testing
                };
            } else if (endpoint === 'sync-data') {
                data = {
                    projects_updated: false,
                    test_only: true  // Flag khusus untuk testing
                };
            }

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (response.ok) {
                    element.innerHTML = `<i class="fas fa-check-circle text-3xl text-success mb-2"></i>`;
                } else {
                    element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
                }
            })
            .catch(error => {
                element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
            });
        }

        // Tes untuk semua endpoint
        // 1. Core endpoints (aman)
        testEndpoint('root', `${apiUrl}/`, 'GET');
        testEndpoint('health', `${apiUrl}/health`, 'GET');

        // 2. Recommendation endpoints (aman untuk GET, hati-hati untuk POST)
        testEndpoint('trending', `${apiUrl}/recommend/trending?limit=5`, 'GET');
        testEndpoint('popular', `${apiUrl}/recommend/popular?limit=5`, 'GET');
        testEndpoint('similar', `${apiUrl}/recommend/similar/bitcoin?limit=5`, 'GET');

        // Endpoint yang bisa mengubah data - tandai sebagai destructive
        testEndpoint('projects', `${apiUrl}/recommend/projects`, 'POST', {
            user_id: 'user_1',
            model_type: 'fecf',
            num_recommendations: 5,
            test_only: true  // Flag khusus untuk testing
        }, true);

        // 3. Analysis endpoints (aman untuk GET)
        testEndpoint('market-events', `${apiUrl}/analysis/market-events/bitcoin?days=30`, 'GET');
        testEndpoint('alerts', `${apiUrl}/analysis/alerts/bitcoin?days=30`, 'GET');
        testEndpoint('price-prediction', `${apiUrl}/analysis/price-prediction/bitcoin?days=30`, 'GET');

        // Endpoint yang bisa berat prosesnya - tandai sebagai destructive
        testEndpoint('trading-signals', `${apiUrl}/analysis/trading-signals`, 'POST', {
            project_id: 'bitcoin',
            test_only: true // Flag khusus untuk testing
        }, true);
        testEndpoint('indicators', `${apiUrl}/analysis/indicators`, 'POST', {
            project_id: 'bitcoin',
            test_only: true // Flag khusus untuk testing
        }, true);

        // 4. NEW: Blockchain Analytics endpoints (aman untuk GET)
        testEndpoint('blockchain-portfolio', `${apiUrl}/blockchain/portfolio/0x1234567890123456789012345678901234567890`, 'GET');
        testEndpoint('blockchain-analytics', `${apiUrl}/blockchain/analytics/0x1234567890123456789012345678901234567890`, 'GET');
        testEndpoint('blockchain-transactions', `${apiUrl}/blockchain/transactions/0x1234567890123456789012345678901234567890?limit=10`, 'GET');
        testEndpoint('blockchain-health', `${apiUrl}/blockchain/health`, 'GET');

        // 5. Admin & data endpoints - SEMUA destructive
        testEndpoint('record-interaction', `${apiUrl}/interactions/record`, 'POST', null, true);
        testEndpoint('train-models', `${apiUrl}/admin/train-models`, 'POST', null, true);
        testEndpoint('sync-data', `${apiUrl}/admin/sync-data`, 'POST', null, true);
        testEndpoint('rec-cache-clear', `${apiUrl}/recommend/cache/clear`, 'POST', null, true);
        testEndpoint('analysis-cache-clear', `${apiUrl}/analysis/cache/clear`, 'POST', null, true);
        testEndpoint('blockchain-cache-clear', `${apiUrl}/blockchain/cache/clear`, 'POST', null, true);
    });
</script>
@endpush

@endsection
