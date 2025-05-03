<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRecommendationData extends Command
{
    protected $signature   = 'recommend:import {--projects} {--interactions} {--features} {--force}';
    protected $description = 'Impor data dari CSV yang dihasilkan engine rekomendasi';

    // Flag untuk mengetahui apakah command dijalankan dari web atau CLI
    protected $runningFromWeb = false;

    public function handle()
    {
        // Deteksi apakah dijalankan dari web atau CLI
        $this->runningFromWeb = ! defined('STDIN');

        if ($this->option('projects')) {
            $this->importProjects();
        }

        if ($this->option('interactions')) {
            $this->importInteractions();
        }

        if ($this->option('features')) {
            $this->importFeatures();
        }

        if (! $this->option('projects') && ! $this->option('interactions') && ! $this->option('features')) {
            $this->error('Harap tentukan data yang akan diimpor: --projects, --interactions, atau --features');
        }
    }

    protected function importProjects()
    {
        $csvPath = base_path('../recommendation-engine/data/processed/projects.csv');

        if (! file_exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");
            Log::error("Import projects failed: File tidak ditemukan: {$csvPath}");
            return;
        }

        $this->info("Mengimpor proyek dari {$csvPath}...");

        try {
            // Baca CSV dengan penanganan BOM
            $content = file_get_contents($csvPath);
            // Hapus BOM jika ada
            $bom     = pack('H*', 'EFBBBF');
            $content = preg_replace("/^$bom/", '', $content);

            // Tulis kembali content tanpa BOM ke temporary file
            $tempPath = sys_get_temp_dir() . '/temp_projects.csv';
            file_put_contents($tempPath, $content);

            // Baca CSV dari temporary file
            $csv     = array_map('str_getcsv', file($tempPath));
            $headers = array_shift($csv);

            // Clean up temporary file
            unlink($tempPath);

            $totalRows = count($csv);
            $this->info("Ditemukan {$totalRows} baris data");

            if ($totalRows === 0) {
                $this->warn("File CSV kosong!");
                return;
            }

            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            // Gunakan transaksi untuk kecepatan
            DB::beginTransaction();

            try {
                // Proses per batch
                $batchSize    = 100;
                $batches      = array_chunk($csv, $batchSize);
                $successCount = 0;
                $failedCount  = 0;

                foreach ($batches as $batch) {
                    foreach ($batch as $row) {
                        if (count($row) !== count($headers)) {
                            $this->warn("Baris tidak valid, dilewati");
                            $failedCount++;
                            continue;
                        }

                        $record = array_combine($headers, $row);

                        try {
                            // Proses fields JSON dengan penanganan yang lebih baik
                            $jsonFields = ['platforms', 'categories', 'roi'];
                            foreach ($jsonFields as $field) {
                                if (isset($record[$field])) {
                                    // Cek apakah field kosong atau "?"
                                    if (empty($record[$field]) || $record[$field] === '?' || $record[$field] === '[]') {
                                        $record[$field] = null;
                                    } else {
                                        // Parse JSON, jika gagal set null
                                        $jsonDecoded    = json_decode($record[$field], true);
                                        $record[$field] = json_last_error() === JSON_ERROR_NONE ? $jsonDecoded : null;
                                    }
                                }
                            }

                            // Konversi nilai numerik yang kosong ke null
                            $numericFields = [
                                'fully_diluted_valuation', 'max_supply', 'sentiment_votes_up_percentage',
                                'telegram_channel_user_count', 'twitter_followers', 'github_stars',
                                'github_subscribers', 'github_forks', 'developer_activity_score',
                                'social_engagement_score',
                            ];
                            foreach ($numericFields as $field) {
                                if (isset($record[$field]) && ($record[$field] === '' || $record[$field] === 'null')) {
                                    $record[$field] = null;
                                }
                            }

                            // Konversi tanggal yang kosong ke null
                            $dateFields = ['genesis_date', 'ath_date', 'atl_date'];
                            foreach ($dateFields as $field) {
                                if (isset($record[$field]) && ($record[$field] === '' || $record[$field] === 'null')) {
                                    $record[$field] = null;
                                }
                            }

                            // Pastikan field timestamp ada
                            $record['created_at'] = $record['created_at'] ?? now();
                            $record['updated_at'] = $record['updated_at'] ?? now();

                            // Insert atau update ke database
                            DB::table('projects')
                                ->updateOrInsert(
                                    ['id' => $record['id']],
                                    $record
                                );

                            $successCount++;
                        } catch (\Exception $e) {
                            $this->error("Error importing project {$record['id']}: " . $e->getMessage());
                            Log::error("Error importing project {$record['id']}: " . $e->getMessage());
                            $failedCount++;
                        }

                        $bar->advance();
                    }
                }

                DB::commit();
                $bar->finish();
                $this->info("\nImport proyek selesai!");
                $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("\nGagal mengimpor proyek: " . $e->getMessage());
                Log::error("Import projects error: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->error("Error membaca file CSV: " . $e->getMessage());
            Log::error("CSV reading error: " . $e->getMessage());
        }
    }

    protected function importInteractions()
    {
        $csvPath = base_path('../recommendation-engine/data/processed/interactions.csv');

        if (! file_exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");
            Log::error("Import interactions failed: File tidak ditemukan: {$csvPath}");
            return;
        }

        $this->info("Mengimpor interaksi dari {$csvPath}...");

        try {
            // Baca CSV dengan penanganan BOM
            $content = file_get_contents($csvPath);
            // Hapus BOM jika ada
            $bom     = pack('H*', 'EFBBBF');
            $content = preg_replace("/^$bom/", '', $content);

            // Tulis kembali content tanpa BOM ke temporary file
            $tempPath = sys_get_temp_dir() . '/temp_interactions.csv';
            file_put_contents($tempPath, $content);

            // Baca CSV dari temporary file
            $csv     = array_map('str_getcsv', file($tempPath));
            $headers = array_shift($csv);

            // Clean up temporary file
            unlink($tempPath);

            $totalRows = count($csv);
            $this->info("Ditemukan {$totalRows} baris data");

            if ($totalRows === 0) {
                $this->warn("File CSV kosong!");
                return;
            }

            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            // Hapus interaksi lama jika --force digunakan atau dijalankan dari web
            $shouldClearOldData = $this->option('force') || $this->runningFromWeb;

            if (! $shouldClearOldData && ! $this->runningFromWeb) {
                // Hanya tanya konfirmasi jika dari CLI dan tidak ada --force
                $shouldClearOldData = $this->confirm('Apakah Anda ingin menghapus semua interaksi yang ada sebelum mengimpor yang baru?', true);
            }

            if ($shouldClearOldData) {
                DB::table('interactions')->truncate();
                $this->info('Interaksi lama dihapus');
            }

            // Gunakan transaksi untuk kecepatan
            DB::beginTransaction();

            try {
                // Proses per batch
                $batchSize    = 500;
                $batches      = array_chunk($csv, $batchSize);
                $successCount = 0;
                $failedCount  = 0;

                foreach ($batches as $batch) {
                    $records = [];

                    foreach ($batch as $row) {
                        if (count($row) !== count($headers)) {
                            $this->warn("Baris tidak valid, dilewati");
                            $failedCount++;
                            continue;
                        }

                        $record = array_combine($headers, $row);

                        try {
                            // Konversi timestamp
                            if (isset($record['timestamp'])) {
                                $record['created_at'] = $record['timestamp'];
                                $record['updated_at'] = $record['timestamp'];
                                unset($record['timestamp']);
                            } else {
                                $record['created_at'] = $record['created_at'] ?? now();
                                $record['updated_at'] = $record['updated_at'] ?? now();
                            }

                            // Konversi context jika ada
                            if (isset($record['context']) && ! empty($record['context'])) {
                                $jsonContext       = json_decode($record['context'], true);
                                $record['context'] = json_last_error() === JSON_ERROR_NONE ? $jsonContext : null;
                            } else {
                                $record['context'] = null;
                            }

                            $records[] = $record;
                            $successCount++;
                        } catch (\Exception $e) {
                            $this->error("Error processing interaction: " . $e->getMessage());
                            Log::error("Error processing interaction: " . $e->getMessage());
                            $failedCount++;
                        }

                        $bar->advance();
                    }

                    // Insert batch
                    if (! empty($records)) {
                        DB::table('interactions')->insert($records);
                    }
                }

                DB::commit();
                $bar->finish();
                $this->info("\nImport interaksi selesai!");
                $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("\nGagal mengimpor interaksi: " . $e->getMessage());
                Log::error("Import interactions error: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->error("Error membaca file CSV: " . $e->getMessage());
            Log::error("CSV reading error: " . $e->getMessage());
        }
    }

    protected function importFeatures()
    {
        $this->info('Fitur impor features belum diimplementasikan');
        // Implementasikan jika diperlukan
    }
}
