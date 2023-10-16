<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $this->image,
            'type' => $this->type,
            'link' => $this->link,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'from' => $this->whenLoaded('from_who', fn () => new UserResource($this->from_who))
        ];
    }
}
