<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_yandex_review_scraper_defaults_are_configured(): void
    {
        $this->assertSame(600, config('services.yandex_reviews.max_reviews'));
        $this->assertSame(50, config('services.yandex_reviews.page_size'));
        $this->assertSame(20, config('services.yandex_reviews.timeout'));
        $this->assertSame(10, config('services.yandex_reviews.connect_timeout'));
    }
}
