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

        <!-- API Cache Stats -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-database mr-2 text-primary"></i>
                Statistik Cache
            </h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Cache:</span>
                    <span class="font-bold">{{ $cacheStats['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Cache Valid:</span>
                    <span class="font-bold">{{ $cacheStats['valid'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Cache Kadaluwarsa:</span>
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

    <!-- API Connection Test -->
    <div class="clay-card p-6 mb-8">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-plug mr-2 text-info"></i>
            Tes Koneksi API
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="clay-card bg-primary/10 p-4 text-center api-test" data-endpoint="trending">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-primary mb-2"></i>
                </div>
                <div class="font-bold">Trending</div>
                <p class="text-sm mt-1">GET /recommend/trending</p>
            </div>

            <div class="clay-card bg-success/10 p-4 text-center api-test" data-endpoint="popular">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-success mb-2"></i>
                </div>
                <div class="font-bold">Popular</div>
                <p class="text-sm mt-1">GET /recommend/popular</p>
            </div>

            <div class="clay-card bg-warning/10 p-4 text-center api-test" data-endpoint="projects">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-warning mb-2"></i>
                </div>
                <div class="font-bold">Recommendations</div>
                <p class="text-sm mt-1">POST /recommend/projects</p>
            </div>

            <div class="clay-card bg-secondary/10 p-4 text-center api-test" data-endpoint="trading-signals">
                <div class="status-indicator">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-secondary mb-2"></i>
                </div>
                <div class="font-bold">Trading Signals</div>
                <p class="text-sm mt-1">POST /analysis/trading-signals</p>
            </div>
        </div>
    </div>

    <!-- Sync Options -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Sync Data -->
        <div class="clay-card p-6">
            <h2 class="text-lg font-bold mb-4 flex items-center">
                <i class="fas fa-sync mr-2 text-success"></i>
                Sinkronisasi Data
            </h2>
            <p class="text-sm text-gray-600 mb-4">
                Sinkronisasi data antara database Laravel dan engine rekomendasi Python
                melalui file CSV yang di-sharing.
            </p>
            <div class="clay-card bg-success/5 p-4 mb-4">
                <h3 class="font-bold mb-2">Command Artisan yang Tersedia:</h3>
                <div class="space-y-2 font-mono text-xs">
                    <div class="clay-card bg-success/10 p-2">
                        php artisan recommend:sync --projects
                    </div>
                    <div class="clay-card bg-success/10 p-2">
                        php artisan recommend:sync --interactions
                    </div>
                    <div class="clay-card bg-success/10 p-2">
                        php artisan recommend:sync --train
                    </div>
                    <div class="clay-card bg-success/10 p-2">
                        php artisan recommend:sync --full
                    </div>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="document.getElementById('sync-data-modal').classList.remove('hidden')" class="clay-button clay-button-success">
                    <i class="fas fa-sync mr-2"></i> Sinkronisasi Data
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
                        <div>NDCG: 0.2945</div>
                        <div>Hit Ratio: 0.8148</div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-2">
                        <div class="font-bold">NCF</div>
                        <div>NDCG: 0.1986</div>
                        <div>Hit Ratio: 0.7138</div>
                    </div>
                    <div class="clay-card bg-secondary/10 p-2">
                        <div class="font-bold">Hybrid</div>
                        <div>NDCG: 0.2954</div>
                        <div>Hit Ratio: 0.8788</div>
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
            <i class="fas fa-database mr-2 text-primary"></i>
            Daftar Endpoint Cache
        </h2>

        <div class="overflow-x-auto">
            <table class="clay-table min-w-full">
                <thead>
                    <tr>
                        <th class="py-3 px-4 text-left">Endpoint</th>
                        <th class="py-3 px-4 text-left">Jumlah Cache</th>
                        <th class="py-3 px-4 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($endpointUsage ?? [] as $endpoint)
                    <tr>
                        <td class="py-3 px-4 font-medium">{{ $endpoint->endpoint }}</td>
                        <td class="py-3 px-4">{{ $endpoint->count }}</td>
                        <td class="py-3 px-4">
                            <form action="{{ route('admin.clear-api-cache') }}" method="POST" class="inline-block">
                                @csrf
                                <input type="hidden" name="endpoint" value="{{ $endpoint->endpoint }}">
                                <button type="submit" class="clay-badge clay-badge-danger py-1 px-2 text-xs">
                                    <i class="fas fa-trash-alt"></i> Hapus Cache
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="py-6 px-4 text-center">
                            <p class="text-gray-500">Tidak ada data cache yang tersedia.</p>
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
                    <form action="{{ url('panel/admin/import-command') }}" method="POST">
                        @csrf
                        <input type="hidden" name="command" value="recommend:import --projects">
                        <button type="submit" class="clay-button clay-button-primary py-1.5 px-3 text-sm">
                            Import Proyek
                        </button>
                    </form>
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
                    <form action="{{ url('panel/admin/import-command') }}" method="POST">
                        @csrf
                        <input type="hidden" name="command" value="recommend:import --interactions">
                        <button type="submit" class="clay-button clay-button-warning py-1.5 px-3 text-sm">
                            Import Interaksi
                        </button>
                    </form>
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
                    <form action="{{ url('panel/admin/import-command') }}" method="POST">
                        @csrf
                        <input type="hidden" name="command" value="recommend:sync --full">
                        <button type="submit" class="clay-button clay-button-info py-1.5 px-3 text-sm">
                            Export Semua Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CRON Jobs Configuration -->
    <div class="clay-card p-6">
        <h2 class="text-xl font-bold mb-6 flex items-center">
            <i class="fas fa-clock mr-2 text-info"></i>
            Jadwal Tugas Otomatis (CRON Jobs)
        </h2>

        <div class="clay-card bg-info/10 p-6 mb-6">
            <h3 class="font-bold mb-3">Konfigurasi CRON Jobs</h3>
            <p class="text-sm mb-4">
                Jadwal tugas otomatis berikut sudah dikonfigurasi pada sistem:
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
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                recommend:sync --projects
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">12 jam sekali</span>
                            </td>
                            <td class="py-2 px-4">Sinkronisasi data proyek</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                recommend:sync --interactions
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">4 jam sekali</span>
                            </td>
                            <td class="py-2 px-4">Sinkronisasi interaksi pengguna</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                recommend:sync --train
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">03:00 setiap hari</span>
                            </td>
                            <td class="py-2 px-4">Melatih model rekomendasi</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-4 font-mono text-xs">
                                cache:api-clear --expired
                            </td>
                            <td class="py-2 px-4">
                                <span class="clay-badge clay-badge-info">Setiap jam</span>
                            </td>
                            <td class="py-2 px-4">Membersihkan cache API kadaluwarsa</td>
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
            <h3 class="text-xl font-bold">Hapus Cache API</h3>
        </div>
        <form action="{{ route('admin.clear-api-cache') }}" method="POST">
            @csrf
            <div class="clay-modal-body">
                <div class="space-y-4">
                    <p>Pilih opsi pembersihan cache:</p>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-all" name="cache_option" value="all" class="clay-checkbox" checked>
                        <label for="cache-all">Semua Cache</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-expired" name="cache_option" value="expired" class="clay-checkbox">
                        <label for="cache-expired">Hanya Cache Kadaluwarsa</label>
                    </div>

                    <div class="clay-checkbox-container">
                        <input type="radio" id="cache-maintenance" name="cache_option" value="maintenance" class="clay-checkbox">
                        <label for="cache-maintenance">Maintenance Cache</label>
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

    // API Connection Test
    document.addEventListener('DOMContentLoaded', function() {
        const apiUrl = "{{ env('RECOMMENDATION_API_URL', 'http://localhost:8001') }}";
        const tests = [
            {
                endpoint: 'popular',
                url: `${apiUrl}/recommend/popular`,
                method: 'GET',
                data: { limit: 1 }
            },
            {
                endpoint: 'projects',
                url: `${apiUrl}/recommend/projects`,
                method: 'POST',
                data: { user_id: 'test_user', model_type: 'hybrid', num_recommendations: 1 }
            },
            {
                endpoint: 'trading-signals',
                url: `${apiUrl}/analysis/trading-signals`,
                method: 'POST',
                data: { project_id: 'bitcoin', days: 30, interval: '1d', risk_tolerance: 'medium' }
            }
        ];

        tests.forEach(test => {
            const element = document.querySelector(`.api-test[data-endpoint="${test.endpoint}"] .status-indicator`);

            fetch(test.url, {
                method: test.method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: test.method === 'POST' ? JSON.stringify(test.data) : undefined
            })
            .then(response => {
                if (response.ok) {
                    element.innerHTML = `<i class="fas fa-check-circle text-3xl text-success mb-2"></i>`;
                } else {
                    element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
                }
                return response.json();
            })
            .catch(error => {
                console.error(`Error testing ${test.endpoint}:`, error);
                element.innerHTML = `<i class="fas fa-exclamation-circle text-3xl text-danger mb-2"></i>`;
            });
        });
    });
</script>
@endpush

@endsection
