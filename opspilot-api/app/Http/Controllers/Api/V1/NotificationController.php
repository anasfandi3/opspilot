<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexNotificationRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(IndexNotificationRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $request->user()->notifications();
        if (($filters['status'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['status'] ?? 'all') === 'read') {
            $query->whereNotNull('read_at');
        }

        return NotificationResource::collection(
            $query->latest('created_at')->latest('id')
                ->paginate($filters['per_page'] ?? 20)->withQueryString(),
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->ownedNotification($request, $notification);
        if ($record->unread()) {
            $record->markAsRead();
        }

        return $this->response($request, $record->refresh(), 'Notification marked as read.');
    }

    public function markUnread(Request $request, string $notification): JsonResponse
    {
        $record = $this->ownedNotification($request, $notification);
        if ($record->read()) {
            $record->markAsUnread();
        }

        return $this->response($request, $record->refresh(), 'Notification marked as unread.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $affected = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'data' => ['affected' => $affected],
            'message' => 'All notifications marked as read.',
        ]);
    }

    private function ownedNotification(Request $request, string $notification): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($notification)->firstOrFail();
    }

    private function response(Request $request, DatabaseNotification $notification, string $message): JsonResponse
    {
        return response()->json([
            'data' => NotificationResource::make($notification)->resolve($request),
            'message' => $message,
        ]);
    }
}
