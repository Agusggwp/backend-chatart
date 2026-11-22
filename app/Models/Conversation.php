<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Conversation extends Model
{
    use HasFactory;

    public const TYPE_PRIVATE = 'private';

    public const TYPE_GROUP = 'group';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'description',
        'invite_token',
        'invite_token_created_at',
        'settings',
        'avatar',
        'created_by',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'invite_token_created_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'is_pinned', 'pinned_at', 'is_archived', 'archived_at', 'is_muted', 'muted_until', 'cleared_at', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function isParticipant(int $userId): bool
    {
        return $this->hasParticipant($userId);
    }

    public function isAdmin(int $userId): bool
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->where('role', ConversationParticipant::ROLE_ADMIN)
            ->exists();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return url(Storage::url($this->avatar));
    }
}
