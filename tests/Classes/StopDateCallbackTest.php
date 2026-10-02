<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use Contao\DataContainer;
use Contao\DC_Table;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\TagSynchronizer;
use WEM\AudioTracksBundle\EventListener\DataContainer\AudiotrackContainer;

/**
 * The save callback of the "stop" field of a track, with a data container that
 * has the record the start date was just saved in (that is what Contao does: the
 * start comes before the stop in the palette).
 */
class StopDateCallbackTest extends TestCase
{
    public function testAStopAfterTheStartIsAccepted(): void
    {
        $this->assertSame(1700003600, $this->container()->validateStopDate(1700003600, $this->dc(1700000000)));
    }

    public function testNoStopOrNoStartIsAccepted(): void
    {
        $this->assertSame(0, $this->container()->validateStopDate(0, $this->dc(1700000000)));
        $this->assertSame(1700003600, $this->container()->validateStopDate(1700003600, $this->dc('')));
        $this->assertSame('', $this->container()->validateStopDate('', $this->dc(1700000000)));
    }

    public function testAStopBeforeTheStartIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The stop date must be after the start date.');

        $this->container()->validateStopDate(1700000000, $this->dc(1700003600));
    }

    public function testAStopAtTheStartIsRefused(): void
    {
        $this->expectException(\Exception::class);

        $this->container()->validateStopDate(1700000000, $this->dc(1700000000));
    }

    private function container(): AudiotrackContainer
    {
        return new AudiotrackContainer($this->createStub(TagSynchronizer::class));
    }

    private function dc(int|string $start): DataContainer
    {
        $dc = (new \ReflectionClass(DC_Table::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(DataContainer::class, 'objActiveRecord');
        $property->setValue($dc, (object) ['start' => $start]);

        return $dc;
    }
}
