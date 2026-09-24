<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function toggle(Request $request, Lot $lot): RedirectResponse
    {
        $result = $request->user()->watchlist()->toggle($lot->id);

        return back()->with('success', $result['attached'] ? 'Ditambahkan ke daftar pantauan.' : 'Dihapus dari daftar pantauan.');
    }
}
