<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Manajemen akun petugas (khusus super admin). */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::where('role', '!=', Role::Bidder)->orderBy('name')->get()
                ->map(fn (User $u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                    'role' => ['value' => $u->role->value, 'label' => $u->role->label(), 'color' => $u->role->color()],
                    'two_factor' => (bool) $u->two_factor_confirmed_at, 'is_blocked' => $u->is_blocked,
                ]),
            'roles' => collect(Role::options())->reject(fn ($r) => $r['value'] === Role::Bidder->value)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([Role::SuperAdmin->value, Role::Admin->value, Role::Staff->value])],
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => Role::from($data['role']), 'kyc_status' => KycStatus::Unsubmitted])->save();

        AuditLogger::log('user.created', $user, ['role' => $data['role']]);

        return back()->with('success', 'Akun petugas dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === Role::Bidder, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in([Role::SuperAdmin->value, Role::Admin->value, Role::Staff->value])],
            'is_blocked' => ['required', 'boolean'],
        ]);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat mengubah peran/blokir akun sendiri.');
        }

        $user->forceFill(['role' => Role::from($data['role']), 'is_blocked' => $data['is_blocked']])->save();
        AuditLogger::log('user.updated', $user, $data);

        return back()->with('success', 'Akun petugas diperbarui.');
    }
}
