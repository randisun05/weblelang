<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\LotStatus;
use App\Enums\SettlementStatus;
use App\Exports\SalesExport;
use App\Exports\SettlementsExport;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Settlement;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        $invoices = Invoice::whereBetween('created_at', [$from, $to]);
        $paid = (clone $invoices)->where('status', InvoiceStatus::Paid);
        $lots = Lot::whereBetween('closed_at', [$from, $to]);

        return Inertia::render('Admin/Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'lots_closed' => (clone $lots)->count(),
                'lots_sold' => (clone $lots)->where('status', LotStatus::Sold)->count(),
                'invoices' => (clone $invoices)->count(),
                'invoices_paid' => (clone $paid)->count(),
                'invoices_cancelled' => (clone $invoices)->where('status', InvoiceStatus::Cancelled)->count(),
                'gmv' => (int) (clone $paid)->sum('hammer_price'),
                'buyer_premium' => (int) (clone $paid)->sum('buyer_premium'),
                'admin_fee' => (int) (clone $paid)->sum('admin_fee'),
                'commission' => (int) Settlement::whereBetween('created_at', [$from, $to])->sum('commission'),
                'settlement_paid' => (int) Settlement::whereBetween('created_at', [$from, $to])->where('status', SettlementStatus::Paid)->sum('net_amount'),
                'settlement_pending' => (int) Settlement::whereBetween('created_at', [$from, $to])->where('status', SettlementStatus::Pending)->sum('net_amount'),
            ],
        ]);
    }

    public function sales(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        AuditLogger::log('report.sales_exported', null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return Excel::download(new SalesExport($from, $to), "penjualan_{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx");
    }

    public function settlements(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        AuditLogger::log('report.settlements_exported', null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return Excel::download(new SettlementsExport($from, $to), "settlement_{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx");
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth(),
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
