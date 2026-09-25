<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name'),
            ],
            // Flash global (sukses/error/info/warning) seperti di web-aspro.
            'session' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_backoffice' => $user->isBackoffice(),
                    'kyc_status' => $user->kyc_status->value,
                    'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
                    'needs_terms' => $user->needsTermsAcceptance(),
                ] : null,
                'unread_notifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            ],
        ]);
    }
}
