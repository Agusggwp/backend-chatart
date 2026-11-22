<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Call\InitiateCallRequest;
use App\Http\Requests\Call\SendCallSignalRequest;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Services\CallService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(
        private readonly CallService $callService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $paginator = $this->callService->listCalls($request->user(), $perPage);

        return ApiResponse::success(
            data: [
                'calls' => CallResource::collection($paginator->items()),
            ],
            message: 'Riwayat panggilan berhasil diambil.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(InitiateCallRequest $request): JsonResponse
    {
        $call = $this->callService->initiateCall(
            caller: $request->user(),
            receiverId: (int) $request->validated('receiver_id'),
            conversationId: $request->validated('conversation_id') ? (int) $request->validated('conversation_id') : null,
            type: $request->validated('type', Call::TYPE_AUDIO),
        );

        return ApiResponse::success(
            data: [
                'call' => new CallResource($call),
            ],
            message: 'Panggilan sedang dimulai.',
            status: 201
        );
    }

    public function accept(Request $request, Call $call): JsonResponse
    {
        $updated = $this->callService->acceptCall($request->user(), $call);

        return ApiResponse::success(
            data: [
                'call' => new CallResource($updated),
            ],
            message: 'Panggilan diterima.'
        );
    }

    public function reject(Request $request, Call $call): JsonResponse
    {
        $updated = $this->callService->rejectCall($request->user(), $call);

        return ApiResponse::success(
            data: [
                'call' => new CallResource($updated),
            ],
            message: 'Panggilan ditolak.'
        );
    }

    public function end(Request $request, Call $call): JsonResponse
    {
        $updated = $this->callService->endCall($request->user(), $call);

        return ApiResponse::success(
            data: [
                'call' => new CallResource($updated),
            ],
            message: 'Panggilan telah berakhir.'
        );
    }

    public function signal(SendCallSignalRequest $request, Call $call): JsonResponse
    {
        $this->callService->sendSignal(
            user: $request->user(),
            call: $call,
            signalType: (string) $request->validated('signal_type'),
            signalData: $request->input('signal_data'),
        );

        return ApiResponse::success(
            data: null,
            message: 'Sinyal WebRTC berhasil dikirim.'
        );
    }
}
