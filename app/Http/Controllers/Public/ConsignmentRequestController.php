<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ConsignmentRequest;
use App\Models\Item;
use App\Services\ConsignmentIntakeService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Form publik "Titipkan barang Anda". */
class ConsignmentRequestController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        Seo::set(title: 'Titipkan Barang untuk Dilelang', description: 'Ajukan barang Anda untuk dilelang secara online: isi formulir, unggah foto, dan tim kami akan menghubungi Anda.');

        return Inertia::render('Public/Consign/Create', [
            'categories' => Category::orderBy('name')->get(['id', 'name', 'icon']),
            'conditions' => collect(Item::CONDITIONS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'handovers' => collect(ConsignmentRequest::HANDOVER)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'prefill' => $user && ! $user->isBackoffice() ? ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone] : null,
            'commissionRate' => config('auction.default_commission_rate'),
        ]);
    }

    public function store(Request $request, ConsignmentIntakeService $intake): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'email' => ['required', 'email', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'condition' => ['required', Rule::in(array_keys(Item::CONDITIONS))],
            'expected_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'handover' => ['required', Rule::in(array_keys(ConsignmentRequest::HANDOVER))],
            'photos' => ['required', 'array', 'min:1', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => 'Nomor HP harus nomor Indonesia yang valid, mis. 081234567890.',
            'description.min' => 'Ceritakan kondisi & kelengkapan barang minimal 20 karakter.',
            'photos.required' => 'Unggah minimal 1 foto barang.',
            'consent.accepted' => 'Anda harus menyetujui pemrosesan data untuk pengajuan ini.',
        ]);
        unset($data['photos'], $data['consent']);

        $consignment = $intake->submit($data, $request->file('photos', []), $request->user(), $request->ip());

        return redirect()->to($consignment->statusUrl())
            ->with('success', "Pengajuan {$consignment->code} terkirim! Konfirmasi juga dikirim ke email Anda.");
    }

    /** Halaman status untuk pengaju (link bertanda tangan dari email). */
    public function status(ConsignmentRequest $consignmentRequest): Response
    {
        $r = $consignmentRequest->load('category:id,name');

        Seo::set(title: 'Status Pengajuan', noindex: true);

        return Inertia::render('Public/Consign/Status', [
            'request' => [
                'code' => $r->code, 'title' => $r->title, 'name' => $r->name,
                'category' => $r->category?->name, 'status' => Present::status($r->status),
                'reject_reason' => $r->reject_reason, 'handover' => ConsignmentRequest::HANDOVER[$r->handover] ?? $r->handover,
                'created_at' => $r->created_at->toIso8601String(), 'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            ],
        ]);
    }
}
