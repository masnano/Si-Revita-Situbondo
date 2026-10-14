<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Models\SystemNotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mengambil daftar notifikasi terbaru beserta status dibaca user saat ini.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Ambil notifikasi beserta relasi reads untuk user saat ini
        $notifications = SystemNotification::with(['reads' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])
        ->latest('id')
        ->limit(25)
        ->get()
        ->map(function ($item) use ($userId) {
            return [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'message' => $item->message,
                'url' => $item->url,
                'icon' => $item->icon,
                'color' => $item->color,
                'time_ago' => $item->time_ago,
                'created_at' => $item->created_at->format('d M Y H:i'),
                'is_read' => $item->reads->isNotEmpty(),
            ];
        });

        $unreadCount = SystemNotification::whereDoesntHave('reads', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Endpoint ringan untuk polling unread count dan ID notifikasi terbaru.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $unreadCount = SystemNotification::whereDoesntHave('reads', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        $latestNotification = SystemNotification::latest('id')->first();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'latest_id' => $latestNotification?->id ?? 0,
            'latest' => $latestNotification ? [
                'id' => $latestNotification->id,
                'type' => $latestNotification->type,
                'title' => $latestNotification->title,
                'message' => $latestNotification->message,
                'url' => $latestNotification->url,
                'icon' => $latestNotification->icon,
                'color' => $latestNotification->color,
                'time_ago' => $latestNotification->time_ago,
            ] : null,
        ]);
    }

    /**
     * Tandai SEMUA notifikasi sebagai telah dibaca untuk user yang sedang aktif.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Ambil semua notifikasi yang belum dibaca oleh user ini
        $unreadNotifications = SystemNotification::whereDoesntHave('reads', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->pluck('id');

        $now = now();
        $records = [];
        foreach ($unreadNotifications as $notifId) {
            $records[] = [
                'notification_id' => $notifId,
                'user_id' => $userId,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($records)) {
            SystemNotificationRead::insertOrIgnore($records);
        }

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi berhasil ditandai telah dibaca.',
            'unread_count' => 0,
        ]);
    }

    /**
     * Tandai satu notifikasi tertentu sebagai telah dibaca.
     */
    public function markAsRead(Request $request, SystemNotification $notification): JsonResponse
    {
        $userId = auth()->id();

        SystemNotificationRead::firstOrCreate(
            ['notification_id' => $notification->id, 'user_id' => $userId],
            ['read_at' => now()]
        );

        $unreadCount = SystemNotification::whereDoesntHave('reads', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Menandai notifikasi dibaca dan langsung mengarahkan ke link tujuan.
     */
    public function visit(SystemNotification $notification): RedirectResponse
    {
        $userId = auth()->id();

        SystemNotificationRead::firstOrCreate(
            ['notification_id' => $notification->id, 'user_id' => $userId],
            ['read_at' => now()]
        );

        return redirect($notification->url ?: route('dashboard'));
    }
}

