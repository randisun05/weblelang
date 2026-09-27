<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConsignmentRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ConsignmentRequest;
use App\Models\Item;
use App\Services\AuditLogger;
use App\Services\ConsignmentIntakeService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsignmentRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $status = ConsignmentRequestStatus::tryFrom((string) $status) ? $status : null;

        return Inertia::render('Admin/ConsignRequests/Index', [
            'requests' => ConsignmentRequest::with('category:id,name')
                ->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['new', 'reviewing']))
                ->latest()->paginate(20)->withQueryString()
                ->through(fn (ConsignmentRequest $r) => [
                    'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'phone' => $r->phone, 'city' => $r->city,
                    'title' => $r->title, 'category' => $r->category?->name, 'expected_price' => $r->expected_price,
                    'photos_count' => count($r->photos), 'status' => Present::status($r->status),
                    'created_at' => $r->created_at->toIso8601String(),
                ]),
            'status' => $status,
            'statuses' => ConsignmentRequestStatus::options(),
            'counts' => ConsignmentRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(ConsignmentRequest $consignmentRequest, ConsignmentIntakeService $intake): Response
    {
        $r = $consignmentRequest->load('category', 'consignor:id,code,name', 'item:id,code,title', 'reviewer:id,name');

        return Inertia::render('Admin/ConsignRequests/Show', [
            'request' => [
                'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'phone' => $r->phone, 'email' => $r->email,
                'city' => $r->city, 'category_id' => $r->category_id, 'category' => $r->category?->name,
                'title' => $r->title, 'description' => $r->description,
                'condition' => Item::CONDITIONS[$r->condition] ?? $r->condition, 'expected_price' => $r->expected_price,
                'handover' => ConsignmentRequest::HANDOVER[$r->handover] ?? $r->handover,
                'photos' => collect($r->photos)->keys()->map(fn ($i) => route('admin.consign-requests.photo', [$r->id, $i])),
                'status' => Present::status($r->status), 'open' => $r->isOpen(), 'reject_reason' => $r->reject_reason,
                'consignor' => $r->consignor?->only(['id', 'code', 'name']), 'item' => $r->item?->only(['id', 'code', 'title']),
                'reviewer' => $r->reviewer?->name, 'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                'created_at' => $r->created_at->toIso8601String(),
            ],
            'matches' => $r->isOpen() ? $intake->matchingConsignors($r) : [],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'defaultCommission' => config('auction.default_commission_rate'),
        ]);
    }

    public function photo(ConsignmentRequest $consignmentRequest, int $index): StreamedResponse
    {
        $path = $consignmentRequest->photos[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }

    public function reviewing(Request $request, ConsignmentRequest $consignmentRequest): RedirectResponse
    {
        if ($consignmentRequest->status === ConsignmentRequestStatus::New) {
            $consignmentRequest->forceFill(['status' => ConsignmentRequestStatus::Reviewing, 'reviewed_by' => $request->user()->id])->save();
            AuditLogger::log('consignment_request.reviewing', $consignmentRequest);
        }

        return back()->with('success', 'Pengajuan ditandai sedang ditinjau.');
    }

    public function accept(Request $request, ConsignmentRequest $consignmentRequest, ConsignmentIntakeService $intake): RedirectResponse
    {
        $data = $request->validate([
            'consignor_id' => ['nullable', 'exists:consignors,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'reserve_price' => ['nullable', 'integer', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'storage_location' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $item = $intake->accept($consignmentRequest, array_filter($data, fn ($v) => $v !== null), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.items.show', $item)
            ->with('success', "Pengajuan diterima → barang {$item->code} dibuat. Lanjutkan pemeriksaan setelah barang tiba.");
    }

    public function reject(Request $request, ConsignmentRequest $consignmentRequest, ConsignmentIntakeService $intake): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $intake->reject($consignmentRequest, $data['reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan ditolak dan pengaju sudah diberi tahu melalui email.');
    }
}
