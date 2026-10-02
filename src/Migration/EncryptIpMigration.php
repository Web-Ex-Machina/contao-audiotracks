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
use WEM\AudioTracksBundle\Classes\ClientIdentifier;

/**
 * Encrypts the IP addresses stored in clear by the previous versions (feedbacks
 * and sessions).
 */
class EncryptIpMigration extends AbstractMigration
{
    private const TABLES = ['tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_session'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClientIdentifier $clientIdentifier,
    ) {
    }

    public function shouldRun(): bool
    {
        foreach (self::TABLES as $table) {
            if ([] !== $this->getRawIps($table, 1)) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $count = 0;

        foreach (self::TABLES as $table) {
            foreach ($this->getRawIps($table) as $id => $ip) {
                $this->connection->update($table, ['ip' => $this->clientIdentifier->fromIp($ip)], ['id' => $id]);
                ++$count;
            }
        }

        return $this->createResult(true, \sprintf('Encrypted %d IP address(es) of the audiotracks feedbacks and sessions.', $count));
    }

    /**
     * @return array<int, string> [id => ip]
     */
    private function getRawIps(string $table, int|null $limit = null): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table]) || !isset($schemaManager->listTableColumns($table)['ip'])) {
            return [];
        }

        // An IPv4 / IPv6 only contains these characters, the encrypted values contain
        // more (base64)
        $rows = $this->connection->fetchAllKeyValue("SELECT id, ip FROM $table WHERE ip REGEXP '^[0-9a-fA-F:.]+\$'");
        $raw = array_filter($rows, fn (string $ip): bool => $this->clientIdentifier->isRawIp($ip));

        return null === $limit ? $raw : \array_slice($raw, 0, $limit, true);
    }
}
