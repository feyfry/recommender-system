<?php
// app/Console/Commands/ImportRecommendationData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRecommendationData extends Command
{
    protected $signature   = 'recommend:import {--projects} {--interactions} {--features}';
    protected $description = 'Impor data dari CSV yang dihasilkan engine rekomendasi';

    public function handle()
    {
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
        $csvPath = '../recommendation-engine/data/processed/projects.csv';

        if (! file_exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");
            return;
        }

        $this->info("Mengimpor proyek dari {$csvPath}...");

        // Baca CSV
        $csv     = array_map('str_getcsv', file($csvPath));
        $headers = array_shift($csv);

        $totalRows = count($csv);
        $this->info("Ditemukan {$totalRows} baris data");

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        // Gunakan transaksi untuk kecepatan
        DB::beginTransaction();

        try {
            // Proses per batch
            $batchSize = 100;
            $batches   = array_chunk($csv, $batchSize);

            foreach ($batches as $batch) {
                $records = [];

                foreach ($batch as $row) {
                    $record = array_combine($headers, $row);

                    // Konversi format data jika diperlukan
                    if (isset($record['platforms'])) {
                        $record['platforms'] = json_decode($record['platforms'], true) ?: [];
                    }

                    if (isset($record['categories'])) {
                        $record['categories'] = json_decode($record['categories'], true) ?: [];
                    }

                    // Pastikan field timestamp ada
                    $record['created_at'] = $record['created_at'] ?? now();
                    $record['updated_at'] = $record['updated_at'] ?? now();

                    $records[] = $record;
                    $bar->advance();
                }

                // Hapus dan insert
                foreach ($records as $record) {
                    DB::table('projects')
                        ->updateOrInsert(
                            ['id' => $record['id']],
                            $record
                        );
                }
            }

            DB::commit();
            $bar->finish();
            $this->info("\nImport proyek selesai!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nGagal mengimpor proyek: " . $e->getMessage());
        }
    }

    protected function importInteractions()
    {
        $csvPath = '../recommendation-engine/data/processed/interactions.csv';

        if (! file_exists($csvPath)) {
            $this->error("File tidak ditemukan: {$csvPath}");
            return;
        }

        $this->info("Mengimpor interaksi dari {$csvPath}...");

        // Baca CSV
        $csv     = array_map('str_getcsv', file($csvPath));
        $headers = array_shift($csv);

        $totalRows = count($csv);
        $this->info("Ditemukan {$totalRows} baris data");

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        // Hapus interaksi lama
        if ($this->confirm('Apakah Anda ingin menghapus semua interaksi yang ada sebelum mengimpor yang baru?', true)) {
            DB::table('interactions')->truncate();
            $this->info('Interaksi lama dihapus');
        }

        // Gunakan transaksi untuk kecepatan
        DB::beginTransaction();

        try {
            // Proses per batch
            $batchSize = 500;
            $batches   = array_chunk($csv, $batchSize);

            foreach ($batches as $batch) {
                $records = [];

                foreach ($batch as $row) {
                    $record = array_combine($headers, $row);

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
                        $record['context'] = json_decode($record['context'], true) ?: null;
                    }

                    $records[] = $record;
                    $bar->advance();
                }

                // Insert
                DB::table('interactions')->insert($records);
            }

            DB::commit();
            $bar->finish();
            $this->info("\nImport interaksi selesai!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nGagal mengimpor interaksi: " . $e->getMessage());
        }
    }

    protected function importFeatures()
    {
        $this->info('Fitur impor features belum diimplementasikan');
        // Implementasikan jika diperlukan
    }
}
