<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'url',
        'icon',
        'color',
        'user_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'time_ago',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reads()
    {
        return $this->hasMany(SystemNotificationRead::class, 'notification_id');
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : '';
    }

    /**
     * Cek apakah notifikasi sudah dibaca oleh user tertentu.
     */
    public function isReadBy(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return false;
        }

        if ($this->relationLoaded('reads')) {
            return $this->reads->contains('user_id', $userId);
        }

        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * Method terpusat untuk memicu notifikasi baru ke database & session push.
     */
    public static function send(
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        ?string $icon = null,
        ?string $color = null,
        ?array $data = null,
        ?int $userId = null
    ): self {
        $iconMap = [
            'school' => 'school',
            'project' => 'building-2',
            'rab' => 'calculator',
            'rpd' => 'file-spreadsheet',
            'bku' => 'book-open',
        ];

        $colorMap = [
            'school' => 'emerald',
            'project' => 'indigo',
            'rab' => 'amber',
            'rpd' => 'purple',
            'bku' => 'sky',
        ];

        $resolvedIcon = $icon ?: ($iconMap[$type] ?? 'bell');
        $resolvedColor = $color ?: ($colorMap[$type] ?? 'indigo');
        $actorId = $userId ?: auth()->id();

        $notification = self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'icon' => $resolvedIcon,
            'color' => $resolvedColor,
            'user_id' => $actorId,
            'data' => $data,
        ]);

        // Simpan flash ke session untuk push notification instan di frontend (Browser Push & In-App Toast)
        if (session()) {
            session()->flash('push_notification', [
                'id' => $notification->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'icon' => $resolvedIcon,
                'color' => $resolvedColor,
            ]);
        }

        return $notification;
    }
}

