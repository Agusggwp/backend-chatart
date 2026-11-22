<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserE2eeKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'identity_key',
        'signed_prekey',
        'prekey_signature',
        'one_time_prekeys',
    ];

    protected function casts(): array
    {
        return [
            'one_time_prekeys' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
