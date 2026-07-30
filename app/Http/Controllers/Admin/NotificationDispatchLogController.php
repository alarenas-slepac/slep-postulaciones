<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationDispatchLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationDispatchLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $channel = trim((string) $request->get('channel', ''));
        $event = trim((string) $request->get('event', ''));
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $items = NotificationDispatchLog::query()
            ->with(['triggeredBy', 'related', 'notifiable'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('recipient_email', 'like', "%{$q}%")
                        ->orWhere('recipient_name', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('event_key', 'like', "%{$q}%")
                        ->orWhere('mailable_class', 'like', "%{$q}%")
                        ->orWhere('notification_class', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn($query) => $query->where('status', $status))
            ->when($channel !== '', fn($query) => $query->where('channel', $channel))
            ->when($event !== '', fn($query) => $query->where('event_key', $event))
            ->when($dateFrom !== '', fn($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $events = NotificationDispatchLog::query()
            ->whereNotNull('event_key')
            ->distinct()
            ->orderBy('event_key')
            ->pluck('event_key');

        return view('admin.notification-logs.index', compact(
            'items',
            'q',
            'status',
            'channel',
            'event',
            'dateFrom',
            'dateTo',
            'events'
        ));
    }

    public function show(NotificationDispatchLog $notificationLog): View
    {
        $notificationLog->load(['triggeredBy', 'related', 'notifiable']);

        return view('admin.notification-logs.show', [
            'item' => $notificationLog,
        ]);
    }
}
