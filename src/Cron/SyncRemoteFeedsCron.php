<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/contao-audiotracks
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use WEM\AudioTracksBundle\Classes\RssFeed;

/**
 * Imports the remote RSS feeds of the "remote" categories, outside of the visitors requests.
 * A feed that fails does not prevent the others from being imported.
 */
#[AsCronJob('hourly')]
class SyncRemoteFeedsCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RssFeed $rssFeed,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $ids = $this->connection->fetchFirstColumn("SELECT id FROM tl_wem_audiotrack_category WHERE type = 'remote' AND rssRemoteUrl != ''");

        foreach ($ids as $id) {
            try {
                $this->rssFeed->import((int) $id);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Audiotracks: the remote feed of the category %d could not be imported: %s', $id, $e->getMessage()));
            }
        }
    }
}
