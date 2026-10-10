<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WEM\AudioTracksBundle\Classes\RemoteFeedFetcher;

class RemoteFeedFetcherTest extends TestCase
{
    public function testFetchReturnsTheBodyAndTheValidators(): void
    {
        $fetcher = $this->fetcher(new MockResponse('<rss/>', ['response_headers' => ['ETag: "abc"', 'Last-Modified: Wed, 01 Jan 2025 10:00:00 GMT']]));

        $this->assertSame(
            ['body' => '<rss/>', 'etag' => '"abc"', 'lastModified' => 'Wed, 01 Jan 2025 10:00:00 GMT'],
            $fetcher->fetch('https://example.org/feed.xml'),
        );
    }

    public function testValidatorsAreMissingWhenTheServerGivesNone(): void
    {
        $result = $this->fetcher(new MockResponse('<rss/>'))->fetch('https://example.org/feed.xml');

        $this->assertNull($result['etag']);
        $this->assertNull($result['lastModified']);
    }

    public function testTheValidatorsOfTheLastImportAreSent(): void
    {
        $requests = [];
        $this->fetcher(new MockResponse('<rss/>'), $requests)->fetch('https://example.org/feed.xml', '"abc"', 'Wed, 01 Jan 2025 10:00:00 GMT');

        $headers = $requests[0]['headers'];
        $this->assertSame(['If-None-Match: "abc"'], $headers['if-none-match']);
        $this->assertSame(['If-Modified-Since: Wed, 01 Jan 2025 10:00:00 GMT'], $headers['if-modified-since']);
        $this->assertArrayHasKey('user-agent', $headers);
    }

    public function testNotModifiedReturnsNull(): void
    {
        $this->assertNull($this->fetcher(new MockResponse('', ['http_code' => 304]))->fetch('https://example.org/feed.xml', '"abc"'));
    }

    #[DataProvider('failures')]
    public function testFailuresAreRuntimeExceptions(MockResponse|callable $response, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches($message);

        $this->fetcher($response)->fetch('https://example.org/feed.xml');
    }

    public static function failures(): iterable
    {
        yield 'server error' => [new MockResponse('oops', ['http_code' => 503]), '/HTTP 503/'];
        yield 'not found' => [new MockResponse('', ['http_code' => 404]), '/HTTP 404/'];
        yield 'empty body' => [new MockResponse('  '), '/empty/'];
        yield 'network error' => [static fn () => throw new TransportException('Connection timed out'), '/timed out/'];
        yield 'too large' => [new MockResponse('x', ['response_headers' => ['Content-Length: '.(RemoteFeedFetcher::MAX_SIZE + 1)]]), '/larger than/'];
    }

    public function testOnlyHttpUrlsAreAccepted(): void
    {
        foreach (['file:///etc/passwd', 'ftp://example.org/feed.xml', 'gopher://x', 'not a url'] as $url) {
            try {
                $this->fetcher(new MockResponse('<rss/>'))->fetch($url);
                $this->fail('"'.$url.'" must be refused');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('http(s)', $e->getMessage());
            }
        }
    }

    private function fetcher(MockResponse|callable $response, array &$requests = []): RemoteFeedFetcher
    {
        return new RemoteFeedFetcher(new MockHttpClient(
            static function (string $method, string $url, array $options) use ($response, &$requests) {
                $requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['normalized_headers'] ?? []];

                return \is_callable($response) ? $response($method, $url, $options) : $response;
            },
        ));
    }
}
