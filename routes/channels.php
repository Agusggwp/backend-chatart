<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return (int) $user->id === $id;
});

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $conversation->hasParticipant($user->id);
});
