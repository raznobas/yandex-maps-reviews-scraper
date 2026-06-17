<?php

namespace Tests\Feature\Organization;

use App\Contracts\YandexMapsShortUrlResolver;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_unauthenticated_user_cannot_access_organization_settings()
    {
        $this->getJson('/api/organization')->assertStatus(401);
        $this->putJson('/api/organization', ['source_url' => 'https://yandex.ru/maps/org/test/123/'])->assertStatus(401);
    }

    public function test_authenticated_user_gets_null_if_no_organization_saved()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertStatus(200)
            ->assertJson(['data' => null]);
    }

    public function test_user_can_save_valid_yandex_maps_organization_url()
    {
        $user = User::factory()->create();
        $sourceUrl = 'https://www.yandex.ru/maps/org/some_slug/1234567890/?ll=37.123,55.123';

        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => $sourceUrl])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'source_url' => $sourceUrl,
                    'normalized_url' => 'https://yandex.ru/maps/org/some_slug/1234567890/',
                    'yandex_organization_id' => '1234567890',
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_organization_id' => '1234567890',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_user_can_save_short_yandex_maps_url()
    {
        $user = User::factory()->create();
        $shortUrl = 'https://yandex.ru/maps/-/CPx6aH5G';

        Http::preventStrayRequests();

        $this->mock(YandexMapsShortUrlResolver::class)
            ->shouldReceive('resolve')
            ->once()
            ->with($shortUrl)
            ->andReturn('https://yandex.ru/maps/org/some_slug/191403044676/');

        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => $shortUrl])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'yandex_organization_id' => '191403044676',
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_organization_id' => '191403044676',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_user_can_reload_organization_after_saving()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'source_url' => $organization->source_url,
                    'normalized_url' => $organization->normalized_url,
                ],
            ]);
    }

    public function test_saving_new_url_replaces_old_one()
    {
        $user = User::factory()->create();
        Organization::factory()->create(['user_id' => $user->id, 'yandex_organization_id' => '111']);

        $newUrl = 'https://yandex.ru/maps/org/new/222/';
        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => $newUrl])
            ->assertStatus(200);

        $this->assertEquals(1, Organization::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_organization_id' => '222',
        ]);
    }

    public function test_saving_different_organization_clears_previous_cached_reviews()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'user_id' => $user->id,
            'yandex_organization_id' => '111',
            'rating' => 4.8,
            'rating_count' => 10,
            'review_count' => 10,
            'sync_status' => 'success',
            'synced_reviews_count' => 10,
            'last_synced_at' => now(),
        ]);

        Review::factory()->count(3)->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => 'https://yandex.ru/maps/org/new/222/'])
            ->assertOk()
            ->assertJsonPath('data.yandex_organization_id', '222')
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.rating_count', 0)
            ->assertJsonPath('data.review_count', 0)
            ->assertJsonPath('data.sync_status', 'idle')
            ->assertJsonPath('data.synced_reviews_count', 0);

        $this->assertDatabaseMissing('reviews', [
            'organization_id' => $organization->id,
        ]);
    }

    public function test_user_can_get_paginated_reviews()
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['user_id' => $user->id]);

        Review::factory()->count(60)->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/organization/reviews')
            ->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'author_name', 'rating', 'text', 'publish_date'],
                ],
                'links',
                'meta',
            ]);

        $this->assertEquals(60, $response->json('meta.total'));
        $response->assertJsonMissingPath('data.0.author_avatar_url');

        $this->actingAs($user)
            ->getJson('/api/organization/reviews?page=2')
            ->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    public function test_rejects_invalid_domains()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => 'https://google.com/maps'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_url']);
    }

    public function test_rejects_invalid_path_formats()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/organization', ['source_url' => 'https://yandex.ru/maps/search/pizza'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_url']);
    }
}
