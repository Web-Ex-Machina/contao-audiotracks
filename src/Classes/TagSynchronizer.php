<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Keeps the pivot table of the tags of the tracks (tl_wem_audiotrack_tag) in line
 * with the "tags" field of a track. The back end does it when a track is saved,
 * the remote import has to do it as well.
 */
class TagSynchronizer
{
    private const TABLE = 'tl_wem_audiotrack_tag';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $tags
     */
    public function sync(int $trackId, array $tags): void
    {
        $tags = array_values(array_unique(array_map('strval', $tags)));
        $existing = $this->connection->fetchFirstColumn('SELECT tag FROM '.self::TABLE.' WHERE pid = ?', [$trackId]);

        foreach (array_diff($tags, $existing) as $tag) {
            $this->connection->insert(self::TABLE, ['tstamp' => time(), 'createdAt' => time(), 'pid' => $trackId, 'tag' => $tag]);
        }

        // Remove the tags that are not in the list anymore (all of them if there is none)
        if ([] === $tags) {
            $this->connection->executeStatement('DELETE FROM '.self::TABLE.' WHERE pid = ?', [$trackId]);

            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM '.self::TABLE.' WHERE pid = ? AND tag NOT IN (?)',
            [$trackId, $tags],
            [ParameterType::INTEGER, ArrayParameterType::STRING],
        );
    }
}
