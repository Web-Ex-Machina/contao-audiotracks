<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\BaseUrl;

class BaseUrlTest extends TestCase
{
    public function testTheConfiguredAddressComesFirst(): void
    {
        $this->assertSame('https://www.example.org/', BaseUrl::resolve('https://www.example.org', 'https://other.test/', $this->neverCalled()));
    }

    public function testThenTheAddressOfTheRequest(): void
    {
        $this->assertSame('https://other.test/sub/', BaseUrl::resolve('', 'https://other.test/sub/', $this->neverCalled()));
    }

    #[DataProvider('invalidRequests')]
    public function testThenTheRootPages(string $request): void
    {
        $this->assertSame('https://www.example.org/', BaseUrl::resolve('', $request, static fn (): array => [['dns' => 'www.example.org', 'useSSL' => '1']]));
        $this->assertSame('http://www.example.org/', BaseUrl::resolve('', $request, static fn (): array => [['dns' => 'www.example.org', 'useSSL' => '']]));
        // Several root pages (languages) with the same domain
        $this->assertSame('https://www.example.org/', BaseUrl::resolve('', $request, static fn (): array => [['dns' => 'www.example.org', 'useSSL' => '1'], ['dns' => 'WWW.example.org', 'useSSL' => '1']]));
    }

    public static function invalidRequests(): iterable
    {
        yield 'empty (command line)' => [''];
        yield 'only a slash' => ['/'];
        yield 'no scheme' => ['localhost/'];
        yield 'other scheme' => ['ftp://example.org/'];
    }

    public function testAnInvalidConfiguredAddressIsIgnored(): void
    {
        $this->assertSame('https://other.test/', BaseUrl::resolve('www.example.org', 'https://other.test/', $this->neverCalled()));
    }

    #[DataProvider('ambiguousRoots')]
    public function testTheRootPagesAreNotGuessed(array $roots): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/audio_tracks\.base_url/');

        BaseUrl::resolve('', '', static fn (): array => $roots);
    }

    public static function ambiguousRoots(): iterable
    {
        yield 'no root page' => [[]];
        yield 'a root page without domain' => [[['dns' => '  ', 'useSSL' => '1']]];
        yield 'one without domain, one with' => [[['dns' => '', 'useSSL' => '1'], ['dns' => 'www.example.org', 'useSSL' => '1']]];
        yield 'two domains' => [[['dns' => 'a.example.org', 'useSSL' => '1'], ['dns' => 'b.example.org', 'useSSL' => '1']]];
        yield 'same domain, http and https' => [[['dns' => 'www.example.org', 'useSSL' => '1'], ['dns' => 'www.example.org', 'useSSL' => '']]];
    }

    private function neverCalled(): \Closure
    {
        return fn (): array => $this->fail('The root pages are not needed');
    }
}
