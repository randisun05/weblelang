<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberi setiap peramban ID acak (cookie terenkripsi, httpOnly). Dipakai hanya untuk
 * mendeteksi beberapa akun yang menawar dari perangkat yang sama (lihat FraudDetector).
 */
class AssignDeviceId
{
    public const COOKIE = 'wl_did';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9]{32}$/', $id)) {
            $id = Str::random(32);
            cookie()->queue(cookie(self::COOKIE, $id, 60 * 24 * 730, secure: config('session.secure'), httpOnly: true, sameSite: 'lax'));
        }

        $request->attributes->set('device_id', $id);

        return $next($request);
    }
}
