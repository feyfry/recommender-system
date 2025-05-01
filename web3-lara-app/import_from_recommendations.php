<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap aplikasi Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

// Path ke file CSV dari engine rekomendasi
$projectsPath     = __DIR__ . '/../recommendation-engine/data/processed/projects.csv';
$interactionsPath = __DIR__ . '/../recommendation-engine/data/processed/interactions.csv';

// Import Projects
if (file_exists($projectsPath)) {
    echo "Mengimpor data proyek...\n";

    // Baca CSV
    $projects = array_map('str_getcsv', file($projectsPath));
    $headers  = array_shift($projects);

    // Hitung total untuk progress
    $total   = count($projects);
    $counter = 0;

    // Batch insert untuk kinerja yang lebih baik
    $batchSize = 100;
    $batches   = array_chunk($projects, $batchSize);

    foreach ($batches as $batch) {
        $data = [];
        foreach ($batch as $project) {
            $projectData = array_combine($headers, $project);

            // Konversi string JSON
            if (isset($projectData['platforms']) && ! empty($projectData['platforms'])) {
                $projectData['platforms'] = json_decode($projectData['platforms'], true);
            } else {
                $projectData['platforms'] = [];
            }

            if (isset($projectData['categories']) && ! empty($projectData['categories'])) {
                $projectData['categories'] = json_decode($projectData['categories'], true);
            } else {
                $projectData['categories'] = [];
            }

            // Tambahkan timestamp
            $projectData['created_at'] = now();
            $projectData['updated_at'] = now();

            $data[] = $projectData;
            $counter++;
        }

        // Insert ke database
        DB::table('projects')->insert($data);

        // Tampilkan progress
        echo "Diproses: {$counter} dari {$total} proyek\n";
    }

    echo "Selesai mengimpor proyek!\n";
} else {
    echo "File proyek tidak ditemukan: {$projectsPath}\n";
}

// Import Interactions
if (file_exists($interactionsPath)) {
    echo "Mengimpor data interaksi...\n";

    // Baca CSV
    $interactions = array_map('str_getcsv', file($interactionsPath));
    $headers      = array_shift($interactions);

    // Hitung total untuk progress
    $total   = count($interactions);
    $counter = 0;

    // Batch insert untuk kinerja yang lebih baik
    $batchSize = 500;
    $batches   = array_chunk($interactions, $batchSize);

    foreach ($batches as $batch) {
        $data = [];
        foreach ($batch as $interaction) {
            $interactionData = array_combine($headers, $interaction);

            // Konversi string JSON jika ada
            if (isset($interactionData['context']) && ! empty($interactionData['context'])) {
                $interactionData['context'] = json_decode($interactionData['context'], true);
            } else {
                $interactionData['context'] = null;
            }

            // Tambahkan timestamp
            if (! isset($interactionData['created_at'])) {
                $interactionData['created_at'] = now();
            }
            if (! isset($interactionData['updated_at'])) {
                $interactionData['updated_at'] = now();
            }

            $data[] = $interactionData;
            $counter++;
        }

        // Insert ke database
        DB::table('interactions')->insert($data);

        // Tampilkan progress
        echo "Diproses: {$counter} dari {$total} interaksi\n";
    }

    echo "Selesai mengimpor interaksi!\n";
} else {
    echo "File interaksi tidak ditemukan: {$interactionsPath}\n";
}

echo "Proses impor selesai!\n";
