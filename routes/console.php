<?php

use App\Services\Auction\LotCloser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('auctions:tick', function (LotCloser $closer) {
    $result = $closer->tick();
    $this->info("Lot dibuka: {$result['opened']}, ditutup: {$result['closed']}, pengingat: {$result['reminded']}, invoice kedaluwarsa: {$result['expired']}, refund jaminan: {$result['refunded']}");
})->purpose('Buka/tutup lot, kirim pengingat, batalkan invoice lewat jatuh tempo');

Schedule::command('auctions:tick')->everyMinute()->withoutOverlapping();
