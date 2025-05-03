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
                $successCount = 0;
                $failedCount  = 0;

                foreach ($csv as $row) {
                    if (count($row) !== count($headers)) {
                        $this->warn("Baris tidak valid, dilewati");
                        $failedCount++;
                        $bar->advance();
                        continue;
                    }

                    $record = array_combine($headers, $row);

                    try {
                        // PERBAIKAN: Proses fields JSON dengan benar
                        $jsonFields = ['platforms', 'categories', 'roi'];
                        foreach ($jsonFields as $field) {
                            if (isset($record[$field])) {
                                // Cek apakah field kosong atau "?"
                                if (empty($record[$field]) || $record[$field] === '?' || $record[$field] === '[]') {
                                    $record[$field] = null;
                                } else {
                                    // Parse JSON, jika gagal set null
                                    $jsonDecoded = json_decode($record[$field], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        // PERBAIKAN: Encode kembali ke JSON string untuk disimpan
                                        $record[$field] = json_encode($jsonDecoded);
                                    } else {
                                        $record[$field] = null;
                                    }
                                }
                            }
                        }

                        // Konversi nilai numerik yang kosong ke null
                        $numericFields = [
                            'fully_diluted_valuation', 'max_supply', 'sentiment_votes_up_percentage',
                            'telegram_channel_user_count', 'twitter_followers', 'github_stars',
                            'github_subscribers', 'github_forks', 'developer_activity_score',
                            'social_engagement_score', 'current_price', 'market_cap', 'market_cap_rank',
                            'total_volume', 'high_24h', 'low_24h', 'price_change_24h',
                            'price_change_percentage_24h', 'market_cap_change_24h',
                            'market_cap_change_percentage_24h', 'circulating_supply', 'total_supply',
                            'ath', 'ath_change_percentage', 'atl', 'atl_change_percentage',
                            'price_change_percentage_1h_in_currency', 'price_change_percentage_24h_in_currency',
                            'price_change_percentage_30d_in_currency', 'price_change_percentage_7d_in_currency',
                            'popularity_score', 'trend_score', 'developer_activity_score',
                            'social_engagement_score', 'description_length', 'age_days', 'maturity_score'
                        ];

                        foreach ($numericFields as $field) {
                            if (isset($record[$field])) {
                                if ($record[$field] === '' || $record[$field] === 'null' || $record[$field] === '?') {
                                    $record[$field] = null;
                                } else {
                                    $record[$field] = floatval($record[$field]);
                                }
                            }
                        }

                        // Konversi tanggal yang kosong ke null
                        $dateFields = ['genesis_date', 'ath_date', 'atl_date', 'last_updated'];
                        foreach ($dateFields as $field) {
                            if (isset($record[$field]) && ($record[$field] === '' || $record[$field] === 'null' || $record[$field] === '?')) {
                                $record[$field] = null;
                            }
                        }

                        // Konversi boolean fields
                        $booleanFields = ['is_trending'];
                        foreach ($booleanFields as $field) {
                            if (isset($record[$field])) {
                                $record[$field] = filter_var($record[$field], FILTER_VALIDATE_BOOLEAN);
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

            if ($shouldClearOldData) {
                DB::table('interactions')->truncate();
                $this->info('Interaksi lama dihapus');
            }

            // Gunakan transaksi untuk kecepatan
            DB::beginTransaction();

            try {
                $successCount = 0;
                $failedCount  = 0;
                $usersMissing = [];

                foreach ($csv as $row) {
                    if (count($row) !== count($headers)) {
                        $this->warn("Baris tidak valid, dilewati");
                        $failedCount++;
                        $bar->advance();
                        continue;
                    }

                    $record = array_combine($headers, $row);

                    try {
                        // PERBAIKAN: Cek apakah user ada, jika tidak buat
                        $userExists = DB::table('users')->where('user_id', $record['user_id'])->exists();

                        if (!$userExists) {
                            // Buat user baru jika tidak ada
                            DB::table('users')->insert([
                                'user_id' => $record['user_id'],
                                'wallet_address' => 'synthetic_' . $record['user_id'],
                                'role' => 'community',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $usersMissing[] = $record['user_id'];
                        }

                        // Cek apakah project ada
                        $projectExists = DB::table('projects')->where('id', $record['project_id'])->exists();

                        if (!$projectExists) {
                            Log::warning("Project tidak ditemukan: {$record['project_id']}");
                            $failedCount++;
                            $bar->advance();
                            continue;
                        }

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
                            if ($record['context'] === '?' || $record['context'] === '') {
                                $record['context'] = null;
                            } else {
                                $jsonContext = json_decode($record['context'], true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $record['context'] = json_encode($jsonContext);
                                } else {
                                    $record['context'] = null;
                                }
                            }
                        } else {
                            $record['context'] = null;
                        }

                        // Konversi weight ke integer
                        if (isset($record['weight'])) {
                            $record['weight'] = intval($record['weight']);
                        }

                        // Insert data
                        DB::table('interactions')->insert($record);
                        $successCount++;

                    } catch (\Exception $e) {
                        $this->error("Error processing interaction: " . $e->getMessage());
                        Log::error("Error processing interaction: " . $e->getMessage());
                        $failedCount++;
                    }

                    $bar->advance();
                }

                DB::commit();
                $bar->finish();
                $this->info("\nImport interaksi selesai!");
                $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

                if (!empty($usersMissing)) {
                    $this->info("Synthetic users yang dibuat: " . count($usersMissing));
                }

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
