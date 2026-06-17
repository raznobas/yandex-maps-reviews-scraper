<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'yandex_review_id' => $this->yandex_review_id,
            'author_name' => $this->author_name,
            'rating' => $this->rating,
            'text' => $this->text,
            'publish_date' => $this->publish_date?->toIso8601String(),
            'created_at' => $this->created_at,
        ];
    }
}
