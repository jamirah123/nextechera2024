<?php

namespace App\Http\Controllers;

use App\Services\NotificationFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $user = $request->user();
        $payload = $feed->feed($user);

        return response()->json([
            ...$payload,
            'can_view_audit' => Gate::forUser($user)->allows('viewAuditLogs'),
            'audit_url' => Gate::forUser($user)->allows('viewAuditLogs')
                ? route('audit.index')
                : null,
        ]);
    }

    public function markRead(Request $request, NotificationFeedService $feed): JsonResponse
    {
        $user = $request->user();
        $feed->markRead($user);

        return response()->json([
            'unread_count' => 0,
            'read_at' => $user->fresh()->notifications_read_at?->toIso8601String(),
        ]);
    }
}
