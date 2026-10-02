<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use WEM\AudioTracksBundle\Classes\ClientIdentifier;
use WEM\AudioTracksBundle\Classes\RequestRateLimiter;
use WEM\UtilsBundle\Classes\Encryption;

class RequestRateLimiterTest extends TestCase
{
    public function testRequestsUnderTheLimitPass(): void
    {
        $limiter = $this->limiter(3);

        $this->assertNull($limiter->consume());
        $this->assertNull($limiter->consume());
        $this->assertNull($limiter->consume());
    }

    public function testRequestsOverTheLimitGetA429(): void
    {
        $limiter = $this->limiter(2);
        $limiter->consume();
        $limiter->consume();

        $response = $limiter->consume();

        $this->assertNotNull($response);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('error', json_decode((string) $response->getContent(), true)['status']);
    }

    private function limiter(int $limit): RequestRateLimiter
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('Too many requests')
        ;

        return new RequestRateLimiter(
            new RateLimiterFactory(['id' => 'test', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage()),
            new ClientIdentifier(new Encryption('test-secret', true)),
            $translator,
        );
    }
}
