<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Consignor;
use App\Models\Item;
use App\Services\AuditLogger;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConsignorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();

        return Inertia::render('Admin/Consignors/Index', [
            'consignors' => Consignor::withCount('items')
                ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
                ->latest()->paginate(20)->withQueryString()
                ->through(fn (Consignor $c) => [
                    'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'phone' => $c->phone,
                    'email' => $c->email, 'commission_rate' => $c->commission_rate, 'items_count' => $c->items_count,
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Consignors/Form', [
            'consignor' => null,
            'defaultCommission' => config('auction.default_commission_rate'),
            'banks' => config('payments.banks'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $consignor = Consignor::create($this->validated($request));
        AuditLogger::log('consignor.created', $consignor);

        return redirect()->route('admin.consignors.show', $consignor)->with('success', 'Penitip ditambahkan.');
    }

    public function show(Consignor $consignor): Response
    {
        $consignor->load(['items' => fn ($q) => $q->latest(), 'settlements' => fn ($q) => $q->latest()]);

        return Inertia::render('Admin/Consignors/Show', [
            'consignor' => [
                'id' => $consignor->id, 'code' => $consignor->code, 'name' => $consignor->name,
                'phone' => $consignor->phone, 'email' => $consignor->email, 'address' => $consignor->address,
                'bank_name' => $consignor->bank_name, 'bank_account' => $consignor->maskedBankAccount(),
                'bank_holder' => $consignor->bank_holder, 'commission_rate' => $consignor->commission_rate,
                'notes' => $consignor->notes,
                'portal_url' => $consignor->portalUrl(),
                'portal_days' => (int) config('auction.consignor_portal_days', 30),
            ],
            'items' => $consignor->items->map(fn (Item $i) => [
                'id' => $i->id, 'code' => $i->code, 'title' => $i->title,
                'reserve_price' => $i->reserve_price, 'status' => Present::status($i->status),
            ]),
            'settlements' => $consignor->settlements->map(fn ($s) => [
                'id' => $s->id, 'number' => $s->number, 'hammer_price' => $s->hammer_price,
                'commission' => $s->commission, 'net_amount' => $s->net_amount,
                'status' => Present::status($s->status), 'paid_at' => $s->paid_at?->toIso8601String(),
            ]),
            'totals' => [
                'net_paid' => $consignor->settlements->filter(fn ($s) => $s->status === SettlementStatus::Paid)->sum('net_amount'),
                'net_pending' => $consignor->settlements->filter(fn ($s) => $s->status === SettlementStatus::Pending)->sum('net_amount'),
            ],
        ]);
    }

    /** Mencabut semua link portal lama dan membuat yang baru. */
    public function resetPortal(Consignor $consignor): RedirectResponse
    {
        $consignor->resetPortalLink();
        AuditLogger::log('consignor.portal_reset', $consignor);

        return back()->with('success', 'Link portal lama dicabut. Bagikan link baru ke penitip.');
    }

    public function edit(Consignor $consignor): Response
    {
        return Inertia::render('Admin/Consignors/Form', [
            'consignor' => $consignor->only([
                'id', 'name', 'phone', 'whatsapp_notifications', 'email', 'address', 'bank_name', 'bank_holder', 'commission_rate', 'notes',
            ]) + [
                'has_nik' => (bool) $consignor->nik,
                'bank_account_masked' => $consignor->maskedBankAccount(),
            ],
            'defaultCommission' => config('auction.default_commission_rate'),
            'banks' => config('payments.banks'),
        ]);
    }

    public function update(Request $request, Consignor $consignor): RedirectResponse
    {
        $data = $this->validated($request);

        // Field terenkripsi yang dikosongkan berarti "tidak diubah".
        foreach (['nik', 'bank_account'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $consignor->update($data);
        AuditLogger::log('consignor.updated', $consignor, ['fields' => array_keys($data)]);

        return redirect()->route('admin.consignors.show', $consignor)->with('success', 'Data penitip diperbarui.');
    }

    public function destroy(Consignor $consignor): RedirectResponse
    {
        if ($consignor->items()->exists()) {
            return back()->with('error', 'Penitip yang memiliki barang tidak dapat dihapus.');
        }

        $consignor->delete();
        AuditLogger::log('consignor.deleted', $consignor);

        return redirect()->route('admin.consignors.index')->with('success', 'Penitip dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp_notifications' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'nik' => ['nullable', 'digits:16'],
            'bank_name' => ['nullable', Rule::in(array_keys(config('payments.banks')))],
            'bank_account' => ['nullable', 'string', 'regex:/^[0-9]{5,20}$/'],
            'bank_holder' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
