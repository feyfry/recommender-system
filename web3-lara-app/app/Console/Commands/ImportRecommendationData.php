<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRecommendationData extends Command
{
    protected $signature   = 'recommend:import {--projects} {--interactions} {--features} {--force} {--debug}';
    protected $description = 'Impor data dari CSV yang dihasilkan engine rekomendasi';

    // Flag untuk mengetahui apakah command dijalankan dari web atau CLI
    protected $runningFromWeb = false;
    protected $debug          = false;

    public function handle()
    {
        // Deteksi apakah dijalankan dari web atau CLI
        $this->runningFromWeb = ! defined('STDIN');
        $this->debug          = $this->option('debug');

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

    // Mapping kolom dari CSV ke database
    protected function getColumnMapping()
    {
        return [
            'current_price'                           => 'price_usd',
            'total_volume'                            => 'volume_24h',
            'ath'                                     => 'all_time_high',
            'ath_date'                                => 'all_time_high_date',
            'atl'                                     => 'all_time_low',
            'atl_date'                                => 'all_time_low_date',
            'price_change_percentage_1h_in_currency'  => 'price_change_percentage_1h',
            'price_change_percentage_30d_in_currency' => 'price_change_percentage_30d',
            'price_change_percentage_7d_in_currency'  => 'price_change_percentage_7d',
            'sentiment_votes_up_percentage'           => 'sentiment_positive',
            // Mapping untuk interactions
            'timestamp'                               => 'created_at',
        ];
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
            $handle  = fopen($tempPath, 'r');
            $headers = fgetcsv($handle);

            $totalRows = 0;
            while (! feof($handle)) {
                if (fgetcsv($handle) !== false) {
                    $totalRows++;
                }
            }

            // Rewind file
            rewind($handle);
            fgetcsv($handle); // Skip header

            $this->info("Ditemukan {$totalRows} baris data");

            if ($totalRows === 0) {
                $this->warn("File CSV kosong!");
                return;
            }

            $bar = $this->output->createProgressBar($totalRows);
            $bar->start();

            $successCount  = 0;
            $failedCount   = 0;
            $columnMapping = $this->getColumnMapping();
            $rowNumber     = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    if ($this->debug) {
                        $this->warn("Baris {$rowNumber} tidak valid, header count: " . count($headers) . ", row count: " . count($row));
                    }
                    $failedCount++;
                    $bar->advance();
                    continue;
                }

                $record = array_combine($headers, $row);

                // Gunakan transaction terpisah untuk setiap record
                DB::beginTransaction();
                try {
                    // Lakukan mapping kolom
                    $mappedRecord = [];
                    foreach ($record as $key => $value) {
                        $newKey                = $columnMapping[$key] ?? $key;
                        $mappedRecord[$newKey] = $value;
                    }

                    // Proses fields JSON
                    $jsonFields = ['platforms', 'categories', 'roi'];
                    foreach ($jsonFields as $field) {
                        if (isset($mappedRecord[$field])) {
                            if (empty($mappedRecord[$field]) || $mappedRecord[$field] === '?' || $mappedRecord[$field] === '[]') {
                                $mappedRecord[$field] = null;
                            } else {
                                $jsonDecoded = json_decode($mappedRecord[$field], true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $mappedRecord[$field] = json_encode($jsonDecoded);
                                } else {
                                    $mappedRecord[$field] = null;
                                }
                            }
                        }
                    }

                    // Konversi nilai numerik yang kosong ke null
                    $numericFields = [
                        'fully_diluted_valuation', 'max_supply', 'sentiment_positive',
                        'telegram_channel_user_count', 'twitter_followers', 'github_stars',
                        'github_subscribers', 'github_forks', 'developer_activity_score',
                        'social_engagement_score', 'price_usd', 'market_cap',
                        'volume_24h', 'price_change_24h',
                        'price_change_percentage_24h', 'circulating_supply', 'total_supply',
                        'all_time_high', 'all_time_low',
                        'price_change_percentage_1h', 'price_change_percentage_24h',
                        'price_change_percentage_30d', 'price_change_percentage_7d',
                        'popularity_score', 'trend_score', 'developer_activity_score',
                        'social_engagement_score', 'maturity_score',
                    ];

                    foreach ($numericFields as $field) {
                        if (isset($mappedRecord[$field])) {
                            if ($mappedRecord[$field] === '' || $mappedRecord[$field] === 'null' || $mappedRecord[$field] === '?') {
                                $mappedRecord[$field] = null;
                            } else {
                                $mappedRecord[$field] = floatval($mappedRecord[$field]);
                            }
                        }
                    }

                    // Konversi tanggal yang kosong ke null
                    $dateFields = ['genesis_date', 'all_time_high_date', 'all_time_low_date'];
                    foreach ($dateFields as $field) {
                        if (isset($mappedRecord[$field]) && ($mappedRecord[$field] === '' || $mappedRecord[$field] === 'null' || $mappedRecord[$field] === '?')) {
                            $mappedRecord[$field] = null;
                        }
                    }

                    // Tambahkan field yang dibutuhkan database tapi tidak ada di CSV
                    $mappedRecord['created_at'] = now();
                    $mappedRecord['updated_at'] = now();

                    // Hapus field yang tidak ada di database
                    $fieldsToRemove = [
                        'market_cap_rank', 'high_24h', 'low_24h', 'market_cap_change_24h',
                        'market_cap_change_percentage_24h', 'ath_change_percentage',
                        'atl_change_percentage', 'last_updated', 'price_change_percentage_24h_in_currency',
                        'query_category', 'description_length', 'age_days', 'is_trending',
                    ];

                    foreach ($fieldsToRemove as $field) {
                        unset($mappedRecord[$field]);
                    }

                    // Insert atau update ke database
                    DB::table('projects')
                        ->updateOrInsert(
                            ['id' => $mappedRecord['id']],
                            $mappedRecord
                        );

                    DB::commit();
                    $successCount++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    if ($this->debug) {
                        $this->error("Error importing project {$record['id']}: " . $e->getMessage());
                    }
                    Log::error("Error importing project {$record['id']}: " . $e->getMessage());
                    $failedCount++;
                }

                $bar->advance();
            }

            fclose($handle);
            unlink($tempPath);

            $bar->finish();
            $this->info("\nImport proyek selesai!");
            $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

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

            $successCount = 0;
            $failedCount  = 0;
            $usersMissing = [];
            $projectsMissing = [];
            $rowNumber = 0;

            foreach ($csv as $row) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    if ($this->debug) {
                        $this->warn("Baris {$rowNumber} tidak valid, headers: " . count($headers) . ", row: " . count($row));
                    }
                    $failedCount++;
                    $bar->advance();
                    continue;
                }

                $record = array_combine($headers, $row);

                DB::beginTransaction();
                try {
                    // Cek apakah user ada
                    $userExists = DB::table('users')->where('user_id', $record['user_id'])->exists();

                    if (!$userExists) {
                        // Buat user baru TANPA prefix synthetic_
                        DB::table('users')->insert([
                            'user_id' => $record['user_id'],
                            'wallet_address' => $record['user_id'] . '_wallet', // Gunakan format yang lebih sederhana
                            'role' => 'community',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $usersMissing[] = $record['user_id'];
                    }

                    // Cek apakah project ada
                    $projectExists = DB::table('projects')->where('id', $record['project_id'])->exists();

                    if (!$projectExists) {
                        // Kita punya 2 opsi:
                        // Opsi 1: Skip interaksi ini
                        if (!$this->option('create-missing-projects')) {
                            if ($this->debug) {
                                $this->warn("Project tidak ditemukan: {$record['project_id']}");
                            }

                            if (!in_array($record['project_id'], $projectsMissing)) {
                                $projectsMissing[] = $record['project_id'];
                            }

                            Log::warning("Project tidak ditemukan: {$record['project_id']}");
                            $failedCount++;
                            $bar->advance();
                            DB::rollBack();
                            continue;
                        }

                        // Opsi 2: Buat project placeholder (jika opsi diaktifkan)
                        else {
                            DB::table('projects')->insert([
                                'id' => $record['project_id'],
                                'name' => 'Unknown ' . $record['project_id'],
                                'symbol' => strtoupper(substr($record['project_id'], 0, 4)),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $projectsMissing[] = $record['project_id'];
                        }
                    }

                    // Mapping timestamp ke created_at & updated_at
                    if (isset($record['timestamp'])) {
                        $record['created_at'] = $record['timestamp'];
                        $record['updated_at'] = $record['timestamp'];
                        unset($record['timestamp']);
                    } else {
                        $record['created_at'] = now();
                        $record['updated_at'] = now();
                    }

                    // Set default values untuk field yang tidak ada di CSV
                    $record['context'] = null;
                    $record['session_id'] = null;

                    // Konversi weight ke integer
                    if (isset($record['weight'])) {
                        $record['weight'] = intval($record['weight']);
                    }

                    // Insert data
                    DB::table('interactions')->insert($record);
                    DB::commit();
                    $successCount++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    if ($this->debug) {
                        $this->error("Error processing interaction row {$rowNumber}: " . $e->getMessage());
                    }
                    Log::error("Error processing interaction: " . $e->getMessage());
                    $failedCount++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->info("\nImport interaksi selesai!");
            $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

            if (!empty($usersMissing)) {
                $this->info("Users yang dibuat: " . count($usersMissing));
            }

            if (!empty($projectsMissing)) {
                $this->info("Projects yang tidak ditemukan: " . count($projectsMissing));
                if ($this->debug) {
                    $this->info("Project IDs: " . implode(', ', array_slice($projectsMissing, 0, 10)) . (count($projectsMissing) > 10 ? '...' : ''));
                }
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
