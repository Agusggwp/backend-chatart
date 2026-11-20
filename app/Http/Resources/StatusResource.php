<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $isMine = $currentUserId === $this->user_id;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'type' => $this->type,
            'content' => $this->content ?? '',
            'media_url' => $this->media_url,
            'background_color' => $this->background_color ?? '#128C7E',
            'font_style' => $this->font_style ?? 'default',
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'is_mine' => $isMine,
            'is_viewed' => $currentUserId ? $this->isViewedBy($currentUserId) : false,
            'views_count' => $isMine ? ($this->views_count ?? $this->views()->count()) : null,
        ];
    }
}
