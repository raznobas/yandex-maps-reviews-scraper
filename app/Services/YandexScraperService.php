<?php

namespace App\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexScraperService
{
    private ?CookieJar $cookieJar = null;

    /**
     * @return array{
     *     rating: float|null,
     *     review_count: int,
     *     rating_count: int,
     *     reviews: array<int, array<string, mixed>>,
     *     status: string,
     *     error: string|null,
     *     next_page_url: string|null
     * }
     */
    public function scrape(string $yandexId, ?int $maxReviews = null): array
    {
        $maxReviews = $maxReviews ?? (int) config('services.yandex_reviews.max_reviews', 600);
        $pageSize = (int) config('services.yandex_reviews.page_size', 50);
        $url = $this->initialReviewsUrl($yandexId);
        $this->cookieJar = new CookieJar;

        $firstPage = $this->fetchPage($url);
        $result = $this->parseHtml($firstPage->body());
        $reviewsById = $this->keyReviewsById($result['reviews']);
        $apiContext = $this->extractFetchReviewsContext($firstPage->body(), $yandexId, $url);
        $nextPageUrl = $result['next_page_url'];
        $pageError = null;
        $availableReviewCount = $result['review_count'];
        $fetchedReviewCount = count($result['reviews']);
        $nextPageNumber = 2;

        while (
            $fetchedReviewCount < $maxReviews
            && ($availableReviewCount === 0 || $fetchedReviewCount < $availableReviewCount)
        ) {
            $nextPageUrl = $nextPageUrl
                ?: $this->fetchReviewsApiUrl($apiContext, $nextPageNumber, $pageSize)
                ?: $this->configuredPageUrl($yandexId, count($reviewsById), $pageSize, $nextPageNumber);

            if (! $nextPageUrl) {
                break;
            }

            try {
                $pageResponse = $this->fetchPage($nextPageUrl, [
                    'X-Retpath-Y' => $apiContext['retpath'] ?? $url,
                    'Referer' => $apiContext['retpath'] ?? $url,
                ]);
            } catch (RuntimeException $exception) {
                $pageError = $exception->getMessage();
                $nextPageUrl = null;
                break;
            }

            $page = $this->parsePageResponse($pageResponse->body());
            $fetchedReviewCount += count($page['reviews']);
            $beforeCount = count($reviewsById);

            foreach ($page['reviews'] as $review) {
                $reviewsById[$review['yandex_review_id']] = $review;
            }

            if (count($reviewsById) === $beforeCount) {
                $nextPageUrl = null;
                break;
            }

            $nextPageUrl = $page['next_page_url'];
            $nextPageNumber++;
        }

        return [
            'rating' => $result['rating'],
            'review_count' => $availableReviewCount,
            'rating_count' => $result['rating_count'],
            'reviews' => array_slice(array_values($reviewsById), 0, $maxReviews),
            'status' => $this->resolveStatus($availableReviewCount, $fetchedReviewCount),
            'error' => $this->resolveError($availableReviewCount, $fetchedReviewCount, $maxReviews, $pageError),
            'next_page_url' => $nextPageUrl,
        ];
    }

