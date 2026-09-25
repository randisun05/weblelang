<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        RateLimiter::for('bids', function (Request $request) {
            return Limit::perMinute((int) config('auction.bid_rate_limit', 20))
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => back()->with('error', 'Terlalu banyak penawaran dalam waktu singkat. Tunggu sebentar.'));
        });

        // Form titip barang publik: 5 pengajuan per jam per IP.
        RateLimiter::for('consign', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
