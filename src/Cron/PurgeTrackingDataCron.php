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
use WEM\AudioTracksBundle\Migration\UniqueTrackingDataMigration;

/**
 * Limits the retention of the visitors data (configuration: audio_tracks.retention_months, 0 = keep forever, which is the default).
 *  - the listening sessions not updated since then are deleted
 *  - the feedbacks (likes) are kept, so the counters do not change, but they are detached from the visitor (unique placeholder)
 */
#[AsCronJob('daily')]
class PurgeTrackingDataCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly int $retentionMonths,
    ) {
    }

    public function __invoke(): void
    {
        if ($this->retentionMonths < 1) {
            return;
        }

        $limit = (new \DateTimeImmutable(sprintf('-%d months', $this->retentionMonths)))->getTimestamp();

        $this->connection->executeStatement('DELETE FROM tl_wem_audiotrack_session WHERE tstamp < ?', [$limit]);
        $this->connection->executeStatement("UPDATE tl_wem_audiotrack_feedback SET ip = CONCAT(?, id) WHERE tstamp < ? AND ip NOT LIKE ?", [UniqueTrackingDataMigration::PURGED_PREFIX, $limit, UniqueTrackingDataMigration::PURGED_PREFIX.'%']);
    }
}
