<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->string('action')->trim()->toString();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => AuditLog::with('user:id,name')
                ->when($action, fn ($q) => $q->where('action', 'like', "{$action}%"))
                ->latest('id')->paginate(50)->withQueryString()
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'user' => $log->user?->name ?? 'Sistem',
                    'action' => $log->action,
                    'subject' => $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : null,
                    'properties' => $log->properties,
                    'ip' => $log->ip,
                    'at' => $log->created_at?->toIso8601String(),
                ]),
            'filters' => ['action' => $action],
        ]);
    }
}
