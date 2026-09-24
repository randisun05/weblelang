<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin & super admin wajib mengaktifkan 2FA sebelum bisa memakai panel.
 * Bisa dimatikan di lingkungan lokal lewat AUTH_ENFORCE_2FA=false.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && config('auth.enforce_two_factor', true)
            && $user->role->requiresTwoFactor()
            && is_null($user->two_factor_confirmed_at)
            && ! $request->routeIs('admin.security.*', 'two-factor.*', 'password.confirm*', 'logout')) {
            return redirect()->route('admin.security.two-factor')
                ->with('warning', 'Aktifkan autentikasi dua faktor (2FA) terlebih dahulu untuk mengakses panel admin.');
        }

        return $next($request);
    }
}
