<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a remote RSS feed with limits (time, size) and conditional requests
 * (ETag / Last-Modified), so a slow or huge feed cannot block the cron, and an
 * unchanged feed is not downloaded again.
 */
class RemoteFeedFetcher
{
    // Seconds without any data before giving up
    public const TIMEOUT = 10;

    // Seconds for the whole download
    public const MAX_DURATION = 30;

    // Maximum size of a feed, in bytes
    public const MAX_SIZE = 10 * 1024 * 1024;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{body: string, etag: string|null, lastModified: string|null}|null null if the feed has not changed
     *
     * @throws \RuntimeException If the feed cannot be downloaded
     */
    public function fetch(string $url, string|null $etag = null, string|null $lastModified = null): array|null
    {
        if (!\in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new \RuntimeException(\sprintf('"%s" is not an http(s) url', $url));
        }

        $headers = [
            'Accept' => 'application/rss+xml, application/xml;q=0.9, text/xml;q=0.8, */*;q=0.5',
            'User-Agent' => 'Contao-Audiotracks (+https://github.com/Web-Ex-Machina/contao-audiotracks)',
        ];

        if ($etag) {
            $headers['If-None-Match'] = $etag;
        }

        if ($lastModified) {
            $headers['If-Modified-Since'] = $lastModified;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'headers' => $headers,
                    'timeout' => self::TIMEOUT,
                    'max_duration' => self::MAX_DURATION,
                    'on_progress' => static function (int $downloaded, int $total): void {
                        if ($downloaded > self::MAX_SIZE || $total > self::MAX_SIZE) {
                            throw new \RuntimeException(\sprintf('the feed is larger than %d MB', self::MAX_SIZE / 1048576));
                        }
                    },
                ],
            );

            $status = $response->getStatusCode();

            if (304 === $status) {
                return null;
            }

            if (200 !== $status) {
                throw new \RuntimeException(\sprintf('the server answered with HTTP %d', $status));
            }

            $body = $response->getContent();
            $responseHeaders = $response->getHeaders(false);
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        if ('' === trim($body)) {
            throw new \RuntimeException('the feed is empty');
        }

        return [
            'body' => $body,
            'etag' => $responseHeaders['etag'][0] ?? null,
            'lastModified' => $responseHeaders['last-modified'][0] ?? null,
        ];
    }
}
