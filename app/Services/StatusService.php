<?php

namespace App\Services;

use App\Events\StatusCreated;
use App\Models\Status;
use App\Models\StatusView;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StatusService
{
    /**
     * Get active statuses grouped into my_statuses, recent_updates, and viewed_updates.
     */
    public function getStatusesGrouped(User $currentUser): array
    {
        $activeStatuses = Status::query()
            ->active()
            ->with(['user.presence', 'views'])
            ->withCount('views')
            ->orderBy('created_at', 'desc')
            ->get();

        $myStatuses = $activeStatuses->where('user_id', $currentUser->id)->values();

        $otherGrouped = $activeStatuses->where('user_id', '!=', $currentUser->id)
            ->groupBy('user_id')
            ->map(function (Collection $userStatuses) use ($currentUser) {
                $user = $userStatuses->first()->user;
                $hasUnviewed = $userStatuses->contains(function (Status $s) use ($currentUser) {
                    return !$s->isViewedBy($currentUser->id);
                });
                $latestAt = $userStatuses->first()->created_at;

                return [
                    'user' => $user,
                    'statuses' => $userStatuses->values(),
                    'has_unviewed' => $hasUnviewed,
                    'latest_at' => $latestAt,
                ];
            })
            ->values();

        $recentUpdates = $otherGrouped->where('has_unviewed', true)->sortByDesc('latest_at')->values();
        $viewedUpdates = $otherGrouped->where('has_unviewed', false)->sortByDesc('latest_at')->values();

        return [
            'my_statuses' => $myStatuses,
            'recent_updates' => $recentUpdates,
            'viewed_updates' => $viewedUpdates,
        ];
    }

    /**
     * Create a new status (text or image).
     */
    public function createStatus(User $user, array $data, ?UploadedFile $mediaFile = null): Status
    {
        $type = $data['type'] ?? ($mediaFile ? Status::TYPE_IMAGE : Status::TYPE_TEXT);
        $mediaUrl = null;

        if ($mediaFile) {
            $type = Status::TYPE_IMAGE;
            $dir = "statuses/{$user->id}";
            $path = $mediaFile->store($dir, 'public');
            $mediaUrl = url(Storage::url($path));
        }

        $status = Status::create([
            'user_id' => $user->id,
            'type' => $type,
            'content' => $data['content'] ?? null,
            'media_url' => $mediaUrl,
            'background_color' => $data['background_color'] ?? '#128C7E',
            'font_style' => $data['font_style'] ?? 'default',
            'expires_at' => now()->addHours(24),
        ]);

        $status->load('user.presence');

        event(new StatusCreated($status));

        return $status;
    }

    /**
     * Mark a status as viewed by a user.
     */
    public function markViewed(User $viewer, Status $status): bool
    {
        if ($status->user_id === $viewer->id) {
            return false;
        }

        StatusView::firstOrCreate(
            [
                'status_id' => $status->id,
                'viewer_id' => $viewer->id,
            ],
            [
                'viewed_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Get viewers for a status (author only).
     */
    public function getStatusViewers(User $user, Status $status): Collection
    {
        if ($status->user_id !== $user->id) {
            throw new AccessDeniedHttpException('Anda tidak memiliki akses melihat penonton status ini.');
        }

        return $status->views()
            ->with('viewer.presence')
            ->orderBy('viewed_at', 'desc')
            ->get();
    }

    /**
     * Delete a status (author only).
     */
    public function deleteStatus(User $user, Status $status): bool
    {
        if ($status->user_id !== $user->id) {
            throw new AccessDeniedHttpException('Anda tidak memiliki izin menghapus status ini.');
        }

        if ($status->media_url) {
            $storagePath = str_replace(url(Storage::url('')), '', $status->media_url);
            $storagePath = ltrim($storagePath, '/');
            if (Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }
        }

        return (bool) $status->delete();
    }
}