    /**
     * @return array{
     *     rating: float|null,
     *     review_count: int,
     *     rating_count: int,
     *     reviews: array<int, array<string, mixed>>,
     *     next_page_url: string|null
     * }
     */
    private function parseHtml(string $html): array
    {
        if ($this->looksLikeBotProtection($html)) {
            throw new RuntimeException('Яндекс.Карты вернули страницу защиты от ботов вместо отзывов.');
        }

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>');
        $xpath = new DOMXPath($doc);

        $ratingNodes = $xpath->query('//div[contains(@class, "business-rating-badge-view__rating")]/span | //div[contains(@class, "business-rating-badge-view__rating-text")]');
        $rating = null;

        if ($ratingNodes->length > 0) {
            $rating = (float) str_replace(',', '.', $ratingNodes->item(0)->textContent);
        } else {
            $metaRating = $xpath->query('//meta[@itemprop="ratingValue"]');
            if ($metaRating->length > 0) {
                $rating = (float) $metaRating->item(0)->getAttribute('content');
            }
        }

        $reviewCount = 0;
        $ratingCount = 0;

        $reviewCountNodes = $xpath->query('//div[contains(@class, "tabs-select-view__title _name_reviews")]//div[contains(@class, "tabs-select-view__counter")]');
        if ($reviewCountNodes->length > 0) {
            $reviewCount = (int) preg_replace('/\D/', '', $reviewCountNodes->item(0)->textContent);
        }

        $ratingCountNodes = $xpath->query('//meta[@itemprop="reviewCount"] | //meta[@itemprop="ratingCount"]');
        if ($ratingCountNodes->length > 0) {
            $ratingCount = (int) $ratingCountNodes->item(0)->getAttribute('content');
        }

        $reviewCount = $reviewCount ?: $this->extractMetaInteger($html, 'reviewCount');
        $ratingCount = $ratingCount ?: $this->extractMetaInteger($html, 'ratingCount');

        if (preg_match('/<meta[^>]+property="og:description"[^>]+content="(.*?)"/u', $html, $matches)) {
            $description = $matches[1];

            if (preg_match('/(\d+) оценок/u', $description, $ratingMatches)) {
                $ratingCount = (int) $ratingMatches[1];
            }

            if (preg_match('/(\d+) отзывов/u', $description, $reviewMatches)) {
                $reviewCount = (int) $reviewMatches[1];
            }
        }

        if ($reviewCount === 0 || $ratingCount === 0) {
            $ogDescriptionNodes = $xpath->query('//meta[@property="og:description"]');
            if ($ogDescriptionNodes->length > 0) {
                $description = $ogDescriptionNodes->item(0)->getAttribute('content');

                if (preg_match('/(\d+)\s+(?:оценок|оценки|оценка)/u', $description, $matches)) {
                    $ratingCount = (int) $matches[1];
                }

                if (preg_match('/(\d+)\s+(?:отзывов|отзыва|отзыв)/u', $description, $matches)) {
                    $reviewCount = (int) $matches[1];
                }
            }
        }

        $reviews = [];
        $reviewNodes = $xpath->query('//div[contains(@class, "business-reviews-card-view__review")]');

        foreach ($reviewNodes as $node) {
            $reviews[] = $this->parseReviewBlock($doc->saveHTML($node));
        }

        return [
            'rating' => $rating,
            'review_count' => $reviewCount,
            'rating_count' => $ratingCount,
            'reviews' => $reviews,
            'next_page_url' => $this->extractNextPageUrl($xpath),
        ];
    }

    /**
     * @return array{
     *     rating: null,
     *     review_count: int,
     *     rating_count: int,
     *     reviews: array<int, array<string, mixed>>,
     *     next_page_url: null
     * }
     */
    private function parseJson(string $json): array
    {
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            return [
                'rating' => null,
                'review_count' => 0,
                'rating_count' => 0,
                'reviews' => [],
                'next_page_url' => null,
            ];
        }

        $reviews = [];
        foreach ($this->extractReviewPayloads($payload) as $reviewPayload) {
            $reviews[] = $this->parseReviewPayload($reviewPayload);
        }

        $params = $this->extractReviewsParams($payload);

