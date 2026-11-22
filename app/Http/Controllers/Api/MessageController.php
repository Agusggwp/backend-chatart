<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\DeleteMessageRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\TypingRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $paginator = $this->chatService->listMessages($request->user(), $conversation, $perPage);

        return ApiResponse::success(
            data: [
                'messages' => MessageResource::collection($paginator->items()),
            ],
            message: 'Riwayat pesan berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->chatService->sendMessage(
            sender: $request->user(),
            conversation: $conversation,
            body: $request->validated('body'),
            attachment: $request->file('attachment'),
            replyToId: $request->validated('reply_to_id') ? (int) $request->validated('reply_to_id') : null,
            duration: $request->validated('duration') ? (int) $request->validated('duration') : null,
        );

        return ApiResponse::success(
            data: [
                'message' => new MessageResource($message),
            ],
            message: 'Pesan berhasil dikirim.',
            status: 201
        );
    }

    public function update(\App\Http\Requests\Chat\EditMessageRequest $request, Conversation $conversation, Message $message): JsonResponse
    {
        $updated = $this->chatService->editMessage(
            user: $request->user(),
            conversation: $conversation,
            message: $message,
            newBody: $request->validated('body'),
        );

        return ApiResponse::success(
            data: [
                'message' => new MessageResource($updated),
            ],
            message: 'Pesan berhasil diperbarui.'
        );
    }

    public function destroy(DeleteMessageRequest $request, Conversation $conversation, Message $message): JsonResponse
    {
        $deleted = $this->chatService->deleteMessage(
            user: $request->user(),
            conversation: $conversation,
            message: $message,
            deleteType: $request->validated('delete_type'),
        );

        return ApiResponse::success(
            data: [
                'message' => new MessageResource($deleted),
            ],
            message: $request->validated('delete_type') === 'for_everyone'
                ? 'Pesan telah dihapus untuk semua orang.'
                : 'Pesan telah dihapus untuk Anda.'
        );
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $updated = $this->chatService->markAsRead($request->user(), $conversation);

        return ApiResponse::success(
            data: [
                'marked_count' => $updated,
            ],
            message: 'Pesan ditandai sudah dibaca.'
        );
    }

    public function typing(TypingRequest $request, Conversation $conversation): JsonResponse
    {
        $this->chatService->broadcastTyping(
            user: $request->user(),
            conversation: $conversation,
            isTyping: (bool) $request->validated('is_typing'),
        );

        return ApiResponse::success(
            data: null,
            message: 'Status mengetik dikirim.'
        );
    }

    public function toggleStar(Request $request, int $messageId): JsonResponse
    {
        $result = $this->chatService->toggleStarMessage($request->user(), $messageId);

        return ApiResponse::success(
            data: $result,
            message: $result['is_starred'] ? 'Pesan berhasil dibintangi.' : 'Bintang pada pesan telah dihapus.'
        );
    }

    public function starred(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $paginator = $this->chatService->getStarredMessages($request->user(), page: (int) $request->query('page', 1), perPage: $perPage);

        return ApiResponse::success(
            data: [
                'messages' => MessageResource::collection($paginator->items()),
            ],
            message: 'Daftar pesan berbintang berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $paginator = $this->chatService->searchMessages(
            conversation: $conversation,
            user: $request->user(),
            keyword: $keyword,
            page: (int) $request->query('page', 1),
            perPage: $perPage
        );

        return ApiResponse::success(
            data: [
                'messages' => MessageResource::collection($paginator->items()),
            ],
            message: 'Hasil pencarian pesan berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function react(Request $request, int $messageId): JsonResponse
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $result = $this->chatService->reactToMessage($request->user(), $messageId, $validated['emoji']);

        return ApiResponse::success(
            data: $result,
            message: 'Reaksi berhasil diperbarui.'
        );
    }

    public function removeReaction(Request $request, int $messageId): JsonResponse
    {
        $result = $this->chatService->removeReaction($request->user(), $messageId);

        return ApiResponse::success(
            data: $result,
            message: 'Reaksi berhasil dihapus.'
        );
    }

    public function forward(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'integer', 'exists:messages,id'],
            'conversation_ids' => ['required', 'array', 'min:1'],
            'conversation_ids.*' => ['integer', 'exists:conversations,id'],
        ]);

        $created = $this->chatService->forwardMessage(
            user: $request->user(),
            messageId: (int) $validated['message_id'],
            targetConversationIds: $validated['conversation_ids'],
        );

        return ApiResponse::success(
            data: [
                'messages' => MessageResource::collection($created),
            ],
            message: 'Pesan berhasil diteruskan.'
        );
    }
}
