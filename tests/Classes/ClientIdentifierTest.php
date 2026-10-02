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

    public function testHmacIdentifierIsADeterministicHash(): void
    {
        $identifier = $this->hmac();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $identifier->fromIp('203.0.113.7'));
        $this->assertSame($identifier->fromIp('203.0.113.7'), $identifier->fromIp('203.0.113.7'));
        $this->assertNotSame($identifier->fromIp('203.0.113.7'), $identifier->fromIp('203.0.113.8'));
        $this->assertNotSame($identifier->fromIp('203.0.113.7'), $this->hmac('other')->fromIp('203.0.113.7'));
        $this->assertTrue($identifier->isHashOrPurged($identifier->fromIp('203.0.113.7')));
    }

    public function testToCurrentConvertsOnlyWhatMustBe(): void
    {
        $encryption = $this->identifier();
        $hmac = $this->hmac();
        $encrypted = $encryption->fromIp('203.0.113.7');

        // raw IP: converted in both modes
        $this->assertSame($encrypted, $encryption->toCurrent('203.0.113.7'));
        $this->assertSame($hmac->fromIp('203.0.113.7'), $hmac->toCurrent('203.0.113.7'));
        // encrypted identifier: already current in encryption mode, hashed in hmac mode
        $this->assertNull($encryption->toCurrent($encrypted));
        $this->assertSame($hmac->fromIp('203.0.113.7'), $hmac->toCurrent($encrypted));
        $this->assertSame($hmac->fromIp(''), $hmac->toCurrent($encryption->fromIp('')));
        // hash, placeholder, empty, garbage: untouched
        $this->assertNull($hmac->toCurrent($hmac->fromIp('203.0.113.7')));
        $this->assertNull($hmac->toCurrent('purged-3'));
        $this->assertNull($hmac->toCurrent(''));
        $this->assertNull($hmac->toCurrent('not-an-identifier'));
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

    private function hmac(string $key = 'k'): ClientIdentifier
    {
        return new ClientIdentifier(new Encryption('test-secret', true), ClientIdentifier::MODE_HMAC, $key);
    }

    private function identifier(string $secret = 'test-secret'): ClientIdentifier
    {
        return new ClientIdentifier(new Encryption($secret, true));
    }
}
