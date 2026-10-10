<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\NotificationFeedService;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationFeedService $feed): JsonResponse|View
    {
        $user = $request->user();

        if ($request->expectsJson()) {
            $panel = $request->string('panel')->toString();
            if (! in_array($panel, ['all', 'unread', 'important'], true)) {
                $panel = 'all';
            }

            return response()->json($feed->feed($user, 12, $panel));
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'group' => ['nullable', 'in:operations,deployments,manpower,hr,payroll,finance,administration,system'],
            'priority' => ['nullable', 'in:normal,important,urgent'],
            'read' => ['nullable', 'in:unread,read'],
            'status' => ['nullable', 'in:dismissed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('notifications.index', [
            'notifications' => $feed->history($user, $filters),
            'filters' => $filters,
            'preferences' => NotificationPreferences::for($user),
            'unreadCount' => $feed->unreadCount($user),
        ]);
    }

    public function markRead(Request $request, NotificationFeedService $feed): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $feed->markRead($user);

        if (! $request->expectsJson()) {
            return back()->with('status', 'Notifications marked as read.');
        }

        return response()->json([
            'unread_count' => 0,
            'read_at' => $user->fresh()->notifications_read_at?->toIso8601String(),
        ]);
    }

    public function updateState(Request $request, AuditLog $auditLog, NotificationFeedService $feed): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($feed->visibleTo($user, $auditLog), 404);

        $data = $request->validate([
            'action' => ['required', 'in:read,unread,dismiss,open'],
        ]);

        if ($data['action'] === 'open') {
            $destination = $feed->open($user, $auditLog);

            if (! $request->expectsJson()) {
                return $destination
                    ? redirect()->to($destination)
                    : back()->with('error', 'This record is no longer available, or you do not have access to it.');
            }

            return response()->json([
                'unread_count' => $feed->unreadCount($user),
                'url' => $destination,
            ]);
        }

        $feed->setState($user, $auditLog, $data['action']);

        if (! $request->expectsJson()) {
            return back();
        }

        return response()->json([
            'unread_count' => $feed->unreadCount($user),
        ]);
    }

    public function updatePreferences(Request $request, NotificationFeedService $feed): RedirectResponse
    {
        $feed->savePreferences($request->user(), $request->all());

        return back()->with('status', 'Notification preferences saved.');
    }
}
