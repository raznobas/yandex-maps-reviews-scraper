<?php

namespace Tests\Feature\Feature\Scraper;

use App\Jobs\SyncOrganizationReviews;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\YandexScraperService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncReviewsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sync_job_updates_organization_and_creates_reviews()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
            'yandex_organization_id' => '12345',
            'rating' => null,
            'review_count' => 0,
        ]);

        $mockScraper = $this->mock(YandexScraperService::class);
        $mockScraper->shouldReceive('scrape')
            ->once()
            ->with('12345')
            ->andReturn([
                'rating' => 4.5,
                'rating_count' => 10,
                'review_count' => 10,
                'status' => 'partial',
                'error' => 'Загружено 2 из 10 доступных отзывов;',
                'reviews' => [
                    [
                        'yandex_review_id' => 'rev1',
                        'author_name' => 'Alice',
                        'rating' => 5.0,
                        'text' => 'Great!',
                        'publish_date' => now(),
                    ],
                    [
                        'yandex_review_id' => 'rev2',
                        'author_name' => 'Bob',
                        'rating' => 4.0,
                        'text' => 'Good.',
                        'publish_date' => now()->subDay(),
                    ],
                ],
            ]);

        $job = new SyncOrganizationReviews($organization);
        $job->handle(app(YandexScraperService::class));

        $organization->refresh();
        $this->assertEquals(4.5, $organization->rating);
        $this->assertEquals(10, $organization->review_count);
        $this->assertEquals(10, $organization->rating_count);
        $this->assertEquals('partial', $organization->sync_status);
        $this->assertEquals(2, $organization->synced_reviews_count);
        $this->assertNotNull($organization->sync_error);
        $this->assertNotNull($organization->last_synced_at);
        $this->assertNotNull($organization->last_sync_started_at);
        $this->assertNotNull($organization->last_sync_finished_at);

        $this->assertEquals(2, $organization->reviews()->count());
        $this->assertDatabaseHas('reviews', [
            'yandex_review_id' => 'rev1',
            'author_name' => 'Alice',
        ]);
    }

    public function test_sync_job_replaces_existing_cached_reviews()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id, 'yandex_organization_id' => '12345']);

        Review::create([
            'organization_id' => $organization->id,
            'yandex_review_id' => 'rev1',
            'author_name' => 'Old Name',
            'rating' => 3.0,
            'text' => 'Old text',
        ]);
        Review::factory()->create([
            'organization_id' => $organization->id,
            'yandex_review_id' => 'stale-review',
        ]);

        $mockScraper = $this->mock(YandexScraperService::class);
        $mockScraper->shouldReceive('scrape')
            ->once()
            ->andReturn([
                'rating' => 4.8,
                'rating_count' => 1,
                'review_count' => 1,
                'status' => 'success',
                'error' => null,
                'reviews' => [
                    [
                        'yandex_review_id' => 'rev1', // Same ID
                        'author_name' => 'New Name',
                        'rating' => 5.0,
                        'text' => 'New text',
                        'publish_date' => null,
                    ],
                ],
            ]);

        $job = new SyncOrganizationReviews($organization);
        $job->handle(app(YandexScraperService::class));

        $this->assertEquals(1, $organization->reviews()->count()); // Should still be 1
        $this->assertDatabaseMissing('reviews', [
            'organization_id' => $organization->id,
            'yandex_review_id' => 'stale-review',
        ]);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'yandex_review_id' => 'rev1',
            'author_name' => 'New Name',
            'rating' => 5.0,
        ]);
    }

    public function test_sync_job_defines_timeout_retry_and_overlap_middleware()
    {
        $organization = Organization::factory()->create();
        $job = new SyncOrganizationReviews($organization);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals([30, 120, 300], $job->backoff());
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
    }

    public function test_authenticated_user_can_sync_saved_organization_from_api()
    {
        Queue::fake();

        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
            'yandex_organization_id' => '12345',
        ]);

        $this->actingAs($user)
            ->postJson('/api/organization/sync')
            ->assertAccepted()
            ->assertJsonPath('data.sync_status', 'running');

        $organization->refresh();
        $this->assertEquals('running', $organization->sync_status);
        $this->assertNotNull($organization->last_sync_started_at);
        $this->assertNull($organization->last_sync_finished_at);

        Queue::assertPushed(
            SyncOrganizationReviews::class,
            fn (SyncOrganizationReviews $job): bool => $job->organization->is($organization),
        );
        $this->assertEquals(0, $organization->reviews()->count());
    }

    public function test_sync_endpoint_requires_saved_organization()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organization/sync')
            ->assertNotFound();
    }
}
