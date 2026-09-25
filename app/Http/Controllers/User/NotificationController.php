<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('User/Notifications', [
            'notifications' => $request->user()->notifications()->paginate(20)
                ->through(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read' => (bool) $n->read_at,
                    'at' => $n->created_at->toIso8601String(),
                ]),
        ]);
    }

    /** Tandai dibaca lalu buka tautan notifikasi (hanya tautan internal). */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('user.dashboard');

        return str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('user.dashboard');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
