<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tugas terjadwal. Jalankan penjadwal dengan cron (setiap menit):
|   * * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
| Waktu mengikuti Asia/Makassar (WITA).
*/

Schedule::command('broseven:backup')
    ->dailyAt('01:30')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('broseven.backup.enabled'));

Schedule::command('broseven:prune')
    ->dailyAt('02:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('02:30')
    ->timezone('Asia/Makassar');