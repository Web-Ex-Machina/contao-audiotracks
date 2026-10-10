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

/**
 * The item templates of the modules are now Twig templates: "wemaudiotrack_default" and
 * "wemaudiotrack_full" became "audiotracks/item" and "audiotracks/item/full".
 */
class TemplateNameMigration extends AbstractMigration
{
    private const MAPPING = [
        'wemaudiotrack_default' => 'audiotracks/item',
        'wemaudiotrack_full' => 'audiotracks/item/full',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module']) || !isset($schemaManager->listTableColumns('tl_module')['wemaudiotracks_template'])) {
            return false;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_module WHERE wemaudiotracks_template IN (?, ?)',
            array_keys(self::MAPPING),
        ) > 0;
    }

    public function run(): MigrationResult
    {
        $count = 0;

        foreach (self::MAPPING as $old => $new) {
            $count += $this->connection->update('tl_module', ['wemaudiotracks_template' => $new], ['wemaudiotracks_template' => $old]);
        }

        return $this->createResult(true, \sprintf('Updated the item template of %d module(s).', $count));
    }
}
