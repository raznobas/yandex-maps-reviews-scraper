<?php

namespace App\Services;

use App\Contracts\YandexMapsShortUrlResolver;

class YandexOrganizationSourceUrlResolver
{
    private const ALLOWED_HOSTS = [
        'yandex.ru',
        'www.yandex.ru',
        'yandex.com',
        'www.yandex.com',
    ];

    public function __construct(private YandexMapsShortUrlResolver $shortUrlResolver) {}

    /**
     * @return array{normalized_url?: string, yandex_organization_id?: string, error?: 'invalid_url'|'invalid_domain'|'invalid_path'}
     */
    public function resolve(string $url): array
    {
        $resolvedUrl = $this->shortUrlResolver->resolve($url);
        $parsed = parse_url($resolvedUrl);

        if (! isset($parsed['host'], $parsed['path'])) {
            return ['error' => 'invalid_url'];
        }

        $host = strtolower($parsed['host']);

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            return ['error' => 'invalid_domain'];
        }

        if (! preg_match('#^/maps/org/([^/]+)/(\d+)/?#', $parsed['path'], $matches)) {
            return ['error' => 'invalid_path'];
        }

        $normalizedHost = str_replace('www.', '', $host);
        $slug = $matches[1];
        $id = $matches[2];

        return [
            'normalized_url' => "https://{$normalizedHost}/maps/org/{$slug}/{$id}/",
            'yandex_organization_id' => $id,
        ];
    }
}
