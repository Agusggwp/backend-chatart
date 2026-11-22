<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'is_pinned',
        'pinned_at',
        'is_archived',
        'archived_at',
        'is_muted',
        'muted_until',
        'cleared_at',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'is_muted' => 'boolean',
            'muted_until' => 'datetime',
            'cleared_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
