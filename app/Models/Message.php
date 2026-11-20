<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VOICE = 'voice';
    public const TYPE_DOCUMENT = 'document';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'type',
        'read_at',
        'attachment_url',
        'file_name',
        'attachment_type',
        'attachment_size',
        'attachment_duration',
        'reply_to_id',
        'is_deleted_for_everyone',
        'is_forwarded',
        'deleted_for_user_ids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'is_deleted_for_everyone' => 'boolean',
            'is_forwarded' => 'boolean',
            'deleted_for_user_ids' => 'array',
            'attachment_size' => 'integer',
            'attachment_duration' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isDeletedFor(int $userId): bool
    {
        if ($this->is_deleted_for_everyone) {
            return false;
        }

        $deletedList = $this->deleted_for_user_ids ?? [];
        return in_array($userId, $deletedList, true);
    }

    public function getFullAttachmentUrlAttribute(): ?string
    {
        if ($this->is_deleted_for_everyone || ! $this->attachment_url) {
            return null;
        }

        $url = $this->attachment_url;
        $requestHost = null;
        try {
            if (request()->hasHeader('Host')) {
                $requestHost = request()->schemeAndHttpHost();
            }
        } catch (\Throwable) {}

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if ($requestHost && preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?(/storage/.*)$#i', $url, $matches)) {
                return $requestHost . $matches[3];
            }
            return $url;
        }

        $path = ltrim($url, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if ($requestHost) {
            return $requestHost . '/storage/' . $path;
        }

        return Storage::disk('public')->url($path);
    }

    public function starredBy(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'starred_messages', 'message_id', 'user_id')
            ->withTimestamps();
    }

    public function isStarredBy(?int $userId): bool
    {
        if (!$userId) return false;
        if ($this->relationLoaded('starredBy')) {
            return $this->starredBy->contains('id', $userId);
        }
        return $this->starredBy()->where('user_id', $userId)->exists();
    }

    public function reactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    /**
     * @return list<array{emoji: string, count: int, reacted_by_me: bool, users: list<array{id: int, name: string, username: string}>}>
     */
    public function reactionsSummary(?int $userId = null): array
    {
        $reactions = $this->relationLoaded('reactions')
            ? $this->reactions
            : $this->reactions()->with('user:id,name,username')->get();

        $grouped = [];
        foreach ($reactions as $r) {
            $emoji = $r->emoji;
            if (!isset($grouped[$emoji])) {
                $grouped[$emoji] = [
                    'emoji' => $emoji,
                    'count' => 0,
                    'reacted_by_me' => false,
                    'users' => [],
                ];
            }
            $grouped[$emoji]['count']++;
            if ($userId && $r->user_id === $userId) {
                $grouped[$emoji]['reacted_by_me'] = true;
            }
            $grouped[$emoji]['users'][] = [
                'id' => $r->user_id,
                'name' => $r->user?->name ?? 'Pengguna',
                'username' => $r->user?->username ?? '',
            ];
        }

        return array_values($grouped);
    }
}
