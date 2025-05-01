<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('recommend:sync --projects')
    ->twiceDaily(1, 13)
    ->description('Sinkronisasi data proyek dari engine rekomendasi');

Schedule::command('recommend:sync --interactions')
    ->everyFourHours()
    ->description('Sinkronisasi interaksi pengguna dengan engine rekomendasi');

Schedule::command('recommend:sync --train')
    ->dailyAt('03:00')
    ->description('Melatih model rekomendasi');

Schedule::command('cache:api-clear --expired')
    ->hourly()
    ->description('Bersihkan cache API yang kadaluwarsa');
