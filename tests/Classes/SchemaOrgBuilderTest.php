<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\SchemaOrgBuilder;

/**
 * The private helpers are pure functions, they are tested without the Contao framework.
 */
class SchemaOrgBuilderTest extends TestCase
{
    #[DataProvider('durations')]
    public function testIsoDuration(int $seconds, string|null $expected): void
    {
        $this->assertSame($expected, $this->call('isoDuration', $seconds));
    }

    public static function durations(): iterable
    {
        yield 'zero' => [0, null];
        yield 'negative' => [-5, null];
        yield 'seconds only' => [45, 'PT45S'];
        yield 'minutes and seconds' => [3476, 'PT57M56S'];
        yield 'exact minutes' => [120, 'PT2M'];
        yield 'exact hour' => [3600, 'PT1H'];
        yield 'hours minutes seconds' => [3723, 'PT1H2M3S'];
    }

    public function testCleanRemovesEmptyValuesAndKeepsBooleans(): void
    {
        $cleaned = $this->call('clean', [
            'a' => null,
            'b' => '',
            'c' => [],
            'd' => false,
            'e' => 0,
            'f' => 'x',
            'g' => true,
        ]);

        $this->assertSame(['d' => false, 'e' => 0, 'f' => 'x', 'g' => true], $cleaned);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $builder = (new \ReflectionClass(SchemaOrgBuilder::class))->newInstanceWithoutConstructor();
        $reflection = new \ReflectionMethod($builder, $method);

        return $reflection->invoke($builder, ...$args);
    }
}
