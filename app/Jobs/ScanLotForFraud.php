<?php

namespace App\Jobs;

use App\Models\Lot;
use App\Services\Security\FraudDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanLotForFraud implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $lotId)
    {
        $this->afterCommit();
    }

    public function handle(FraudDetector $detector): void
    {
        if ($lot = Lot::find($this->lotId)) {
            $detector->scanLot($lot);
        }
    }
}
