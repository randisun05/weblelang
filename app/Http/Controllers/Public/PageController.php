<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function howItWorks(): Response
    {
        return Inertia::render('Public/HowItWorks', [
            'defaults' => [
                'buyer_premium_rate' => config('auction.default_buyer_premium_rate'),
                'commission_rate' => config('auction.default_commission_rate'),
                'invoice_due_hours' => config('auction.invoice_due_hours'),
                'anti_snipe_minutes' => config('auction.anti_snipe_minutes'),
                'extend_minutes' => config('auction.extend_minutes'),
            ],
        ]);
    }
}
