<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\StringType;

/**
 * The "date" of a track is now an integer column (like the news), an empty string
 * cannot be converted by the schema update.
 */
class DateColumnMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        return $this->isStringColumn() && $this->countInvalid() > 0;
    }

    public function run(): MigrationResult
    {
        $count = $this->connection->executeStatement("UPDATE tl_wem_audiotrack SET date = '0' WHERE date = '' OR date NOT REGEXP '^[0-9]+\$'");

        return $this->createResult(true, \sprintf('Reset the empty date of %d audiotrack(s).', $count));
    }

    private function isStringColumn(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_wem_audiotrack'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_wem_audiotrack');

        return isset($columns['date']) && $columns['date']->getType() instanceof StringType;
    }

    private function countInvalid(): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_wem_audiotrack WHERE date = '' OR date NOT REGEXP '^[0-9]+\$'");
    }
}
