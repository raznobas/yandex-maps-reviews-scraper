<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'source_url' => $this->source_url,
            'normalized_url' => $this->normalized_url,
            'yandex_organization_id' => $this->yandex_organization_id,
            'rating' => $this->rating,
            'rating_count' => $this->rating_count,
            'review_count' => $this->review_count,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'synced_reviews_count' => $this->synced_reviews_count,
            'last_sync_started_at' => $this->last_sync_started_at?->toIso8601String(),
            'last_sync_finished_at' => $this->last_sync_finished_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
