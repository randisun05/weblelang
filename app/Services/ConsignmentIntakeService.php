<?php

namespace App\Services;

use App\Enums\ConsignmentRequestStatus;
use App\Enums\ItemStatus;
use App\Models\ConsignmentRequest;
use App\Models\Consignor;
use App\Models\Item;
use App\Models\User;
use App\Notifications\ConsignmentRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Alur pengajuan titip barang online: ajukan → tinjau → terima (jadi penitip + barang) / tolak. */
class ConsignmentIntakeService
{
    public function __construct(private ImageService $images) {}

    public function submit(array $data, array $photos, ?User $user, ?string $ip): ConsignmentRequest
    {
        $paths = array_map(fn ($file) => $this->images->store($file, 'consignment-requests', 'local', 1600), $photos);

        $request = ConsignmentRequest::create($data + ['photos' => $paths, 'user_id' => $user?->id, 'ip' => $ip]);

        Notification::route('mail', $request->email)->notify(new ConsignmentRequestNotification($request));

        return $request;
    }

    /** Penitip yang sudah ada dengan HP atau email sama (disarankan ke admin agar tidak dobel). */
    public function matchingConsignors(ConsignmentRequest $request)
    {
        // Nomor bisa tersimpan dengan format berbeda (+62 812-..., 0812...): bandingkan 9 digit terakhir.
        $digits = fn (?string $phone) => substr(preg_replace('/\D/', '', (string) $phone), -9);
        $target = $digits($request->phone);

        return Consignor::query()
            ->where(fn ($q) => $q->where('email', $request->email)->orWhere('phone', 'like', '%'.substr($target, -4)))
            ->get(['id', 'code', 'name', 'phone', 'email', 'commission_rate'])
            ->filter(fn (Consignor $c) => strcasecmp((string) $c->email, $request->email) === 0 || $digits($c->phone) === $target)
            ->take(5)->values();
    }

    /**
     * Menerima pengajuan: memakai penitip yang ada atau membuat baru, lalu membuat barang
     * berstatus "Diterima" beserta fotonya (dipindah dari disk privat ke publik).
     */
    public function accept(ConsignmentRequest $request, array $data, User $actor): Item
    {
        return DB::transaction(function () use ($request, $data, $actor) {
            $request = ConsignmentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isOpen()) {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }

            $consignor = isset($data['consignor_id'])
                ? Consignor::findOrFail($data['consignor_id'])
                : Consignor::create([
                    'name' => $request->name, 'phone' => $request->phone, 'email' => $request->email,
                    'address' => $request->city, 'commission_rate' => $data['commission_rate'] ?? config('auction.default_commission_rate'),
                ]);

            $item = Item::create([
                'consignor_id' => $consignor->id,
                'category_id' => $data['category_id'],
                'title' => $request->title,
                'description' => $request->description,
                'condition' => $request->condition,
                'reserve_price' => (int) ($data['reserve_price'] ?? $request->expected_price ?? 0),
                'storage_location' => $data['storage_location'] ?? null,
                'inspection_notes' => "Dari pengajuan online {$request->code}. Barang belum diperiksa fisik.",
            ]);
            $item->forceFill(['status' => ItemStatus::Received, 'received_at' => null])->save();

            foreach ($request->photos as $i => $path) {
                if (Storage::disk('local')->exists($path)) {
                    $item->images()->create([
                        'path' => $this->images->storeFromPath(Storage::disk('local')->path($path), 'items/'.$item->id),
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            $request->forceFill([
                'status' => ConsignmentRequestStatus::Accepted, 'consignor_id' => $consignor->id, 'item_id' => $item->id,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(),
            ])->save();

            AuditLogger::log('consignment_request.accepted', $request, ['item' => $item->code, 'consignor' => $consignor->code]);
            Notification::route('mail', $request->email)->notify(new ConsignmentRequestNotification($request));

            return $item;
        });
    }

    public function reject(ConsignmentRequest $request, string $reason, User $actor): void
    {
        if (! $request->isOpen()) {
            throw new RuntimeException('Pengajuan ini sudah diproses.');
        }

        $request->forceFill([
            'status' => ConsignmentRequestStatus::Rejected, 'reject_reason' => $reason,
            'reviewed_by' => $actor->id, 'reviewed_at' => now(),
        ])->save();

        AuditLogger::log('consignment_request.rejected', $request, ['reason' => $reason]);
        Notification::route('mail', $request->email)->notify(new ConsignmentRequestNotification($request));
    }
}
