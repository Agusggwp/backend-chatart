<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'bio',
        'privacy_last_seen',
        'privacy_status',
        'privacy_read_receipts',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'privacy_read_receipts' => 'boolean',
        ];
    }

    /**
     * Presence status of the user.
     */
    public function presence(): HasOne
    {
        return $this->hasOne(UserPresence::class);
    }

    /**
     * Resolve the public URL for the user's avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        $url = $this->avatar;
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

        return url(Storage::url($path));
    }

    public function statuses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function starredMessages(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Message::class, 'starred_messages', 'user_id', 'message_id')
            ->withTimestamps();
    }

    public function blockedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'user_id', 'blocked_user_id')
            ->withTimestamps();
    }

    public function blockedBy(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'blocked_user_id', 'user_id')
            ->withTimestamps();
    }

    public function hasBlocked(int $userId): bool
    {
        return $this->blockedUsers()->where('blocked_user_id', $userId)->exists();
    }

    public function isBlockedBy(int $userId): bool
    {
        return $this->blockedBy()->where('user_id', $userId)->exists();
    }
}
