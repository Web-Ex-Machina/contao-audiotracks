<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\RssFeed;

/**
 * The code that calls the feed service (cron, back end, command) is not run by
 * the tests: its public methods must stay. This test fails if one of them is
 * removed by mistake.
 */
class RssFeedApiTest extends TestCase
{
    public function testThePublicMethodsUsedByTheCallersExist(): void
    {
        $import = new \ReflectionMethod(RssFeed::class, 'import');
        $this->assertTrue($import->isPublic());
        $this->assertSame(['id', 'force'], array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $import->getParameters()));

        foreach (['generate', 'generateAll', 'generateFeed'] as $method) {
            $this->assertTrue((new \ReflectionMethod(RssFeed::class, $method))->isPublic(), $method);
        }
    }
}
