<?php

namespace App\Services;

use App\Contracts\YandexMapsShortUrlResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

class YandexMapsHttpShortUrlResolver implements YandexMapsShortUrlResolver
{
    private const SHORT_URL_HOSTS = [
        'yandex.ru',
        'www.yandex.ru',
        'yandex.com',
        'www.yandex.com',
    ];

    public function resolve(string $url): string
    {
        if (! $this->isShortYandexMapsUrl($url)) {
            return $url;
        }

        try {
            $response = Http::timeout((int) config('services.yandex_reviews.timeout', 20))
                ->connectTimeout((int) config('services.yandex_reviews.connect_timeout', 10))
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                        'protocols' => ['https'],
                    ],
                ])
                ->head($url);

            $effectiveUri = $response->effectiveUri();
            $redirectUrl = $effectiveUri
                ? (string) $effectiveUri
                : $response->header('Location') ?? $response->header('X-Guzzle-Redirect-History');

            return is_string($redirectUrl) && $redirectUrl !== '' ? $redirectUrl : $url;
        } catch (Throwable) {
            return $url;
        }
    }

    private function isShortYandexMapsUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! isset($parsed['host'], $parsed['path'])) {
            return false;
        }

        return in_array(strtolower($parsed['host']), self::SHORT_URL_HOSTS, true)
            && str_starts_with($parsed['path'], '/maps/-/');
    }
}
