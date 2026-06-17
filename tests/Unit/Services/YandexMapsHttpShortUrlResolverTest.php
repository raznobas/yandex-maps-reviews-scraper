<?php

namespace Tests\Unit\Services;

use App\Services\YandexMapsHttpShortUrlResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsHttpShortUrlResolverTest extends TestCase
{
    public function test_it_resolves_short_yandex_maps_url_from_location_header(): void
    {
        $shortUrl = 'https://yandex.ru/maps/-/CPx6aH5G';
        $resolvedUrl = 'https://yandex.ru/maps/org/some_slug/191403044676/';

        Http::preventStrayRequests();
        Http::fake([
            $shortUrl => Http::response('', 302, [
                'Location' => $resolvedUrl,
            ]),
            $resolvedUrl => Http::response('', 200),
        ]);

        $this->assertSame($resolvedUrl, (new YandexMapsHttpShortUrlResolver)->resolve($shortUrl));

        Http::assertSent(fn ($request): bool => $request->method() === 'HEAD' && $request->url() === $shortUrl);
    }

    public function test_it_resolves_short_yandex_com_maps_url_from_location_header(): void
    {
        $shortUrl = 'https://yandex.com/maps/-/CPxs6S2R';
        $resolvedUrl = 'https://yandex.com/maps/org/some_slug/191403044676/';

        Http::preventStrayRequests();
        Http::fake([
            $shortUrl => Http::response('', 302, [
                'Location' => $resolvedUrl,
            ]),
            $resolvedUrl => Http::response('', 200),
        ]);

        $this->assertSame($resolvedUrl, (new YandexMapsHttpShortUrlResolver)->resolve($shortUrl));

        Http::assertSent(fn ($request): bool => $request->method() === 'HEAD' && $request->url() === $shortUrl);
    }

    public function test_it_returns_original_url_when_url_is_not_short(): void
    {
        $url = 'https://yandex.ru/maps/org/some_slug/191403044676/';

        Http::preventStrayRequests();

        $this->assertSame($url, (new YandexMapsHttpShortUrlResolver)->resolve($url));
        Http::assertNothingSent();
    }
}
