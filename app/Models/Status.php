<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';

    protected $fillable = [
        'user_id',
        'type',
        'content',
        'media_url',
        'background_color',
        'font_style',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(StatusView::class);
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'status_views', 'status_id', 'viewer_id')
            ->withPivot('viewed_at')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isViewedBy(int $userId): bool
    {
        if ($this->relationLoaded('views')) {
            return $this->views->contains('viewer_id', $userId);
        }

        return $this->views()->where('viewer_id', $userId)->exists();
    }
}
