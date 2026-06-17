<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOrganizationReviews implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public Organization $organization)
    {
        //
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("sync-organization-reviews:{$this->organization->id}"))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Execute the job.
     */
    public function handle(YandexScraperService $scraper): void
    {
        try {
            $this->organization->update([
                'sync_status' => 'running',
                'sync_error' => null,
                'last_sync_started_at' => now(),
            ]);

            $data = $scraper->scrape($this->organization->yandex_organization_id);

            DB::transaction(function () use ($data) {
                // Update organization metrics
                $this->organization->update([
                    'rating' => $data['rating'],
                    'review_count' => $data['review_count'],
                    'rating_count' => $data['rating_count'] ?? 0,
                    'last_synced_at' => now(),
                    'sync_status' => $data['status'] ?? 'success',
                    'sync_error' => $data['error'] ?? null,
                    'synced_reviews_count' => count($data['reviews']),
                    'last_sync_finished_at' => now(),
                ]);

                $this->organization->reviews()->delete();

                // Sync reviews (upsert based on yandex_review_id)
                foreach ($data['reviews'] as $reviewData) {
                    $this->organization->reviews()->updateOrCreate(
                        ['yandex_review_id' => $reviewData['yandex_review_id']],
                        [
                            'author_name' => $reviewData['author_name'],
                            'rating' => $reviewData['rating'],
                            'text' => $reviewData['text'],
                            'publish_date' => $reviewData['publish_date'],
                        ]
                    );
                }
            });

            Log::info("Successfully synced reviews for organization: {$this->organization->id}");
        } catch (Throwable $e) {
            $this->organization->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'last_sync_finished_at' => now(),
            ]);

            Log::error("Failed to sync reviews for organization {$this->organization->id}: ".$e->getMessage());

            throw $e;
        }
    }
}
