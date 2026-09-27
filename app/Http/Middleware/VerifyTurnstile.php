<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memverifikasi token Cloudflare Turnstile pada form publik yang rawan bot
 * (login, registrasi, lupa kata sandi, pengajuan titip barang).
 * Tidak aktif bila TURNSTILE_SECRET_KEY kosong.
 */
class VerifyTurnstile
{
    /** Nama route (POST) yang dilindungi. */
    public const PROTECTED_ROUTES = ['login.store', 'register.store', 'password.email', 'consign.store'];

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.turnstile.secret_key');

        if (! $secret || ! $request->isMethod('post') || ! $request->routeIs(...self::PROTECTED_ROUTES)) {
            return $next($request);
        }

        if (! $this->passes($secret, (string) $request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'captcha' => 'Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        return $next($request);
    }

    private function passes(string $secret, string $token, ?string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (ConnectionException) {
            // Cloudflare tidak terjangkau: tolak (fail-closed) dan catat.
            Log::warning('Turnstile tidak dapat dihubungi');

            return false;
        }

        return (bool) $response->json('success');
    }
}
