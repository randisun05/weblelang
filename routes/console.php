<?php

use App\Services\Auction\LotCloser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('auctions:tick', function (LotCloser $closer) {
    $result = $closer->tick();
    $this->info("Lot dibuka: {$result['opened']}, lot ditutup: {$result['closed']}");
})->purpose('Buka & tutup lot lelang sesuai jadwal');

Schedule::command('auctions:tick')->everyMinute()->withoutOverlapping();
