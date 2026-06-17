<?php

namespace Tests\Feature\Unit\Services;

use App\Services\YandexScraperService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class YandexScraperServiceTest extends TestCase
{
    public function test_scraper_parses_html_correctly()
    {
        Http::fake([
            'yandex.ru/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 2,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'Great place.'),
                    $this->reviewHtml('review-2', 'Bob', 4.0, 'Good service.'),
                ],
            ), 200),
        ]);

        $result = (new YandexScraperService)->scrape('191403044676');

        $this->assertEquals(5.0, $result['rating']);
        $this->assertEquals(2, $result['review_count']);
        $this->assertEquals(2, $result['rating_count']);
        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['reviews']);
        $this->assertEquals('Alice', $result['reviews'][0]['author_name']);
        $this->assertArrayNotHasKey('author_avatar_url', $result['reviews'][0]);
        $this->assertEquals(5.0, $result['reviews'][0]['rating']);
    }

    public function test_scraper_fetches_configured_review_pages_until_limit()
    {
        Config::set('services.yandex_reviews.page_url', 'https://reviews.example.test/{organization_id}?offset={offset}&limit={limit}');
        Config::set('services.yandex_reviews.page_size', 2);

        Http::fake([
            'yandex.ru/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 120,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                    $this->reviewHtml('review-2', 'Bob', 4.0, 'Two.'),
                ],
            ), 200),
            'reviews.example.test/123?offset=2&limit=2' => Http::response($this->reviewsJson([
                [
                    'reviewId' => 'review-3',
                    'author' => ['name' => 'Chris', 'avatarUrl' => ''],
                    'rating' => 3,
                    'text' => 'Three.',
                    'updatedTime' => '2026-06-15T10:00:00.000Z',
                ],
            ], count: 120, page: 2), 200),
        ]);

        $result = (new YandexScraperService)->scrape('123', maxReviews: 3);

        $this->assertEquals('partial', $result['status']);
        $this->assertCount(3, $result['reviews']);
        $this->assertEquals('review-3', $result['reviews'][2]['yandex_review_id']);
        $this->assertStringContainsString('настроенном лимите', $result['error']);
    }

    public function test_scraper_fetches_yandex_reviews_api_when_context_exists()
    {
        Config::set('services.yandex_reviews.page_size', 2);

        Http::fake([
            'https://yandex.ru/maps/org/org/123/reviews/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 3,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                    $this->reviewHtml('review-2', 'Bob', 4.0, 'Two.'),
                ],
                extraHtml: '<script>{"csrfToken":"csrf-token","sessionId":"session-id","requestId":"1781607507697608-2823956806-addrs-upper-yp-121"}</script>',
            ), 200),
            'https://yandex.ru/maps/api/business/fetchReviews*' => Http::response($this->reviewsJson([
                [
                    'reviewId' => 'review-3',
                    'author' => ['name' => 'Chris', 'avatarUrl' => 'https://avatars.example/{size}'],
                    'rating' => 4,
                    'text' => 'Three.',
                    'updatedTime' => '2026-06-15T10:00:00.000Z',
                ],
            ], count: 3, page: 2), 200),
        ]);

        $result = (new YandexScraperService)->scrape('123', maxReviews: 3);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(3, $result['reviews']);
        $this->assertEquals('review-3', $result['reviews'][2]['yandex_review_id']);
        $this->assertArrayNotHasKey('author_avatar_url', $result['reviews'][2]);
    }

    public function test_scraper_reports_partial_when_no_next_page_source_exists()
    {
        Http::fake([
            'yandex.ru/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 3,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                ],
            ), 200),
        ]);

        $result = (new YandexScraperService)->scrape('123', maxReviews: 3);

        $this->assertEquals('partial', $result['status']);
        $this->assertCount(1, $result['reviews']);
    }

    public function test_scraper_treats_duplicate_source_review_blocks_as_complete_when_declared_count_is_fetched()
    {
        Config::set('services.yandex_reviews.page_url', 'https://reviews.example.test/{organization_id}?offset={offset}&limit={limit}');
        Config::set('services.yandex_reviews.page_size', 2);

        Http::fake([
            'yandex.ru/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 3,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                ],
            ), 200),
            'reviews.example.test/123?offset=1&limit=2' => Http::response($this->reviewsJson([
                [
                    'reviewId' => 'review-2',
                    'author' => ['name' => 'Bob', 'avatarUrl' => ''],
                    'rating' => 4,
                    'text' => 'Two.',
                    'updatedTime' => '2026-06-15T10:00:00.000Z',
                ],
            ], count: 3, page: 2), 200),
        ]);

        $result = (new YandexScraperService)->scrape('123');

        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['error']);
        $this->assertEquals(3, $result['review_count']);
        $this->assertCount(2, $result['reviews']);
    }

    public function test_scraper_does_not_refetch_api_when_first_page_contains_all_reviews()
    {
        Http::fake([
            'https://yandex.ru/maps/org/org/123/reviews/*' => Http::response($this->reviewsPageHtml(
                reviewCount: 2,
                reviews: [
                    $this->reviewHtml('review-1', 'Alice', 5.0, 'One.'),
                    $this->reviewHtml('review-2', 'Bob', 4.0, 'Two.'),
                ],
                extraHtml: '<script>{"csrfToken":"csrf-token","sessionId":"session-id","requestId":"1781607507697608-2823956806-addrs-upper-yp-121"}</script>',
            ), 200),
            'https://yandex.ru/maps/api/business/fetchReviews*' => Http::response($this->reviewsJson([
                [
                    'reviewId' => 'review-1-json',
                    'author' => ['name' => 'Alice', 'avatarUrl' => ''],
                    'rating' => 5,
                    'text' => 'One.',
                    'updatedTime' => '2026-06-15T10:00:00.000Z',
                ],
            ], count: 2, page: 1), 200),
        ]);

        $result = (new YandexScraperService)->scrape('123', maxReviews: 600);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['reviews']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/maps/api/business/fetchReviews'));
    }

    public function test_scraper_fails_on_bot_protection_page()
    {
        Http::fake([
            'yandex.ru/*' => Http::response('<html><body>smartcaptcha</body></html>', 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('защиты от ботов');

        (new YandexScraperService)->scrape('123');
    }

    /**
     * @param  array<int, string>  $reviews
     */
    private function reviewsPageHtml(int $reviewCount, array $reviews, string $extraHtml = ''): string
    {
        $reviewHtml = implode('', $reviews);

        return <<<HTML
            <html>
                <head>
                    <meta itemprop="reviewCount" content="{$reviewCount}">
                    <meta itemprop="ratingCount" content="{$reviewCount}">
                </head>
                <body>
                    <div class="business-rating-badge-view__rating-text">5,0</div>
                    {$reviewHtml}
                    {$extraHtml}
                </body>
            </html>
        HTML;
    }

    private function reviewHtml(string $id, string $author, float $rating, string $text): string
    {
        return <<<HTML
            <div class="business-reviews-card-view__review">
                <a class="business-review-view__user-icon" href="/user/{$id}"></a>
                <span itemprop="name">{$author}</span>
                <meta itemprop="ratingValue" content="{$rating}">
                <span class="spoiler-view__text-container">{$text}</span>
                <meta itemprop="datePublished" content="2026-06-16">
            </div>
        HTML;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     */
    private function reviewsJson(array $reviews, int $count, int $page): string
    {
        return json_encode([
            'data' => [
                'reviews' => $reviews,
                'params' => [
                    'offset' => ($page - 1) * 50,
                    'limit' => 50,
                    'count' => $count,
                    'page' => $page,
                    'totalPages' => (int) ceil($count / 50),
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
