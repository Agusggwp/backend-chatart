<?php

namespace App\Services;

use App\Events\GroupUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GroupService
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * Create a new group conversation with creator as admin and memberIds as members.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGroup(User $creator, array $data): Conversation
    {
        $memberIds = array_values(array_unique(array_map('intval', $data['member_ids'])));

        $conversation = DB::transaction(function () use ($creator, $data, $memberIds): Conversation {
            $group = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $creator->id,
                'last_message_at' => now(),
            ]);

            $participants = [
                [
                    'user_id' => $creator->id,
                    'role' => ConversationParticipant::ROLE_ADMIN,
                    'last_read_at' => now(),
                ],
            ];

            foreach ($memberIds as $memberId) {
                if ($memberId === $creator->id) {
                    continue;
                }
                $participants[] = [
                    'user_id' => $memberId,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'last_read_at' => null,
                ];
            }

            $group->participants()->createMany($participants);

            return $group;
        });

        $allIds = array_values(array_unique([$creator->id, ...$memberIds]));
        $this->safeBroadcast(new GroupUpdated($conversation->id, $allIds, 'created'));

        return $this->chatService->hydrateConversation($conversation, $creator);
    }

    /**
     * Update group name/description (admin only).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(User $actor, Conversation $conversation, array $data): Conversation
    {
        $this->ensureGroupAdmin($actor, $conversation);

        $allowed = array_intersect_key($data, array_flip(['name', 'description']));
        if ($allowed !== []) {
            $conversation->fill($allowed);
            $conversation->save();
        }

        $participantIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->safeBroadcast(new GroupUpdated($conversation->id, $participantIds, 'updated'));

        return $this->chatService->hydrateConversation($conversation->fresh(), $actor);
    }

    /**
     * Upload/replace group avatar (admin only).
     */
    public function uploadAvatar(User $actor, Conversation $conversation, UploadedFile $file): Conversation
    {
        $this->ensureGroupAdmin($actor, $conversation);

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = sprintf('group_%d_%s.%s', $conversation->id, Str::uuid()->toString(), $extension);

        $storedPath = Storage::disk('public')->putFileAs('group_avatars', $file, $filename);
        $oldAvatar = $conversation->avatar;

        $conversation->avatar = $storedPath;
        $conversation->save();

        if ($oldAvatar && $oldAvatar !== $storedPath && Storage::disk('public')->exists($oldAvatar)) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $participantIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->safeBroadcast(new GroupUpdated($conversation->id, $participantIds, 'avatar_updated'));

        return $this->chatService->hydrateConversation($conversation->fresh(), $actor);
    }

    /**
     * Add members to a group (admin only).
     *
     * @param  list<int>  $memberIds
     */
    public function addMembers(User $actor, Conversation $conversation, array $memberIds): Conversation
    {
        $this->ensureGroupAdmin($actor, $conversation);

        $existingIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $toAdd = array_values(array_diff(array_unique(array_map('intval', $memberIds)), $existingIds));

        if ($toAdd !== []) {
            $rows = array_map(fn (int $uid) => [
                'user_id' => $uid,
                'role' => ConversationParticipant::ROLE_MEMBER,
                'last_read_at' => now(),
            ], $toAdd);

            $conversation->participants()->createMany($rows);
        }

        $allIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->safeBroadcast(new GroupUpdated($conversation->id, $allIds, 'members_added'));

        return $this->chatService->hydrateConversation($conversation->fresh(), $actor);
    }

    /**
     * Remove a member from a group (admin only).
     */
    public function removeMember(User $actor, Conversation $conversation, User $targetUser): Conversation
    {
        $this->ensureGroupAdmin($actor, $conversation);

        if ($actor->id === $targetUser->id) {
            throw new BadRequestHttpException('Gunakan endpoint keluar grup untuk meninggalkan grup sendiri.');
        }

        $conversation->participants()->where('user_id', $targetUser->id)->delete();

        $remainingIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $notifyIds = array_values(array_unique([...$remainingIds, $targetUser->id]));
        $this->safeBroadcast(new GroupUpdated($conversation->id, $notifyIds, 'member_removed'));

        return $this->chatService->hydrateConversation($conversation->fresh(), $actor);
    }

    /**
     * Leave a group. If the last admin leaves and others remain, promote the earliest member.
     * If no participants remain, delete the empty group.
     */
    public function leaveGroup(User $actor, Conversation $conversation): void
    {
        if (! $conversation->isGroup()) {
            throw new BadRequestHttpException('Percakapan ini bukan grup.');
        }

        if (! $conversation->hasParticipant($actor->id)) {
            throw new AccessDeniedHttpException('Anda bukan anggota grup ini.');
        }

        $remainingIds = [];

        DB::transaction(function () use ($actor, $conversation, &$remainingIds): void {
            $wasAdmin = $conversation->isAdmin($actor->id);
            $conversation->participants()->where('user_id', $actor->id)->delete();

            $remaining = $conversation->participants()->orderBy('id')->get();
            if ($remaining->isEmpty()) {
                $conversation->delete();

                return;
            }

            if ($wasAdmin && ! $remaining->contains('role', ConversationParticipant::ROLE_ADMIN)) {
                $nextAdmin = $remaining->first();
                $nextAdmin->role = ConversationParticipant::ROLE_ADMIN;
                $nextAdmin->save();
            }

            $remainingIds = $remaining->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        });

        if ($remainingIds !== []) {
            $this->safeBroadcast(new GroupUpdated($conversation->id, $remainingIds, 'member_left'));
        }
    }

    public function generateInviteToken(User $actor, Conversation $conversation): string
    {
        $this->ensureGroupAdmin($actor, $conversation);

        $token = \Illuminate\Support\Str::random(32);
        $conversation->update([
            'invite_token' => $token,
            'invite_token_created_at' => now(),
        ]);

        return $token;
    }

    public function joinByInviteToken(string $token, User $user): Conversation
    {
        $conversation = Conversation::where('invite_token', $token)
            ->where('type', Conversation::TYPE_GROUP)
            ->first();

        if (! $conversation) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Tautan undangan grup tidak valid atau telah kadaluarsa.');
        }

        $isPart = $conversation->isParticipant((int) $user->id);
        if ($isPart) {
            return $conversation;
        }

        $p = ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => (int) $user->id,
            'role' => ConversationParticipant::ROLE_MEMBER,
        ]);

        $allIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->safeBroadcast(new GroupUpdated($conversation->id, $allIds, 'members_added'));

        return $conversation;
    }

    public function updatePermissions(User $actor, Conversation $conversation, array $permissions): Conversation
    {
        $this->ensureGroupAdmin($actor, $conversation);

        $currentSettings = $conversation->settings ?? [];
        $conversation->settings = array_merge($currentSettings, $permissions);
        $conversation->save();

        $allIds = $conversation->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->safeBroadcast(new GroupUpdated($conversation->id, $allIds, 'updated'));

        return $conversation;
    }

    private function ensureGroupAdmin(User $actor, Conversation $conversation): void
    {
        if (! $conversation->isGroup()) {
            throw new BadRequestHttpException('Percakapan ini bukan grup.');
        }

        if (! $conversation->isAdmin($actor->id)) {
            throw new AccessDeniedHttpException('Hanya admin grup yang dapat melakukan tindakan ini.');
        }
    }

    private function safeBroadcast(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Group broadcast via Reverb gagal: '.$e->getMessage());
        }
    }
}
