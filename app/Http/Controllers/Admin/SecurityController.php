<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function twoFactor(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Security/TwoFactor', [
            'enabled' => ! is_null($user->two_factor_secret),
            'confirmed' => ! is_null($user->two_factor_confirmed_at),
            'mustEnable' => $user->role->requiresTwoFactor(),
        ]);
    }
}
