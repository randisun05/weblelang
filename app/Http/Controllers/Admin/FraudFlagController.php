<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tinjauan indikasi shill bidding / akun ganda. */
class FraudFlagController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['open', 'dismissed', 'confirmed'], true) ? $request->query('status') : 'open';

        $flags = FraudFlag::with('lot.item:id,title', 'reviewer:id,name')->where('status', $status)
            ->orderByRaw("case severity when 'high' then 0 else 1 end")->latest()->paginate(20)->withQueryString();

        $users = User::whereIn('id', $flags->getCollection()->pluck('user_ids')->flatten()->unique())
            ->get(['id', 'name', 'email', 'is_blocked'])->keyBy('id');

        return Inertia::render('Admin/Fraud/Index', [
            'status' => $status,
            'counts' => FraudFlag::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'flags' => $flags->through(fn (FraudFlag $f) => [
                'id' => $f->id,
                'label' => $f->label(),
                'rule' => $f->rule,
                'severity' => $f->severity,
                'details' => $f->details,
                'lot' => $f->lot ? ['id' => $f->lot->id, 'number' => $f->lot->lot_number, 'title' => $f->lot->item->title] : null,
                'users' => collect($f->user_ids)->map(fn ($id) => $users->get($id)?->only(['id', 'name', 'email', 'is_blocked']))->filter()->values(),
                'note' => $f->note,
                'reviewer' => $f->reviewer?->name,
                'created_at' => $f->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function review(Request $request, FraudFlag $flag): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:dismissed,confirmed'],
            'note' => ['nullable', 'string', 'max:255'],
            'block_users' => ['boolean'],
        ]);

        $flag->forceFill([
            'status' => $data['decision'], 'note' => $data['note'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ])->save();

        $blocked = 0;
        if ($data['decision'] === 'confirmed' && $request->boolean('block_users')) {
            $blocked = User::whereIn('id', $flag->user_ids)->where('role', 'bidder')->update(['is_blocked' => true]);
        }

        AuditLogger::log('fraud.'.$data['decision'], $flag, ['blocked_users' => $blocked, 'note' => $data['note'] ?? null]);

        return back()->with('success', $data['decision'] === 'confirmed'
            ? 'Kecurigaan dikonfirmasi'.($blocked ? " dan {$blocked} akun diblokir." : '.')
            : 'Kecurigaan diabaikan.');
    }
}
