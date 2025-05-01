<?php
// app/Console/Commands/SyncRecommendationData.php

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

    protected function syncInteractions()
    {
        $this->info('Mengekspor interaksi ke engine rekomendasi...');

        try {
            // Ambil semua interaksi dari database
            $interactions = DB::table('interactions')
                ->orderBy('created_at')
                ->get(['user_id', 'project_id', 'interaction_type', 'weight', 'context', 'created_at'])
                ->toArray();

            $this->info('Ditemukan ' . count($interactions) . ' interaksi untuk diekspor');

            // Konversi ke CSV dan simpan di direktori sementara
            $csvPath = storage_path('app/temp_interactions.csv');
            $file    = fopen($csvPath, 'w');

            // Tulis header
            fputcsv($file, ['user_id', 'project_id', 'interaction_type', 'weight', 'context', 'timestamp']);

            // Tulis data
            foreach ($interactions as $interaction) {
                fputcsv($file, [
                    $interaction->user_id,
                    $interaction->project_id,
                    $interaction->interaction_type,
                    $interaction->weight,
                    is_string($interaction->context) ? $interaction->context : json_encode($interaction->context),
                    $interaction->created_at,
                ]);
            }

            fclose($file);

            // Kirim file ini ke engine rekomendasi
            // Implementasi pengiriman file bisa melalui API atau dengan menyalin file
            $this->info('Interaksi berhasil diekspor ke: ' . $csvPath);
            $this->info('Sekarang menyalin file ke direktori engine rekomendasi...');

            // Salin ke direktori engine rekomendasi
            $targetPath = '../recommendation-engine/data/processed/interactions.csv';
            copy($csvPath, $targetPath);

            $this->info('File interaksi berhasil disalin ke engine rekomendasi');
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
