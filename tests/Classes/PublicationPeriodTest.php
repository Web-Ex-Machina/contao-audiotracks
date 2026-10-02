<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\PublicationPeriod;

class PublicationPeriodTest extends TestCase
{
    #[DataProvider('periods')]
    public function testIsValid(int $start, int $stop, bool $expected): void
    {
        $this->assertSame($expected, PublicationPeriod::isValid($start, $stop));
    }

    public static function periods(): iterable
    {
        yield 'no dates' => [0, 0, true];
        yield 'only a start' => [1700000000, 0, true];
        yield 'only a stop' => [0, 1700000000, true];
        yield 'stop after start' => [1700000000, 1700003600, true];
        yield 'stop one second after start' => [1700000000, 1700000001, true];
        yield 'stop at the start' => [1700000000, 1700000000, false];
        yield 'stop before start' => [1700003600, 1700000000, false];
    }
}
