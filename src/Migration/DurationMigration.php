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
use Symfony\Component\Filesystem\Path;
use WEM\AudioTracksBundle\Util\MP3File;

/**
 * The duration of a track is computed when it is saved in the back end. It used to be computed (and saved)
 * during the first page view: the tracks that never have been displayed yet do not have one.
 */
class DurationMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
    }

    public function shouldRun(): bool
    {
        return [] !== $this->getMissing();
    }

    public function run(): MigrationResult
    {
        $done = 0;

        foreach ($this->getMissing() as $id => $path) {
            $duration = (int) round((new MP3File($path))->getDuration());

            if ($duration > 0) {
                $this->connection->update('tl_wem_audiotrack', ['duration' => $duration], ['id' => $id]);
                ++$done;
            }
        }

        return $this->createResult(true, sprintf('Computed the duration of %d audiotrack(s).', $done));
    }

    /**
     * @return array<int, string> [id => absolute path of the mp3] of the tracks without duration
     */
    private function getMissing(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_wem_audiotrack', 'tl_files'])) {
            return [];
        }

        $rows = $this->connection->fetchAllKeyValue(
            "SELECT t.id, f.path FROM tl_wem_audiotrack t INNER JOIN tl_files f ON f.uuid = t.audio WHERE t.duration = 0 AND f.path LIKE '%.mp3'"
        );

        $missing = [];

        foreach ($rows as $id => $path) {
            $absolute = Path::join($this->projectDir, $path);

            if (is_file($absolute)) {
                $missing[(int) $id] = $absolute;
            }
        }

        return $missing;
    }
}