        return [
            'rating' => null,
            'review_count' => (int) ($params['count'] ?? count($reviews)),
            'rating_count' => 0,
            'reviews' => $reviews,
            'next_page_url' => null,
        ];
    }

    /**
     * @return array{
     *     rating: float|null,
     *     review_count: int,
     *     rating_count: int,
     *     reviews: array<int, array<string, mixed>>,
     *     next_page_url: string|null
     * }
     */
    private function parsePageResponse(string $body): array
    {
        $trimmedBody = trim($body);

        if ($trimmedBody !== '' && in_array($trimmedBody[0], ['{', '['], true)) {
            return $this->parseJson($trimmedBody);
        }

        return $this->parseHtml($body);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseReviewBlock(string $html): array
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>');
        $xpath = new DOMXPath($doc);

        $yandexReviewId = md5($html);
        $linkNodes = $xpath->query('//a[contains(@class, "business-review-view__user-icon")]');
        if ($linkNodes->length > 0) {
            $href = $linkNodes->item(0)->getAttribute('href');
            if (preg_match('/user\/([^\/\?]+)/', $href, $matches)) {
                $yandexReviewId = $matches[1];
            }
        }

        $authorNodes = $xpath->query('//span[@itemprop="name"]');
        $authorName = $authorNodes->length > 0 ? trim($authorNodes->item(0)->textContent) : 'Anonymous';

        $ratingNodes = $xpath->query('//meta[@itemprop="ratingValue"]');
        $rating = $ratingNodes->length > 0 ? (float) $ratingNodes->item(0)->getAttribute('content') : 0.0;

        $textNodes = $xpath->query('//span[contains(@class, "spoiler-view__text-container")]');
        $text = $textNodes->length > 0 ? trim($textNodes->item(0)->textContent) : '';

        $publishDate = null;
        $dateNodes = $xpath->query('//meta[@itemprop="datePublished"]');
        if ($dateNodes->length > 0) {
            try {
                $publishDate = Carbon::parse($dateNodes->item(0)->getAttribute('content'));
            } catch (\Exception) {
                $publishDate = null;
            }
        }

        return [
            'yandex_review_id' => $yandexReviewId,
            'author_name' => $authorName,
            'rating' => $rating,
            'text' => $text,
            'publish_date' => $publishDate,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function fetchPage(string $url, array $headers = []): Response
    {
        $response = Http::withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept' => '*/*',
        ], $headers))
            ->withOptions([
                'cookies' => $this->cookieJar,
            ])
            ->timeout((int) config('services.yandex_reviews.timeout', 20))
            ->connectTimeout((int) config('services.yandex_reviews.connect_timeout', 10))
            ->retry(
                (int) config('services.yandex_reviews.retry_attempts', 3),
                (int) config('services.yandex_reviews.retry_sleep', 250),
                throw: false
            )
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Не удалось загрузить страницу Яндекс.Карт: '.$response->status());
        }

        return $response;
    }

    private function initialReviewsUrl(string $yandexId): string
    {
        return "https://yandex.ru/maps/org/org/{$yandexId}/reviews/";
    }

    private function configuredPageUrl(string $yandexId, int $offset, int $limit, int $page): ?string
    {
        $template = config('services.yandex_reviews.page_url');

        if (! is_string($template) || $template === '') {
            return null;
        }

        return strtr($template, [
            '{organization_id}' => $yandexId,
            '{offset}' => (string) $offset,
            '{limit}' => (string) $limit,
            '{page}' => (string) $page,
        ]);
    }

    /**
     * @return array{business_id: string, csrf_token: string|null, session_id: string|null, req_id: string|null, retpath: string}
     */
    private function extractFetchReviewsContext(string $html, string $yandexId, string $retpath): array
    {
        return [
            'business_id' => $yandexId,
            'csrf_token' => $this->extractJsonString($html, 'csrfToken'),
            'session_id' => $this->extractJsonString($html, 'sessionId'),
            'req_id' => $this->extractAddrsRequestId($html),
            'retpath' => $retpath,
        ];
    }

    /**
     * @param  array{business_id: string, csrf_token: string|null, session_id: string|null, req_id: string|null, retpath: string}  $context
     */
    private function fetchReviewsApiUrl(array $context, int $page, int $pageSize): ?string
    {
        if (! $context['csrf_token'] || ! $context['session_id'] || ! $context['req_id']) {
            return null;
        }

        $query = [
            'ajax' => 1,
            'businessId' => $context['business_id'],
            'csrfToken' => $context['csrf_token'],
            'locale' => 'ru_RU',
            'page' => $page,
            'pageSize' => $pageSize,
            'ranking' => 'by_relevance_org',
            'reqId' => $context['req_id'],
            'sessionId' => $context['session_id'],
        ];

        $query['s'] = $this->signYandexQuery($query);

        return 'https://yandex.ru/maps/api/business/fetchReviews?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<string, array<string, mixed>>
     */
    private function keyReviewsById(array $reviews): array
    {
        $reviewsById = [];

        foreach ($reviews as $review) {
            $reviewsById[$review['yandex_review_id']] = $review;
        }

        return $reviewsById;
    }

    private function extractNextPageUrl(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query(
            '//a[@rel="next"]/@href | //*[@data-next-url]/@data-next-url | //*[@data-reviews-next-url]/@data-reviews-next-url'
        );

        if ($nodes->length === 0) {
            return null;
        }

        $url = trim($nodes->item(0)->nodeValue);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return 'https://yandex.ru'.$url;
        }

        return $url;
    }

    private function extractJsonString(string $text, string $key): ?string
    {
        $quotedKey = preg_quote($key, '/');

        if (! preg_match('/"'.$quotedKey.'":"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u', $text, $matches)) {
            return null;
        }

        $decoded = json_decode('"'.$matches[1].'"');

        return is_string($decoded) ? $decoded : $matches[1];
    }

    private function extractAddrsRequestId(string $html): ?string
    {
        preg_match_all('/"requestId":"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u', $html, $matches);

        foreach ($matches[1] ?? [] as $requestId) {
            $decoded = json_decode('"'.$requestId.'"');
            $requestId = is_string($decoded) ? $decoded : $requestId;

            if (str_contains($requestId, 'addrs-upper')) {
                return $requestId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function signYandexQuery(array $query): string
    {
        uksort($query, fn (string $left, string $right): int => strtolower($left) <=> strtolower($right));

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $hash = 5381;

        for ($index = 0, $length = strlen($queryString); $index < $length; $index++) {
            $hash = ((33 * $hash) ^ ord($queryString[$index])) & 0xFFFFFFFF;
        }

        return (string) $hash;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractReviewPayloads(array $payload): array
    {
        $reviews = [];

        $walk = function (mixed $value) use (&$walk, &$reviews): void {
            if (! is_array($value)) {
                return;
            }

            if (isset($value['reviewId'], $value['author']) && array_key_exists('rating', $value)) {
                $reviews[$value['reviewId']] = $value;

                return;
            }

            foreach ($value as $child) {
                $walk($child);
            }
        };

        $walk($payload);

        return array_values($reviews);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractReviewsParams(array $payload): array
    {
        $params = [];

        $walk = function (mixed $value) use (&$walk, &$params): void {
            if ($params !== [] || ! is_array($value)) {
                return;
            }

            if (isset($value['count'], $value['page'], $value['totalPages'])) {
                $params = $value;

                return;
            }

            foreach ($value as $child) {
                $walk($child);
            }
        };

        $walk($payload);

        return $params;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function parseReviewPayload(array $payload): array
    {
        return [
            'yandex_review_id' => (string) $payload['reviewId'],
            'author_name' => (string) ($payload['author']['name'] ?? 'Anonymous'),
            'rating' => (float) ($payload['rating'] ?? 0),
            'text' => (string) ($payload['text'] ?? ''),
            'publish_date' => $this->parsePayloadDate($payload['updatedTime'] ?? null),
        ];
    }

    private function parsePayloadDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function looksLikeBotProtection(string $html): bool
    {
        return str_contains($html, 'showcaptcha')
            || str_contains($html, 'smartcaptcha')
            || str_contains($html, 'Подтвердите, что запросы отправляли вы')
            || str_contains($html, 'Confirm that you are not a robot');
    }

    private function extractMetaInteger(string $html, string $itemprop): int
    {
        $quotedItemprop = preg_quote($itemprop, '/');

        if (preg_match('/<meta[^>]+itemprop=["\']'.$quotedItemprop.'["\'][^>]+content=["\'](\d+)["\']/iu', $html, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/<meta[^>]+content=["\'](\d+)["\'][^>]+itemprop=["\']'.$quotedItemprop.'["\']/iu', $html, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function resolveStatus(int $availableReviewCount, int $fetchedReviewCount): string
    {
        if ($fetchedReviewCount === 0) {
            return $availableReviewCount > 0 ? 'partial' : 'empty';
        }

        if ($availableReviewCount > 0 && $fetchedReviewCount < $availableReviewCount) {
            return 'partial';
        }

        return 'success';
    }

    private function resolveError(int $availableReviewCount, int $fetchedReviewCount, int $maxReviews, ?string $pageError = null): ?string
    {
        if ($availableReviewCount === 0 || $fetchedReviewCount >= $availableReviewCount) {
            return null;
        }

        if ($pageError) {
            return "Загружено {$fetchedReviewCount} из {$availableReviewCount} доступных отзывов; запрос следующей страницы завершился ошибкой: {$pageError}";
        }

        if ($fetchedReviewCount >= $maxReviews) {
            return "Загружено {$fetchedReviewCount} отзывов. Остановлено на настроенном лимите.";
        }

        return "Загружено {$fetchedReviewCount} из {$availableReviewCount} доступных отзывов";
    }
}
