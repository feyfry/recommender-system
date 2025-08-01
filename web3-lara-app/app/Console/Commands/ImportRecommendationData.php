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

    protected function importProjects()
    {
        $csvPath = base_path('../recommendation-engine/data/processed/projects.csv');

        if (! file_exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");
            Log::error("Import projects failed: File tidak ditemukan: {$csvPath}");
            return;
        }

        $this->info("Mengimpor proyek dari {$csvPath}...");

        // Simpan proyek yang gagal untuk dicoba ulang nanti
        $failedProjects = [];

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

            $successCount = 0;
            $failedCount  = 0;
            $rowNumber    = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    if ($this->debug) {
                        $this->warn("Baris {$rowNumber} tidak valid, header count: " . count($headers) . ", row count: " . count($row));
                    }
                    $failedProjects["row_{$rowNumber}"] = "Header count mismatch";
                    $failedCount++;
                    $bar->advance();
                    continue;
                }

                $record    = array_combine($headers, $row);
                $projectId = $record['id'] ?? "unknown_row_{$rowNumber}";

                // Gunakan transaction terpisah untuk setiap record
                DB::beginTransaction();
                try {
                    // Tidak perlu truncate string fields lagi karena sudah TEXT

                    // Handle ROI field
                    if (isset($record['roi'])) {
                        if (empty($record['roi']) || $record['roi'] === '?') {
                            $record['roi'] = null;
                        } else {
                            // Hapus quotes yang mungkin ada di sekitar JSON
                            $roiData = trim($record['roi'], '"\'');
                            // Ganti single quotes dengan double quotes untuk JSON valid
                            $roiData = str_replace("'", '"', $roiData);

                            $jsonDecoded = json_decode($roiData, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $record['roi'] = json_encode($jsonDecoded);
                            } else {
                                if ($this->debug) {
                                    $this->warn("Invalid ROI JSON in row {$rowNumber}: " . $record['roi']);
                                }
                                $record['roi'] = null;
                            }
                        }
                    }

                    // Proses fields JSON lainnya
                    $jsonFields = ['platforms', 'categories'];
                    foreach ($jsonFields as $field) {
                        if (isset($record[$field])) {
                            if (empty($record[$field]) || $record[$field] === '?' || $record[$field] === '[]') {
                                $record[$field] = null;
                            } else {
                                // Hapus quotes yang mungkin ada di sekitar JSON
                                $jsonData = trim($record[$field], '"');
                                if ($field === 'platforms') {
                                    $jsonData = str_replace('""', '"', $jsonData);
                                }

                                $jsonDecoded = json_decode($jsonData, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $record[$field] = json_encode($jsonDecoded);
                                } else {
                                    if ($this->debug) {
                                        $this->warn("Invalid {$field} JSON in row {$rowNumber}: " . $record[$field]);
                                    }
                                    $record[$field] = null;
                                }
                            }
                        }
                    }

                    // Handle numeric overflow
                    $numericFields = [
                        'current_price', 'market_cap', 'market_cap_rank', 'fully_diluted_valuation',
                        'total_volume', 'high_24h', 'low_24h', 'price_change_24h',
                        'price_change_percentage_24h', 'market_cap_change_24h',
                        'market_cap_change_percentage_24h', 'circulating_supply',
                        'total_supply', 'max_supply', 'ath', 'ath_change_percentage',
                        'atl', 'atl_change_percentage', 'price_change_percentage_1h_in_currency',
                        'price_change_percentage_24h_in_currency', 'price_change_percentage_30d_in_currency',
                        'price_change_percentage_7d_in_currency', 'twitter_followers',
                        'github_stars', 'github_subscribers', 'github_forks',
                        'sentiment_votes_up_percentage', 'telegram_channel_user_count',
                        'popularity_score', 'trend_score', 'developer_activity_score',
                        'social_engagement_score', 'description_length', 'age_days',
                        'maturity_score'
                    ];

                    foreach ($numericFields as $field) {
                        if (isset($record[$field])) {
                            if ($record[$field] === '' || $record[$field] === 'null' || $record[$field] === '?') {
                                $record[$field] = null;
                            } else {
                                $value = floatval($record[$field]);
                                // Check untuk numeric overflow
                                if ($value > 9999999999999999999.9999999999) {
                                    $record[$field] = 9999999999999999999.9999999999;
                                } elseif ($value < -9999999999999999999.9999999999) {
                                    $record[$field] = -9999999999999999999.9999999999;
                                } else {
                                    $record[$field] = $value;
                                }
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

                    // Konversi boolean field
                    if (isset($record['is_trending'])) {
                        $record['is_trending'] = filter_var($record['is_trending'], FILTER_VALIDATE_BOOLEAN);
                    }

                    // Tambahkan field yang dibutuhkan database tapi tidak ada di CSV
                    $record['created_at'] = now();
                    $record['updated_at'] = now();

                    // Debug: tampilkan data yang akan diinsert jika ada error
                    if ($this->debug) {
                        $this->info("Processing project: {$projectId}");
                    }

                    // Insert atau update ke database
                    DB::table('projects')
                        ->updateOrInsert(
                            ['id' => $record['id']],
                            $record
                        );

                    DB::commit();
                    $successCount++;
                } catch (\Exception $e) {
                    DB::rollBack();

                    // Simpan detail error yang lebih lengkap
                    $errorMessage = $e->getMessage();
                    $errorClass = get_class($e);
                    $errorTrace = $e->getTraceAsString();

                    $failedProjects[$projectId] = [
                        'error' => $errorMessage,
                        'type' => $errorClass,
                        'row' => $rowNumber,
                        'data' => $record // Simpan data yang bermasalah untuk debug
                    ];

                    // Log error yang lebih detail
                    Log::error("Error importing project {$projectId} (row {$rowNumber}): " . $errorMessage, [
                        'error_type' => $errorClass,
                        'project_id' => $projectId,
                        'row_number' => $rowNumber,
                        'data' => $record,
                        'trace' => $errorTrace
                    ]);

                    if ($this->debug) {
                        $this->error("Error importing project {$projectId} (row {$rowNumber}): " . $errorMessage);
                        $this->error("Error type: " . $errorClass);
                    }

                    $failedCount++;
                }

                $bar->advance();
            }

            fclose($handle);
            unlink($tempPath);

            $bar->finish();
            $this->info("\nImport proyek selesai!");
            $this->info("Berhasil: {$successCount}, Gagal: {$failedCount}");

            // Tampilkan detail proyek yang gagal jika ada
            if (!empty($failedProjects)) {
                $this->warn("\nProyek yang gagal diimport:");
                foreach ($failedProjects as $id => $errorData) {
                    if (is_array($errorData)) {
                        $this->warn("- {$id} (row {$errorData['row']}): {$errorData['error']}");
                        if ($this->debug) {
                            $this->warn("  Type: {$errorData['type']}");
                            $this->warn("  Data: " . json_encode($errorData['data'], JSON_PRETTY_PRINT));
                        }
                    } else {
                        $this->warn("- {$id}: {$errorData}");
                    }
                }
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

        $importedInteractions = [];

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

            $successCount    = 0;
            $failedCount     = 0;
            $usersMissing    = [];
            $projectsMissing = [];
            $rowNumber       = 0;

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

                // Buat unique key untuk deteksi duplikasi
                $uniqueKey = "{$record['user_id']}:{$record['project_id']}:{$record['interaction_type']}:{$record['timestamp']}";

                // Skip jika sudah pernah diimport
                if (isset($importedInteractions[$uniqueKey])) {
                    if ($this->debug) {
                        $this->warn("Duplikasi interaksi ditemukan, skip: {$uniqueKey}");
                    }
                    continue;
                }

                DB::beginTransaction();
                try {
                    // Cek apakah user ada
                    $userExists = DB::table('users')->where('user_id', $record['user_id'])->exists();

                    if (! $userExists) {
                        // Buat user baru TANPA prefix synthetic_
                        DB::table('users')->insert([
                            'user_id'        => $record['user_id'],
                            'wallet_address' => $record['user_id'] . '_wallet', // Gunakan format yang lebih sederhana
                            'role'           => 'community',
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);

                        $usersMissing[] = $record['user_id'];
                    }

                    // Cek apakah project ada
                    $projectExists = DB::table('projects')->where('id', $record['project_id'])->exists();

                    if (! $projectExists) {
                        // Kita punya 2 opsi:
                        // Opsi 1: Skip interaksi ini
                        if (! $this->option('create-missing-projects')) {
                            if ($this->debug) {
                                $this->warn("Project tidak ditemukan: {$record['project_id']}");
                            }

                            if (! in_array($record['project_id'], $projectsMissing)) {
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
                                'id'         => $record['project_id'],
                                'name'       => 'Unknown ' . $record['project_id'],
                                'symbol'     => strtoupper(substr($record['project_id'], 0, 4)),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $projectsMissing[] = $record['project_id'];
                        }
                    }

                    // Cek jika interaction sudah ada untuk menghindari duplikasi
                    $existingInteraction = DB::table('interactions')
                        ->where('user_id', $record['user_id'])
                        ->where('project_id', $record['project_id'])
                        ->where('interaction_type', $record['interaction_type'])
                        ->where('created_at', $record['timestamp'] ?? now())
                        ->exists();

                    if ($existingInteraction) {
                        if ($this->debug) {
                            $this->warn("Interaction sudah ada, skip: {$uniqueKey}");
                        }
                        DB::rollBack();
                        continue;
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
                    $record['context']    = null;
                    $record['session_id'] = null;

                    // Konversi weight ke integer
                    if (isset($record['weight'])) {
                        $record['weight'] = intval($record['weight']);
                    }

                    // Insert data
                    DB::table('interactions')->insert($record);
                    DB::commit();
                    $importedInteractions[$uniqueKey] = true;
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

            if (! empty($usersMissing)) {
                $this->info("Users yang dibuat: " . count($usersMissing));
            }

            if (! empty($projectsMissing)) {
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
