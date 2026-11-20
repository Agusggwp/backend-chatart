<?php

namespace App\Services;

use App\Events\MessageDeleted;
use App\Events\MessageReactionUpdated;
use App\Events\MessagesRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService
{
    public function __construct(
        protected PushNotificationService $pushNotificationService
    ) {}

    /**
     * List all conversations for the authenticated user ordered by pinned status and latest activity.
     *
     * @return Collection<int, Conversation>
     */
    public function listConversations(User $user, bool $archived = false): Collection
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('is_archived', $archived))
            ->with([
                'users.presence',
                'latestMessage.sender',
            ])
            ->get();

        foreach ($conversations as $conversation) {
            $conversation->setAttribute('unread_count', $this->computeUnreadCount($conversation, $user));
        }

        return $conversations->sort(function (Conversation $a, Conversation $b) use ($user) {
            $pivotA = $a->users->first(fn ($u) => $u->id === $user->id)?->pivot;
            $pivotB = $b->users->first(fn ($u) => $u->id === $user->id)?->pivot;

            $pinnedA = (bool) ($pivotA?->is_pinned ?? false);
            $pinnedB = (bool) ($pivotB?->is_pinned ?? false);

            if ($pinnedA !== $pinnedB) {
                return $pinnedA ? -1 : 1;
            }

            if ($pinnedA && $pinnedB) {
                $pinnedAtA = $pivotA?->pinned_at ? Carbon::parse($pivotA->pinned_at)->timestamp : 0;
                $pinnedAtB = $pivotB?->pinned_at ? Carbon::parse($pivotB->pinned_at)->timestamp : 0;
                if ($pinnedAtA !== $pinnedAtB) {
                    return $pinnedAtB <=> $pinnedAtA;
                }
            }

            $timeA = ($a->last_message_at ?? $a->created_at)?->timestamp ?? 0;
            $timeB = ($b->last_message_at ?? $b->created_at)?->timestamp ?? 0;

            return $timeB <=> $timeA;
        })->values();
    }

    /**
     * Find an existing private conversation between two users, or create a new one.
     */
    public function findOrCreatePrivateConversation(User $currentUser, int $otherUserId): Conversation
    {
        $existing = Conversation::query()
            ->where('type', Conversation::TYPE_PRIVATE)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $currentUser->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $otherUserId))
            ->has('participants', '=', 2)
            ->first();

        if ($existing !== null) {
            return $this->hydrateConversation($existing, $currentUser);
        }

        $conversation = DB::transaction(function () use ($currentUser, $otherUserId): Conversation {
            $created = Conversation::create([
                'type' => Conversation::TYPE_PRIVATE,
                'last_message_at' => null,
            ]);

            $created->participants()->createMany([
                ['user_id' => $currentUser->id],
                ['user_id' => $otherUserId],
            ]);

            return $created;
        });

        return $this->hydrateConversation($conversation, $currentUser);
    }

    /**
     * Retrieve a single conversation after verifying participant access.
     */
    public function getConversation(User $user, Conversation $conversation): Conversation
    {
        $this->ensureParticipant($user, $conversation);

        return $this->hydrateConversation($conversation, $user);
    }

    /**
     * Fetch paginated messages for a conversation (newest first for infinite scroll up).
     */
    public function listMessages(User $user, Conversation $conversation, int $perPage = 30): LengthAwarePaginator
    {
        $this->ensureParticipant($user, $conversation);

        return $conversation->messages()
            ->with(['sender', 'replyTo.sender'])
            ->where(function ($query) use ($user) {
                $query->whereNull('deleted_for_user_ids')
                    ->orWhereJsonDoesntContain('deleted_for_user_ids', $user->id);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Persist a new message, update conversation timestamps, and broadcast via Reverb.
     */
    public function sendMessage(
        User $sender,
        Conversation $conversation,
        ?string $body = null,
        ?UploadedFile $attachment = null,
        ?int $replyToId = null,
        ?int $duration = null,
    ): Message {
        $this->ensureParticipant($sender, $conversation);

        if ($conversation->type === Conversation::TYPE_PRIVATE) {
            $other = $conversation->users()->where('users.id', '!=', $sender->id)->first();
            if ($other && $other->hasBlocked($sender->id)) {
                throw new AccessDeniedHttpException('Anda tidak dapat mengirim pesan karena telah diblokir oleh pengguna ini.');
            }
        }

        $attachmentUrl = null;
        $attachmentType = null;
        $attachmentSize = null;
        $attachmentDuration = null;
        $fileName = null;
        $type = Message::TYPE_TEXT;

        if ($attachment !== null) {
            $path = $attachment->store("chat_media/{$conversation->id}", 'public');
            $attachmentUrl = $path;
            $attachmentSize = $attachment->getSize();
            $mime = strtolower((string) $attachment->getMimeType());
            $ext = strtolower((string) $attachment->getClientOriginalExtension());
            $fileName = $attachment->getClientOriginalName();

            $isAudio = str_starts_with($mime, 'audio/') || in_array($ext, ['m4a', 'mp3', 'wav', 'aac', 'ogg', 'opus', 'bin'], true);
            $isImage = str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

            if ($isAudio || $duration !== null) {
                $type = Message::TYPE_VOICE;
                $attachmentType = 'voice';
                $attachmentDuration = $duration;
            } elseif ($isImage) {
                $type = Message::TYPE_IMAGE;
                $attachmentType = 'image';
            } else {
                $type = Message::TYPE_DOCUMENT;
                $attachmentType = 'document';
            }
        }

        // Validate reply_to belongs to same conversation
        if ($replyToId !== null) {
            $validReply = $conversation->messages()->where('id', $replyToId)->exists();
            if (! $validReply) {
                $replyToId = null;
            }
        }

        $bodyText = $body !== null && trim($body) !== '' ? trim($body) : '';

        $message = DB::transaction(function () use (
            $sender,
            $conversation,
            $bodyText,
            $type,
            $attachmentUrl,
            $attachmentType,
            $attachmentSize,
            $attachmentDuration,
            $fileName,
            $replyToId
        ): Message {
            $created = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $bodyText,
                'type' => $type,
                'attachment_url' => $attachmentUrl,
                'file_name' => $fileName,
                'attachment_type' => $attachmentType,
                'attachment_size' => $attachmentSize,
                'attachment_duration' => $attachmentDuration,
                'reply_to_id' => $replyToId,
            ]);

            $conversation->update([
                'last_message_at' => $created->created_at,
            ]);

            $conversation->participants()
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            return $created;
        });

        $message->load(['sender', 'replyTo.sender']);

        $participantIds = $conversation->participants()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->safeBroadcast(new MessageSent($message, $participantIds));

        $recipientIds = array_values(array_diff($participantIds, [$sender->id]));
        $this->pushNotificationService->sendChatNotification($message, $recipientIds);

        return $message;
    }

    /**
     * Delete a message: for_everyone (author or group admin) or for_me (hidden only for user).
     */
    public function deleteMessage(
        User $user,
        Conversation $conversation,
        Message $message,
        string $deleteType,
    ): Message {
        $this->ensureParticipant($user, $conversation);

        if ((int) $message->conversation_id !== (int) $conversation->id) {
            throw new NotFoundHttpException('Pesan tidak ditemukan di percakapan ini.');
        }

        if ($deleteType === 'for_everyone') {
            $isSender = (int) $message->sender_id === (int) $user->id;
            $isAdmin = $conversation->isGroup() && $conversation->isAdmin($user->id);

            if (! $isSender && ! $isAdmin) {
                throw new AccessDeniedHttpException('Hanya pengirim pesan yang dapat menghapus pesan untuk semua orang.');
            }

            if ($message->attachment_url) {
                Storage::disk('public')->delete($message->attachment_url);
            }

            $message->update([
                'is_deleted_for_everyone' => true,
                'attachment_url' => null,
                'attachment_type' => null,
                'attachment_size' => null,
                'body' => 'Pesan ini telah dihapus',
            ]);

            $participantIds = $conversation->participants()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->safeBroadcast(new MessageDeleted(
                messageId: $message->id,
                conversationId: $conversation->id,
                deletedByUserId: $user->id,
                isDeletedForEveryone: true,
                participantIds: $participantIds,
            ));
        } else {
            // for_me: only hide for this user
            $deletedList = $message->deleted_for_user_ids ?? [];
            if (! in_array($user->id, $deletedList, true)) {
                $deletedList[] = $user->id;
            }
            $message->update(['deleted_for_user_ids' => $deletedList]);
        }

        return $message;
    }

    /**
     * Mark unread messages from other participants as read and broadcast receipt.
     */
    public function markAsRead(User $reader, Conversation $conversation): int
    {
        $this->ensureParticipant($reader, $conversation);

        $now = now();

        if ($conversation->isGroup()) {
            return $this->markGroupAsRead($reader, $conversation, $now);
        }

        $updatedCount = $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        $conversation->participants()
            ->where('user_id', $reader->id)
            ->update(['last_read_at' => $now]);

        if ($updatedCount > 0) {
            $this->safeBroadcast(
                new MessagesRead(
                    conversationId: $conversation->id,
                    readerId: $reader->id,
                    readAt: $now->toIso8601String(),
                )
            );
        }

        return $updatedCount;
    }

    /**
     * Group read receipts: each member tracks their own last_read_at.
     * A message's read_at is only set once ALL other members have read it (WhatsApp-style).
     */
    private function markGroupAsRead(User $reader, Conversation $conversation, \Illuminate\Support\Carbon $now): int
    {
        $participant = $conversation->participants()->where('user_id', $reader->id)->first();
        $previousReadAt = $participant?->last_read_at;

        $unreadQuery = $conversation->messages()->where('sender_id', '!=', $reader->id);
        if ($previousReadAt !== null) {
            $unreadQuery->where('created_at', '>', $previousReadAt);
        }
        $markedCount = $unreadQuery->count();

        $conversation->participants()
            ->where('user_id', $reader->id)
            ->update(['last_read_at' => $now]);

        $readTimes = $conversation->participants()
            ->pluck('last_read_at', 'user_id');

        $pending = $conversation->messages()
            ->whereNull('read_at')
            ->get(['id', 'sender_id', 'created_at']);

        $fullyReadIds = $pending->filter(function ($message) use ($readTimes): bool {
            foreach ($readTimes as $userId => $lastReadAt) {
                if ((int) $userId === (int) $message->sender_id) {
                    continue;
                }
                if ($lastReadAt === null || \Illuminate\Support\Carbon::parse($lastReadAt)->lt($message->created_at)) {
                    return false;
                }
            }

            return true;
        })->pluck('id');

        if ($fullyReadIds->isNotEmpty()) {
            $conversation->messages()->whereIn('id', $fullyReadIds)->update(['read_at' => $now]);
        }

        if ($markedCount > 0) {
            $this->safeBroadcast(
                new MessagesRead(
                    conversationId: $conversation->id,
                    readerId: $reader->id,
                    readAt: $now->toIso8601String(),
                )
            );
        }

        return $markedCount;
    }

    /**
     * Broadcast a typing indicator event to the conversation channel.
     */
    public function broadcastTyping(User $user, Conversation $conversation, bool $isTyping): void
    {
        $this->ensureParticipant($user, $conversation);

        $this->safeBroadcast(
            new UserTyping(
                conversationId: $conversation->id,
                userId: $user->id,
                userName: $user->name,
                isTyping: $isTyping,
            )
        );
    }

    private function ensureParticipant(User $user, Conversation $conversation): void
    {
        if (! $conversation->hasParticipant($user->id)) {
            throw new AccessDeniedHttpException('Anda tidak terdaftar dalam percakapan ini.');
        }
    }

    public function hydrateConversation(Conversation $conversation, User $currentUser): Conversation
    {
        $conversation->load(['users.presence', 'latestMessage.sender']);
        $conversation->setAttribute('unread_count', $this->computeUnreadCount($conversation, $currentUser));

        return $conversation;
    }

    private function computeUnreadCount(Conversation $conversation, User $user): int
    {
        if (! $conversation->isGroup()) {
            return $conversation->messages()
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        }

        $participant = $conversation->users->firstWhere('id', $user->id);
        $lastReadAt = $participant?->pivot?->last_read_at;

        $query = $conversation->messages()->where('sender_id', '!=', $user->id);
        if ($lastReadAt !== null) {
            $query->where('created_at', '>', $lastReadAt);
        }

        return $query->count();
    }

    public function toggleStarMessage(User $user, int $messageId): array
    {
        $message = Message::findOrFail($messageId);
        $this->ensureParticipant($user, $message->conversation);

        $exists = DB::table('starred_messages')
            ->where('user_id', $user->id)
            ->where('message_id', $message->id)
            ->exists();

        if ($exists) {
            DB::table('starred_messages')
                ->where('user_id', $user->id)
                ->where('message_id', $message->id)
                ->delete();
            $isStarred = false;
        } else {
            DB::table('starred_messages')->insert([
                'user_id' => $user->id,
                'message_id' => $message->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isStarred = true;
        }

        return ['is_starred' => $isStarred];
    }

    public function getStarredMessages(User $user, int $page = 1, int $perPage = 30): LengthAwarePaginator
    {
        return $user->starredMessages()
            ->with(['conversation.users.presence', 'sender.presence', 'replyTo.sender'])
            ->orderBy('starred_messages.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function searchMessages(
        Conversation $conversation,
        User $user,
        string $keyword,
        int $page = 1,
        int $perPage = 30
    ): LengthAwarePaginator {
        $this->ensureParticipant($user, $conversation);

        return $conversation->messages()
            ->where('is_deleted_for_everyone', false)
            ->where(function ($q) use ($keyword) {
                $q->where('body', 'like', "%{$keyword}%")
                  ->orWhere('file_name', 'like', "%{$keyword}%");
            })
            ->with(['sender.presence', 'replyTo.sender'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function togglePinConversation(Conversation $conversation, User $user): array
    {
        $this->ensureParticipant($user, $conversation);

        $participant = $conversation->participants()->where('user_id', $user->id)->firstOrFail();
        $isPinned = ! $participant->is_pinned;
        $participant->update([
            'is_pinned' => $isPinned,
            'pinned_at' => $isPinned ? now() : null,
        ]);

        return [
            'is_pinned' => $isPinned,
            'pinned_at' => $participant->pinned_at?->toIso8601String(),
        ];
    }

    public function toggleArchiveConversation(Conversation $conversation, User $user): array
    {
        $this->ensureParticipant($user, $conversation);

        $participant = $conversation->participants()->where('user_id', $user->id)->firstOrFail();
        $isArchived = ! $participant->is_archived;
        $participant->update([
            'is_archived' => $isArchived,
            'archived_at' => $isArchived ? now() : null,
            // If archiving, automatically unpin
            'is_pinned' => $isArchived ? false : $participant->is_pinned,
            'pinned_at' => $isArchived ? null : $participant->pinned_at,
        ]);

        return [
            'is_archived' => $isArchived,
            'archived_at' => $participant->archived_at?->toIso8601String(),
            'is_pinned' => $participant->is_pinned,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reactToMessage(User $user, int $messageId, string $emoji): array
    {
        $message = Message::findOrFail($messageId);
        $this->ensureParticipant($user, $message->conversation);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->emoji === $emoji) {
            // Tapping same emoji removes reaction
            $existing->delete();
            $action = 'removed';
            $activeEmoji = null;
        } elseif ($existing !== null) {
            // Changed to a different emoji
            $existing->update(['emoji' => $emoji]);
            $action = 'updated';
            $activeEmoji = $emoji;
        } else {
            // Newly added
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
            $action = 'added';
            $activeEmoji = $emoji;
        }

        $message->load('reactions.user');
        $reactions = $message->reactionsSummary($user->id);

        $participantIds = $message->conversation->participants()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->safeBroadcast(new MessageReactionUpdated(
            messageId: $message->id,
            conversationId: $message->conversation_id,
            userId: $user->id,
            emoji: $activeEmoji,
            action: $action,
            reactions: $reactions,
            participantIds: $participantIds,
        ));

        return [
            'action' => $action,
            'emoji' => $activeEmoji,
            'reactions' => $reactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeReaction(User $user, int $messageId): array
    {
        $message = Message::findOrFail($messageId);
        $this->ensureParticipant($user, $message->conversation);

        MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->delete();

        $message->load('reactions.user');
        $reactions = $message->reactionsSummary($user->id);

        $participantIds = $message->conversation->participants()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->safeBroadcast(new MessageReactionUpdated(
            messageId: $message->id,
            conversationId: $message->conversation_id,
            userId: $user->id,
            emoji: null,
            action: 'removed',
            reactions: $reactions,
            participantIds: $participantIds,
        ));

        return [
            'action' => 'removed',
            'emoji' => null,
            'reactions' => $reactions,
        ];
    }

    /**
     * Forward an existing message to one or more conversations.
     *
     * @param list<int> $targetConversationIds
     * @return list<Message>
     */
    public function forwardMessage(User $user, int $messageId, array $targetConversationIds): array
    {
        $original = Message::findOrFail($messageId);
        $this->ensureParticipant($user, $original->conversation);

        $createdMessages = [];

        foreach ($targetConversationIds as $targetConvId) {
            $targetConversation = Conversation::find($targetConvId);
            if ($targetConversation === null) continue;

            try {
                $this->ensureParticipant($user, $targetConversation);
            } catch (\Throwable) {
                continue;
            }

            if ($targetConversation->type === Conversation::TYPE_PRIVATE) {
                $other = $targetConversation->users()->where('users.id', '!=', $user->id)->first();
                if ($other && $other->hasBlocked($user->id)) {
                    continue;
                }
            }

            $forwarded = Message::create([
                'conversation_id' => $targetConversation->id,
                'sender_id' => $user->id,
                'body' => $original->body,
                'type' => $original->type,
                'attachment_url' => $original->attachment_url,
                'file_name' => $original->file_name,
                'attachment_type' => $original->attachment_type,
                'attachment_size' => $original->attachment_size,
                'attachment_duration' => $original->attachment_duration,
                'is_forwarded' => true,
            ]);

            $targetConversation->update(['last_message_at' => now()]);
            $forwarded->load(['sender', 'conversation.participants']);

            $participantIds = $targetConversation->participants()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->safeBroadcast(new MessageSent($forwarded, $participantIds));

            $createdMessages[] = $forwarded;
        }

        return $createdMessages;
    }

    public function blockUser(User $currentUser, int $targetUserId): bool
    {
        if ($currentUser->id === $targetUserId) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('Tidak dapat memblokir diri sendiri.');
        }

        \App\Models\BlockedUser::firstOrCreate([
            'user_id' => $currentUser->id,
            'blocked_user_id' => $targetUserId,
        ]);

        return true;
    }

    public function unblockUser(User $currentUser, int $targetUserId): bool
    {
        \App\Models\BlockedUser::where('user_id', $currentUser->id)
            ->where('blocked_user_id', $targetUserId)
            ->delete();

        return true;
    }

    public function listBlockedUsers(User $currentUser): Collection
    {
        return $currentUser->blockedUsers()->with('presence')->get();
    }

    private function safeBroadcast(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast via Reverb gagal dikirim: '.$e->getMessage());
        }
    }
}
