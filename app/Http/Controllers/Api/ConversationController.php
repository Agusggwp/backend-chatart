<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->chatService->listConversations($request->user());

        return ApiResponse::success(
            data: [
                'conversations' => ConversationResource::collection($conversations),
            ],
            message: 'Daftar percakapan berhasil diambil.'
        );
    }

    public function store(StartConversationRequest $request): JsonResponse
    {
        $conversation = $this->chatService->findOrCreatePrivateConversation(
            currentUser: $request->user(),
            otherUserId: (int) $request->validated('user_id'),
        );

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($conversation),
            ],
            message: 'Percakapan siap digunakan.',
            status: 201
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $resolved = $this->chatService->getConversation($request->user(), $conversation);

        return ApiResponse::success(
            data: [
                'conversation' => new ConversationResource($resolved),
            ],
            message: 'Detail percakapan berhasil diambil.'
        );
    }

    public function archived(Request $request): JsonResponse
    {
        $conversations = $this->chatService->listConversations($request->user(), archived: true);

        return ApiResponse::success(
            data: [
                'conversations' => ConversationResource::collection($conversations),
            ],
            message: 'Daftar percakapan diarsipkan berhasil diambil.'
        );
    }

    public function togglePin(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->chatService->togglePinConversation($conversation, $request->user());

        return ApiResponse::success(
            data: $result,
            message: $result['is_pinned'] ? 'Percakapan berhasil disematkan.' : 'Sematkan percakapan dibatalkan.'
        );
    }

    public function toggleArchive(Request $request, Conversation $conversation): JsonResponse
    {
        $result = $this->chatService->toggleArchiveConversation($conversation, $request->user());

        return ApiResponse::success(
            data: $result,
            message: $result['is_archived'] ? 'Percakapan berhasil diarsipkan.' : 'Percakapan dikembalikan dari arsip.'
        );
    }
}
