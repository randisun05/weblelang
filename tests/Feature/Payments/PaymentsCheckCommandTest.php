<?php

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentsCheckCommandTest extends TestCase
{
    public function test_midtrans_valid_keys_and_known_banks_pass(): void
    {
        config([
            'payments.gateway' => 'midtrans', 'payments.payout_gateway' => 'midtrans',
            'payments.drivers.midtrans.server_key' => 'SB-server', 'payments.drivers.midtrans.iris_creator_key' => 'creator',
            'payments.drivers.midtrans.iris_merchant_key' => 'merchant', 'payments.drivers.midtrans.iris_approver_key' => 'approver',
            'payments.banks' => ['BCA' => 'BCA', 'BNI' => 'BNI'],
        ]);
        Http::fake([
            'api.sandbox.midtrans.com/v2/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404),
            'app.sandbox.midtrans.com/iris/api/v1/beneficiary_banks' => Http::response(['beneficiary_banks' => [['code' => 'bca'], ['code' => 'bni'], ['code' => 'bri']]]),
        ]);

        $this->artisan('payments:check')->expectsOutputToContain('Server key valid')->assertSuccessful();
    }

    public function test_wrong_midtrans_key_fails(): void
    {
        config(['payments.gateway' => 'midtrans', 'payments.payout_gateway' => null, 'payments.drivers.midtrans.server_key' => 'salah']);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '401'], 401)]);

        $this->artisan('payments:check')->expectsOutputToContain('Server key ditolak')->assertFailed();
    }

    public function test_xendit_reports_unknown_bank_codes(): void
    {
        config([
            'payments.gateway' => 'xendit', 'payments.payout_gateway' => 'xendit',
            'payments.drivers.xendit.secret_key' => 'xnd_development_x', 'payments.drivers.xendit.callback_token' => 'cb',
            'payments.banks' => ['BCA' => 'BCA', 'BANKX' => 'Bank X'],
        ]);
        Http::fake([
            'api.xendit.co/balance' => Http::response(['balance' => 1_500_000]),
            'api.xendit.co/available_disbursements_banks' => Http::response([['code' => 'BCA'], ['code' => 'MANDIRI']]),
        ]);

        $this->artisan('payments:check')
            ->expectsOutputToContain('Secret key valid')
            ->expectsOutputToContain('BANKX')
            ->assertFailed();
    }

    public function test_simulator_in_production_fails(): void
    {
        config(['payments.gateway' => 'simulator', 'payments.payout_gateway' => 'simulator']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('payments:check')->assertFailed();
    }
}
