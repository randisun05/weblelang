<?php

use App\Jobs\QueueHeartbeat;
use App\Services\Auction\LotCloser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('auctions:tick', function (LotCloser $closer) {
    $result = $closer->tick();
    $this->info("Lot dibuka: {$result['opened']}, ditutup: {$result['closed']}, pengingat: {$result['reminded']}, invoice kedaluwarsa: {$result['expired']}, refund jaminan: {$result['refunded']}");
})->purpose('Buka/tutup lot, kirim pengingat, batalkan invoice lewat jatuh tempo');

Schedule::command('auctions:tick')->everyMinute()->withoutOverlapping();

// Heartbeat untuk /health: scheduler hidup & queue worker memproses job.
Schedule::call(fn () => Cache::put('heartbeat:scheduler', now()->timestamp, now()->addHour()))->everyMinute()->name('heartbeat');
Schedule::job(new QueueHeartbeat)->everyMinute();

// Backup harian terenkripsi (database + dokumen privat + foto), bersihkan arsip lama, pantau kesehatan backup.
if (config('backup.schedule_enabled', true)) {
    Schedule::command('backup:clean')->dailyAt('01:00');
    Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
    Schedule::command('backup:monitor')->dailyAt('07:00');
}
