<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\AddGroupMembersRequest;
use App\Http\Requests\Chat\CreateGroupRequest;
use App\Http\Requests\Chat\UpdateGroupRequest;
use App\Http\Requests\Chat\UploadGroupAvatarRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\GroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupService $groupService
    ) {}

    public function store(CreateGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->createGroup(
            creator: $request->user(),
            data: $request->validated(),
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($group),
            ],
            message: 'Grup berhasil dibuat.',
            status: 201
        );
    }

    public function update(UpdateGroupRequest $request, Conversation $conversation): JsonResponse
    {
        $group = $this->groupService->updateGroup(
            actor: $request->user(),
            conversation: $conversation,
            data: $request->validated(),
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($group),
            ],
            message: 'Info grup berhasil diperbarui.'
        );
    }

    public function uploadAvatar(UploadGroupAvatarRequest $request, Conversation $conversation): JsonResponse
    {
        $group = $this->groupService->uploadAvatar(
            actor: $request->user(),
            conversation: $conversation,
            file: $request->file('avatar'),
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($group),
            ],
            message: 'Foto grup berhasil diperbarui.'
        );
    }

    public function addMembers(AddGroupMembersRequest $request, Conversation $conversation): JsonResponse
    {
        $group = $this->groupService->addMembers(
            actor: $request->user(),
            conversation: $conversation,
            memberIds: $request->validated('member_ids'),
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($group),
            ],
            message: 'Anggota berhasil ditambahkan.'
        );
    }

    public function removeMember(Request $request, Conversation $conversation, User $user): JsonResponse
    {
        $group = $this->groupService->removeMember(
            actor: $request->user(),
            conversation: $conversation,
            targetUser: $user,
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($group),
            ],
            message: 'Anggota berhasil dikeluarkan dari grup.'
        );
    }

    public function leave(Request $request, Conversation $conversation): JsonResponse
    {
        $this->groupService->leaveGroup(
            actor: $request->user(),
            conversation: $conversation,
        );

        return ApiResponse::success(
            data: null,
            message: 'Anda telah keluar dari grup.'
        );
    }
}
