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

namespace WEM\AudioTracksBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * A visitor can only have one feedback and one session per track: (pid, ip) is a unique key.
 *  - the duplicates are removed (the most recent one is kept)
 *  - the feedbacks detached from their visitor (empty ip) get a unique placeholder
 */
class UniqueTrackingDataMigration extends AbstractMigration
{
    public const PURGED_PREFIX = 'purged-';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        foreach (['tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_session'] as $table) {
            if (!$this->tableReady($table)) {
                continue;
            }

            if ($this->countDuplicates($table) > 0) {
                return true;
            }
        }

        return $this->tableReady('tl_wem_audiotrack_feedback')
            && (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_wem_audiotrack_feedback WHERE ip = ''") > 0;
    }

    public function run(): MigrationResult
    {
        $deleted = 0;

        foreach (['tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_session'] as $table) {
            if (!$this->tableReady($table)) {
                continue;
            }

            // Keep the most recent row of each (pid, ip)
            $deleted += $this->connection->executeStatement(
                "DELETE a FROM $table a INNER JOIN $table b ON a.pid = b.pid AND a.ip = b.ip AND a.id < b.id WHERE a.ip != ''"
            );
        }

        $detached = 0;
        if ($this->tableReady('tl_wem_audiotrack_feedback')) {
            $detached = $this->connection->executeStatement(
                "UPDATE tl_wem_audiotrack_feedback SET ip = CONCAT(?, id) WHERE ip = ''",
                [self::PURGED_PREFIX]
            );
        }

        return $this->createResult(true, sprintf('Removed %d duplicated feedback/session row(s), %d feedback(s) detached from their visitor.', $deleted, $detached));
    }

    private function tableReady(string $table): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist([$table]) && isset($schemaManager->listTableColumns($table)['ip']);
    }

    private function countDuplicates(string $table): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM (SELECT 1 FROM $table WHERE ip != '' GROUP BY pid, ip HAVING COUNT(*) > 1) d");
    }
}
