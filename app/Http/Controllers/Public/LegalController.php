<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Public/Legal/Terms', $this->props() + ['version' => config('legal.terms_version')]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Public/Legal/Privacy', $this->props() + ['version' => config('legal.privacy_version')]);
    }

    /** Persetujuan ulang saat versi dokumen berubah. */
    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']], ['accept.accepted' => 'Centang persetujuan terlebih dahulu.']);

        $request->user()->acceptTerms();
        AuditLogger::log('user.terms_accepted', $request->user(), ['version' => config('legal.terms_version')]);

        return back()->with('success', 'Terima kasih, persetujuan Anda telah dicatat.');
    }

    private function props(): array
    {
        return [
            'operator' => config('legal.operator'),
            'privacyEmail' => config('legal.privacy_email'),
            'rules' => [
                'buyer_premium_rate' => config('auction.default_buyer_premium_rate'),
                'invoice_due_hours' => config('auction.invoice_due_hours'),
                'anti_snipe_minutes' => config('auction.anti_snipe_minutes'),
                'extend_minutes' => config('auction.extend_minutes'),
                'commission_rate' => config('auction.default_commission_rate'),
                'pickup_days' => config('legal.pickup_days'),
            ],
        ];
    }
}
