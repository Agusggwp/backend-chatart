<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Status\CreateStatusRequest;
use App\Http\Resources\StatusResource;
use App\Http\Resources\StatusViewerResource;
use App\Http\Resources\UserResource;
use App\Models\Status;
use App\Services\StatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatusController extends Controller
{
    public function __construct(
        protected StatusService $statusService
    ) {}

    /**
     * List all active statuses grouped by my status, recent updates, and viewed updates.
     */
    public function index(Request $request): JsonResponse
    {
        $grouped = $this->statusService->getStatusesGrouped($request->user());

        return ApiResponse::success([
            'my_statuses' => StatusResource::collection($grouped['my_statuses']),
            'recent_updates' => $grouped['recent_updates']->map(function ($item) {
                return [
                    'user' => new UserResource($item['user']),
                    'statuses' => StatusResource::collection($item['statuses']),
                    'has_unviewed' => $item['has_unviewed'],
                    'latest_at' => $item['latest_at']?->toISOString(),
                ];
            }),
            'viewed_updates' => $grouped['viewed_updates']->map(function ($item) {
                return [
                    'user' => new UserResource($item['user']),
                    'statuses' => StatusResource::collection($item['statuses']),
                    'has_unviewed' => $item['has_unviewed'],
                    'latest_at' => $item['latest_at']?->toISOString(),
                ];
            }),
        ], 'Daftar status berhasil diambil.');
    }

    /**
     * Create a new text or image status.
     */
    public function store(CreateStatusRequest $request): JsonResponse
    {
        $media = $request->file('media');
        $status = $this->statusService->createStatus($request->user(), $request->validated(), $media);

        return ApiResponse::success(
            new StatusResource($status),
            'Status berhasil diperbarui.',
            Response::HTTP_CREATED
        );
    }

    /**
     * Mark a status as viewed by the authenticated user.
     */
    public function markView(Request $request, Status $status): JsonResponse
    {
        $this->statusService->markViewed($request->user(), $status);

        return ApiResponse::success(null, 'Status ditandai telah dilihat.');
    }

    /**
     * Get the viewers of a status (author only).
     */
    public function viewers(Request $request, Status $status): JsonResponse
    {
        $viewers = $this->statusService->getStatusViewers($request->user(), $status);

        return ApiResponse::success(
            StatusViewerResource::collection($viewers),
            'Daftar penonton status berhasil diambil.'
        );
    }

    /**
     * Delete a status (author only).
     */
    public function destroy(Request $request, Status $status): JsonResponse
    {
        $this->statusService->deleteStatus($request->user(), $status);

        return ApiResponse::success(null, 'Status berhasil dihapus.');
    }
}
