<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\ClientIdentifier;
use WEM\UtilsBundle\Classes\Encryption;

class ClientIdentifierTest extends TestCase
{
    public function testTheIdentifierIsDeterministic(): void
    {
        $identifier = $this->identifier();

        $this->assertSame($identifier->fromIp('203.0.113.7'), $identifier->fromIp('203.0.113.7'));
        $this->assertNotSame($identifier->fromIp('203.0.113.7'), $identifier->fromIp('203.0.113.8'));
    }

    public function testTheIpIsNotStoredInClear(): void
    {
        $value = $this->identifier()->fromIp('203.0.113.7');

        $this->assertStringNotContainsString('203.0.113.7', $value);
        $this->assertFalse($this->identifier()->isRawIp($value));
    }

    public function testTheIdentifierDependsOnTheSecret(): void
    {
        $this->assertNotSame($this->identifier('a')->fromIp('203.0.113.7'), $this->identifier('b')->fromIp('203.0.113.7'));
    }

    public function testAnEmptyIpGetsAPlaceholder(): void
    {
        $identifier = $this->identifier();

        $this->assertNotSame('', $identifier->fromIp(''));
        $this->assertSame($identifier->fromIp('unknown'), $identifier->fromIp(''));
    }

    #[DataProvider('rawIps')]
    public function testIsRawIp(string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->identifier()->isRawIp($value));
    }

    public static function rawIps(): iterable
    {
        yield 'ipv4' => ['192.168.1.10', true];
        yield 'ipv6' => ['2001:db8::1', true];
        yield 'purged placeholder' => ['purged-12', false];
        yield 'empty' => ['', false];
        yield 'garbage' => ['abc', false];
    }

    private function identifier(string $secret = 'test-secret'): ClientIdentifier
    {
        return new ClientIdentifier(new Encryption($secret, true));
    }
}
