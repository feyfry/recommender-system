<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncRecommendationData extends Command
{
    protected $signature   = 'recommend:sync {--full} {--projects} {--interactions} {--train}';
    protected $description = 'Sinkronisasi data dengan engine rekomendasi';
    protected $apiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->apiUrl = env('RECOMMENDATION_API_URL', 'http://localhost:8001');
    }

    public function handle()
    {
        $startTime = now();
        $this->info('Memulai sinkronisasi dengan engine rekomendasi...');

        // Sinkronisasi proyek jika diminta
        if ($this->option('full') || $this->option('projects')) {
            $this->syncProjects();
        }

        // Sinkronisasi interaksi jika diminta
        if ($this->option('full') || $this->option('interactions')) {
            $this->syncInteractions();
        }

        // Latih model jika diminta
        if ($this->option('full') || $this->option('train')) {
            $this->trainModels();
        }

        $duration = now()->diffInSeconds($startTime);
        $this->info("Sinkronisasi selesai dalam {$duration} detik!");
    }

    protected function syncProjects()
    {
        $this->info('Meminta engine rekomendasi mengekspor proyek...');

        try {
            // Gunakan API endpoint untuk memicu export data proyek
            $response = Http::post("{$this->apiUrl}/admin/sync-data", [
                'projects_updated' => true,
                'users_count'      => DB::table('users')->count(),
            ]);

            if ($response->successful()) {
                $this->info('Data proyek berhasil disinkronkan!');

                // Import CSV yang baru diekspor
                $this->call('recommend:import', ['--projects' => true]);
            } else {
                $this->error('Gagal memicu sinkronisasi proyek: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            Log::error('Sinkronisasi proyek gagal: ' . $e->getMessage());
        }
    }

    /**
     * PERBAIKAN: Sinkronisasi interaksi dengan format timestamp yang konsisten
     */
    protected function syncInteractions()
    {
        $this->info('Mengekspor interaksi ke engine rekomendasi...');

        try {
            // Ambil semua interaksi dari database dengan distinct untuk menghindari duplikasi
            $interactions = DB::table('interactions')
                ->select(['user_id', 'project_id', 'interaction_type', 'weight', 'context', 'created_at'])
                ->distinct()
                ->orderBy('created_at')
                ->get()
                ->toArray();

            $this->info('Ditemukan ' . count($interactions) . ' interaksi untuk diekspor');

            // Konversi ke CSV dan simpan di direktori sementara
            $csvPath = storage_path('app/temp_interactions.csv');
            $file    = fopen($csvPath, 'w');

            // Tulis header
            fputcsv($file, ['user_id', 'project_id', 'interaction_type', 'weight', 'context', 'timestamp']);

            // PERBAIKAN: Track interaksi yang sudah ditulis untuk menghindari duplikasi
            $exportedInteractions = [];
            $duplicateCount       = 0;

            foreach ($interactions as $interaction) {
                // Buat unique key berdasarkan data penting (tanpa timestamp untuk menghindari masalah timezone)
                $uniqueKey = "{$interaction->user_id}:{$interaction->project_id}:{$interaction->interaction_type}";

                // PERBAIKAN: Cek duplikasi berdasarkan key yang sama dalam rentang waktu dekat
                $interactionTime = strtotime($interaction->created_at);
                $isDuplicate     = false;

                foreach ($exportedInteractions as $existingKey => $existingTime) {
                    if (strpos($existingKey, $uniqueKey) === 0) {
                        // Jika ada interaksi serupa dalam 60 detik, anggap duplikat
                        if (abs($interactionTime - $existingTime) < 60) {
                            $isDuplicate = true;
                            $duplicateCount++;
                            break;
                        }
                    }
                }

                // Skip jika duplikat
                if ($isDuplicate) {
                    continue;
                }

                // PERBAIKAN: Format timestamp yang konsisten dengan engine
                // Gunakan format Y-m-d\TH:i:s.u (dengan microseconds, tanpa timezone)
                $carbonDate = \Carbon\Carbon::parse($interaction->created_at);
                $timestamp  = $carbonDate->format('Y-m-d\TH:i:s.u');

                fputcsv($file, [
                    $interaction->user_id,
                    $interaction->project_id,
                    $interaction->interaction_type,
                    $interaction->weight,
                    is_string($interaction->context) ? $interaction->context : json_encode($interaction->context),
                    $timestamp, // PERBAIKAN: Format konsisten dengan microseconds
                ]);

                // Simpan interaksi yang sudah diekspor dengan timestamp
                $exportedInteractions[$uniqueKey . ':' . $timestamp] = $interactionTime;
            }

            fclose($file);

            if ($duplicateCount > 0) {
                $this->warn("Ditemukan dan dihapus {$duplicateCount} interaksi duplikat");
            }

            // Kirim file ini ke engine rekomendasi
            $this->info('Interaksi berhasil diekspor ke: ' . $csvPath);
            $this->info('Sekarang menyalin file ke direktori engine rekomendasi...');

            // Salin ke direktori engine rekomendasi
            $targetPath = base_path('../recommendation-engine/data/processed/interactions.csv');
            copy($csvPath, $targetPath);

            $this->info('File interaksi berhasil disalin ke engine rekomendasi');

            // Hapus file temporary
            unlink($csvPath);

        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            Log::error('Sinkronisasi interaksi gagal: ' . $e->getMessage());
        }
    }

    protected function trainModels()
    {
        $this->info('Melatih model di engine rekomendasi...');

        try {
            // Gunakan API endpoint untuk melatih model
            $response = Http::post("{$this->apiUrl}/admin/train-models", [
                'models'     => ['fecf', 'ncf', 'hybrid'],
                'save_model' => true,
            ]);

            if ($response->successful()) {
                $this->info('Model berhasil dilatih!');
            } else {
                $this->error('Gagal melatih model: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            Log::error('Pelatihan model gagal: ' . $e->getMessage());
        }
    }
}
