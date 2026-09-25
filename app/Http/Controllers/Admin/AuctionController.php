<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuctionStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionRegistration;
use App\Models\Item;
use App\Models\Lot;
use App\Payments\PaymentManager;
use App\Services\Auction\BidException;
use App\Services\Auction\LotCloser;
use App\Services\AuditLogger;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuctionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Auctions/Index', [
            'auctions' => Auction::withCount('lots')
                ->withCount(['lots as sold_count' => fn ($q) => $q->where('status', LotStatus::Sold)])
                ->withSum('lots as bids_total', 'bids_count')
                ->latest('starts_at')->paginate(20)
                ->through(fn (Auction $a) => [
                    'id' => $a->id, 'code' => $a->code, 'title' => $a->title,
                    'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(),
                    'lots_count' => $a->lots_count, 'sold_count' => $a->sold_count, 'bids_total' => (int) $a->bids_total,
                    'status' => Present::status($a->status),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Auctions/Form', [
            'auction' => null,
            'defaults' => [
                'buyer_premium_rate' => config('auction.default_buyer_premium_rate'),
                'anti_snipe_minutes' => config('auction.anti_snipe_minutes'),
                'extend_minutes' => config('auction.extend_minutes'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $auction = Auction::create($this->validated($request));
        AuditLogger::log('auction.created', $auction);

        return redirect()->route('admin.auctions.show', $auction)->with('success', 'Sesi lelang dibuat. Tambahkan lot lalu terbitkan.');
    }

    public function show(Auction $auction): Response
    {
        $auction->load(['lots.item.images', 'lots.leader:id,name', 'registrations.user', 'registrations.payouts', 'registrations.payments']);

        return Inertia::render('Admin/Auctions/Show', [
            'auction' => [
                'id' => $auction->id, 'code' => $auction->code, 'slug' => $auction->slug, 'title' => $auction->title,
                'description' => $auction->description,
                'starts_at' => $auction->starts_at->toIso8601String(), 'ends_at' => $auction->ends_at->toIso8601String(),
                'deposit_amount' => $auction->deposit_amount, 'buyer_premium_rate' => $auction->buyer_premium_rate,
                'anti_snipe_minutes' => $auction->anti_snipe_minutes, 'extend_minutes' => $auction->extend_minutes,
                'stagger_seconds' => $auction->stagger_seconds,
                'status' => Present::status($auction->status),
                'editable' => in_array($auction->status, [AuctionStatus::Draft, AuctionStatus::Published], true),
            ],
            'lots' => $auction->lots->map(fn (Lot $lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'item_id' => $lot->item_id,
                'title' => $lot->item->title,
                'code' => $lot->item->code,
                'image' => $lot->item->images->first()?->url(),
                'starting_price' => $lot->starting_price,
                'reserve_price' => $lot->reserve_price,
                'current_price' => $lot->current_price,
                'bids_count' => $lot->bids_count,
                'leader' => $lot->leader?->name,
                'ends_at' => $lot->ends_at->toIso8601String(),
                'status' => Present::status($lot->status),
            ]),
            'registrations' => $auction->registrations->map(fn (AuctionRegistration $r) => [
                'id' => $r->id, 'user' => $r->user->name, 'email' => $r->user->email,
                'status' => Present::status($r->status), 'has_proof' => (bool) $r->deposit_proof,
                'deposit' => $r->deposit_status ? Present::status($r->deposit_status) : null,
                'paid_online' => $r->payments->contains(fn ($p) => $p->status->value === 'paid'),
                'has_bank' => $r->user->hasBankAccount(),
                'payout' => ($p = $r->payouts->first()) ? ['status' => Present::status($p->status), 'failure_reason' => $p->failure_reason] : null,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
            'payoutEnabled' => app(PaymentManager::class)->payoutsEnabled(),
            'availableItems' => Item::with('consignor:id,name')->where('status', ItemStatus::Approved)->orderBy('code')->get()
                ->map(fn (Item $i) => [
                    'id' => $i->id, 'code' => $i->code, 'title' => $i->title, 'consignor' => $i->consignor?->name,
                    'reserve_price' => $i->reserve_price, 'estimate_low' => $i->estimate_low,
                ]),
        ]);
    }

    public function edit(Auction $auction): Response
    {
        abort_unless(in_array($auction->status, [AuctionStatus::Draft, AuctionStatus::Published], true), 403);

        return Inertia::render('Admin/Auctions/Form', [
            'auction' => $auction->only(['id', 'title', 'description', 'deposit_amount', 'buyer_premium_rate',
                'anti_snipe_minutes', 'extend_minutes', 'stagger_seconds']) + [
                    'starts_at' => $auction->starts_at->format('Y-m-d\TH:i'),
                    'ends_at' => $auction->ends_at->format('Y-m-d\TH:i'),
                ],
            'defaults' => [],
        ]);
    }

    public function update(Request $request, Auction $auction): RedirectResponse
    {
        abort_unless(in_array($auction->status, [AuctionStatus::Draft, AuctionStatus::Published], true), 403);

        DB::transaction(function () use ($request, $auction) {
            $auction->update($this->validated($request));
            $this->resyncLotTimes($auction);
        });
        AuditLogger::log('auction.updated', $auction);

        return redirect()->route('admin.auctions.show', $auction)->with('success', 'Sesi lelang diperbarui.');
    }

    public function addLots(Request $request, Auction $auction): RedirectResponse
    {
        abort_unless(in_array($auction->status, [AuctionStatus::Draft, AuctionStatus::Published], true), 403);

        $data = $request->validate([
            'lots' => ['required', 'array', 'min:1', 'max:200'],
            'lots.*.item_id' => ['required', 'integer', 'distinct'],
            'lots.*.starting_price' => ['required', 'integer', 'min:1000'],
        ]);

        $added = DB::transaction(function () use ($data, $auction) {
            $items = Item::whereIn('id', collect($data['lots'])->pluck('item_id'))
                ->where('status', ItemStatus::Approved)->lockForUpdate()->get()->keyBy('id');
            $number = (int) $auction->lots()->max('lot_number');
            $count = 0;

            foreach ($data['lots'] as $row) {
                $item = $items->get($row['item_id']);
                if (! $item) {
                    continue;
                }

                $auction->lots()->create([
                    'item_id' => $item->id,
                    'lot_number' => ++$number,
                    'starting_price' => $row['starting_price'],
                    'reserve_price' => $item->reserve_price,
                    'starts_at' => $auction->starts_at,
                    'ends_at' => $auction->ends_at,
                ]);
                $item->forceFill(['status' => ItemStatus::Listed])->save();
                $count++;
            }

            $this->resyncLotTimes($auction);

            return $count;
        });

        AuditLogger::log('auction.lots_added', $auction, ['count' => $added]);

        return back()->with('success', "{$added} lot ditambahkan.");
    }

    public function removeLot(Auction $auction, Lot $lot): RedirectResponse
    {
        abort_unless($lot->auction_id === $auction->id, 404);

        if ($lot->status !== LotStatus::Scheduled || $lot->bids_count > 0) {
            return back()->with('error', 'Hanya lot yang belum dimulai dan belum ada penawaran yang dapat dihapus. Gunakan "Batalkan".');
        }

        DB::transaction(function () use ($lot) {
            $lot->item->forceFill(['status' => ItemStatus::Approved])->save();
            $lot->delete();
        });

        return back()->with('success', 'Lot dihapus dari sesi.');
    }

    public function cancelLot(Request $request, Auction $auction, Lot $lot, LotCloser $closer): RedirectResponse
    {
        abort_unless($lot->auction_id === $auction->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $closer->cancel($lot, $data['reason']);
        } catch (BidException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Lot dibatalkan dan barang kembali ke status siap lelang.');
    }

    public function publish(Auction $auction): RedirectResponse
    {
        if ($auction->status !== AuctionStatus::Draft) {
            return back()->with('error', 'Sesi ini sudah diterbitkan.');
        }

        if ($auction->lots()->doesntExist()) {
            return back()->with('error', 'Tambahkan minimal satu lot sebelum menerbitkan.');
        }

        if ($auction->ends_at->isPast()) {
            return back()->with('error', 'Waktu selesai sesi sudah lewat. Ubah jadwal terlebih dahulu.');
        }

        $auction->forceFill(['status' => AuctionStatus::Published])->save();
        AuditLogger::log('auction.published', $auction);

        return back()->with('success', 'Sesi lelang diterbitkan dan tampil di website.');
    }

    public function unpublish(Auction $auction): RedirectResponse
    {
        if ($auction->status !== AuctionStatus::Published || $auction->starts_at->isPast()) {
            return back()->with('error', 'Hanya sesi terjadwal yang belum dimulai yang dapat ditarik kembali.');
        }

        $auction->forceFill(['status' => AuctionStatus::Draft])->save();
        AuditLogger::log('auction.unpublished', $auction);

        return back()->with('success', 'Sesi dikembalikan ke draf.');
    }

    /** Penutupan bertahap: lot ke-n tutup (n-1) × stagger_seconds setelah waktu selesai sesi. */
    private function resyncLotTimes(Auction $auction): void
    {
        $auction->lots()->where('status', LotStatus::Scheduled)->where('bids_count', 0)->get()
            ->each(function (Lot $lot) use ($auction) {
                $lot->forceFill([
                    'starts_at' => $auction->starts_at,
                    'ends_at' => $auction->ends_at->copy()->addSeconds(($lot->lot_number - 1) * $auction->stagger_seconds),
                ])->save();
            });
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'deposit_amount' => ['required', 'integer', 'min:0'],
            'buyer_premium_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'anti_snipe_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'extend_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'stagger_seconds' => ['required', 'integer', 'min:0', 'max:600'],
        ]);
    }
}
