<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Registrasi peserta lelang. Peran selalu `bidder` (default kolom),
     * tidak bisa diisi dari form.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $key = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan registrasi. Coba lagi dalam beberapa menit.',
            ]);
        }
        RateLimiter::hit($key, 600);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Nomor HP harus nomor Indonesia yang valid, mis. 081234567890.',
            'terms.accepted' => 'Anda harus menyetujui Syarat & Ketentuan dan Kebijakan Privasi.',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => Hash::make($input['password']),
        ]);
        $user->acceptTerms();

        return $user;
    }
}
