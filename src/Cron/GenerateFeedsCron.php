<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Psr\Log\LoggerInterface;
use WEM\AudioTracksBundle\Classes\FeedWithoutTracksException;
use WEM\AudioTracksBundle\Classes\RssFeed;

/**
 * Generates the RSS feeds of the local categories again. They are generated when
 * a category or a track is saved in the back end, but not when a track is
 * published or unpublished by its start / stop date, or deleted: without this job
 * the feed would be out of date until the next save. A feed that did not change
 * is not rewritten.
 */
#[AsCronJob('hourly')]
class GenerateFeedsCron
{
    public function __construct(
        private readonly RssFeed $rssFeed,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->rssFeed->generateAll() as $id => $result) {
            // A category without any published track has no feed, that is not an error
            if ($result instanceof \Throwable && !$result instanceof FeedWithoutTracksException) {
                $this->logger->error(\sprintf('Audiotracks: the RSS feed of the category %d could not be generated: %s', $id, $result->getMessage()));
            }
        }
    }
}
