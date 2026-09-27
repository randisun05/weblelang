<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Memeriksa konfigurasi & kredensial payment gateway TANPA membuat transaksi:
 * cek autentikasi via endpoint baca-saja, cocokkan kode bank, dan tampilkan URL webhook.
 */
class PaymentsCheck extends Command
{
    protected $signature = 'payments:check';

    protected $description = 'Periksa kredensial payment gateway, kode bank, dan URL webhook sebelum go-live';

    private int $failures = 0;

    public function handle(): int
    {
        $payment = config('payments.gateway');
        $payout = config('payments.payout_gateway');

        $this->components->info('Gateway pembayaran: '.($payment ?: 'nonaktif').' · payout: '.($payout ?: 'manual'));
        $this->line(str_starts_with((string) config('app.url'), 'https://')
            ? '  ✓ APP_URL memakai HTTPS'
            : '  ⚠ APP_URL belum HTTPS — webhook & redirect gateway membutuhkan URL publik HTTPS');

        foreach (array_unique(array_filter([$payment, $payout])) as $gateway) {
            match ($gateway) {
                'midtrans' => $this->checkMidtrans($payment === 'midtrans', $payout === 'midtrans'),
                'xendit' => $this->checkXendit($payment === 'xendit', $payout === 'xendit'),
                'simulator' => $this->warnLine(app()->isProduction()
                    ? 'Simulator TIDAK boleh dipakai di production — ganti PAYMENT_GATEWAY/PAYOUT_GATEWAY.'
                    : 'Simulator aktif (hanya untuk lokal/demo).', app()->isProduction()),
                default => $this->bad("Gateway [{$gateway}] tidak dikenal."),
            };
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Daftarkan URL webhook berikut di dashboard gateway</>');
        foreach (['midtrans', 'xendit'] as $g) {
            if (in_array($g, [$payment, $payout], true)) {
                if ($payment === $g) {
                    $this->components->twoColumnDetail("{$g} · pembayaran", route('payments.webhook', $g));
                }
                if ($payout === $g) {
                    $this->components->twoColumnDetail("{$g} · payout", route('payouts.webhook', $g));
                }
            }
        }

        $this->newLine();
        if ($this->failures) {
            $this->components->error("{$this->failures} pemeriksaan gagal. Perbaiki sebelum go-live.");

            return self::FAILURE;
        }

        $this->components->info('Semua pemeriksaan lolos.');

        return self::SUCCESS;
    }

    private function checkMidtrans(bool $payments, bool $payouts): void
    {
        $cfg = config('payments.drivers.midtrans');
        $base = $cfg['is_production'] ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $this->line('  Midtrans ('.($cfg['is_production'] ? 'PRODUCTION' : 'sandbox').')');

        if ($payments) {
            if (! $cfg['server_key']) {
                $this->bad('MIDTRANS_SERVER_KEY kosong.');
            } else {
                // Status transaksi fiktif: 404 = kunci valid, 401 = kunci salah. Tidak membuat transaksi.
                $status = $this->probe(fn () => Http::withBasicAuth($cfg['server_key'], '')->acceptJson()->timeout(15)
                    ->get("{$base}/v2/cek-kredensial-weblelang/status"));
                $code = $status?->json('status_code') ?? $status?->status();
                in_array((string) $code, ['404', '200'], true)
                    ? $this->ok('Server key valid')
                    : $this->bad('Server key ditolak (kode '.($code ?? 'koneksi gagal').').');
            }
        }

        if ($payouts) {
            foreach (['iris_creator_key' => 'MIDTRANS_IRIS_CREATOR_KEY', 'iris_merchant_key' => 'MIDTRANS_IRIS_MERCHANT_KEY'] as $key => $env) {
                $cfg[$key] ? $this->ok("{$env} terisi") : $this->bad("{$env} kosong.");
            }
            $cfg['iris_approver_key']
                ? $this->ok('Approver key terisi (payout disetujui otomatis)')
                : $this->warnLine('MIDTRANS_IRIS_APPROVER_KEY kosong — payout harus disetujui manual di dashboard Iris.');

            if ($cfg['iris_creator_key']) {
                $irisBase = ($cfg['is_production'] ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com').'/iris/api/v1';
                $banks = $this->probe(fn () => Http::withBasicAuth($cfg['iris_creator_key'], '')->acceptJson()->timeout(15)->get("{$irisBase}/beneficiary_banks"));
                $this->checkBanks($banks, 'beneficiary_banks', 'code', fn (string $c) => strtolower($c));
            }
        }
    }

    private function checkXendit(bool $payments, bool $payouts): void
    {
        $cfg = config('payments.drivers.xendit');
        $this->line('  Xendit ('.(str_starts_with((string) $cfg['secret_key'], 'xnd_production') ? 'PRODUCTION' : 'test').')');

        if (! $cfg['secret_key']) {
            $this->bad('XENDIT_SECRET_KEY kosong.');

            return;
        }

        $cfg['callback_token'] ? $this->ok('Callback token terisi') : $this->bad('XENDIT_CALLBACK_TOKEN kosong — webhook akan ditolak.');

        $balance = $this->probe(fn () => Http::withBasicAuth($cfg['secret_key'], '')->acceptJson()->timeout(15)->get('https://api.xendit.co/balance'));
        $balance?->successful()
            ? $this->ok('Secret key valid (saldo: Rp '.number_format((int) $balance->json('balance'), 0, ',', '.').')')
            : $this->bad('Secret key ditolak ('.($balance?->json('error_code') ?? 'koneksi gagal').').');

        if ($payouts && $balance?->successful()) {
            $banks = $this->probe(fn () => Http::withBasicAuth($cfg['secret_key'], '')->acceptJson()->timeout(15)->get('https://api.xendit.co/available_disbursements_banks'));
            $this->checkBanks($banks, null, 'code', fn (string $c) => $c);
        }
    }

    /** Cocokkan daftar kode bank di config/payments.php dengan yang didukung gateway. */
    private function checkBanks($response, ?string $path, string $field, callable $map): void
    {
        if (! $response?->successful()) {
            $this->bad('Tidak dapat mengambil daftar bank dari gateway (cek kredensial payout).');

            return;
        }

        $supported = collect($path ? $response->json($path) : $response->json())->pluck($field)->filter()->map(fn ($c) => (string) $c)->all();
        $missing = collect(array_keys(config('payments.banks')))->reject(fn ($code) => in_array($map($code), $supported, true));

        $missing->isEmpty()
            ? $this->ok('Semua kode bank di config/payments.php dikenali gateway')
            : $this->bad('Kode bank tidak dikenali gateway: '.$missing->implode(', ').' — sesuaikan config/payments.php.');
    }

    private function probe(callable $request)
    {
        try {
            return $request();
        } catch (ConnectionException) {
            return null;
        }
    }

    private function ok(string $message): void
    {
        $this->line("    <fg=green>✓</> {$message}");
    }

    private function warnLine(string $message, bool $isFailure = false): void
    {
        $isFailure ? $this->bad($message) : $this->line("    <fg=yellow>⚠</> {$message}");
    }

    private function bad(string $message): void
    {
        $this->failures++;
        $this->line("    <fg=red>✗</> {$message}");
    }
}
