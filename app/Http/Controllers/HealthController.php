<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Endpoint untuk uptime monitor (UptimeRobot, Better Stack, dsb.).
 * Hanya mengembalikan ok/fail per komponen — tanpa detail yang bisa bocor.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->safely(fn () => DB::select('select 1') !== []),
            'cache' => $this->safely(function () {
                Cache::put('health:probe', $v = random_int(1, PHP_INT_MAX), 60);

                return Cache::get('health:probe') === $v;
            }),
            // Scheduler menulis cap waktu tiap menit, queue worker memproses job heartbeat tiap menit.
            'scheduler' => $this->fresh('heartbeat:scheduler', 180),
            'queue' => $this->fresh('heartbeat:queue', 300),
            'disk' => $this->safely(fn () => disk_free_space(storage_path()) > 500 * 1024 * 1024),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(fn (bool $ok) => $ok ? 'ok' : 'fail', $checks),
        ], $healthy ? 200 : 503, ['Cache-Control' => 'no-store']);
    }

    private function fresh(string $key, int $maxAgeSeconds): bool
    {
        return $this->safely(fn () => now()->timestamp - (int) Cache::get($key, 0) <= $maxAgeSeconds);
    }

    private function safely(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }
}
