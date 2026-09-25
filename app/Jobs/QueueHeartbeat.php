<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Dikirim scheduler tiap menit; bila worker hidup, cap waktu ini selalu baru (dipantau /health). */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('heartbeat:queue', now()->timestamp, now()->addHour());
    }
}
