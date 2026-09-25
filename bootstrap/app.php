<?php

use App\Http\Middleware\AssignDeviceId;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            AssignDeviceId::class,
            VerifyTurnstile::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'two-factor.required' => EnsureTwoFactorEnabled::class,
        ]);

        // Notifikasi Midtrans datang dari server luar (tanpa token CSRF); keasliannya
        // diverifikasi lewat signature di controller.
        $middleware->validateCsrfTokens(except: ['payments/webhook/*', 'payouts/webhook/*', 'payments/midtrans/notification']);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Di belakang load balancer / Cloudflare: TRUSTED_PROXIES=* atau daftar IP dipisah koma.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laporan error ke Sentry (aktif hanya bila SENTRY_LARAVEL_DSN diisi).
        Integration::handles($exceptions);
    })->create();
