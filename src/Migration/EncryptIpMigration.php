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
 * Converts the visitors stored by the previous versions (feedbacks and sessions) to the
 * current identifier mode: the IP addresses stored in clear are encrypted (or hashed in
 * "hmac" mode), and the encrypted identifiers are hashed when the "hmac" mode is
 * enabled (the opposite is not possible: a hash cannot be reversed).
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
            if ([] !== $this->getConvertible($table, 1)) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $count = 0;

        foreach (self::TABLES as $table) {
            foreach ($this->getConvertible($table) as $id => $identifier) {
                $this->connection->update($table, ['ip' => $identifier], ['id' => $id]);
                ++$count;
            }
        }

        return $this->createResult(true, \sprintf('Converted %d visitor(s) of the audiotracks feedbacks and sessions to the "%s" identifier.', $count, $this->clientIdentifier->getMode()));
    }

    /**
     * @return array<int, string> [id => new identifier]
     */
    private function getConvertible(string $table, int|null $limit = null): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table]) || !isset($schemaManager->listTableColumns($table)['ip'])) {
            return [];
        }

        if (ClientIdentifier::MODE_HMAC === $this->clientIdentifier->getMode()) {
            // Everything that is not a hash yet (raw IP, encrypted identifier)
            $sql = "SELECT id, ip FROM $table WHERE ip != '' AND ip NOT REGEXP '^[0-9a-f]{64}\$' AND ip NOT LIKE 'purged-%'";
        } else {
            // An IPv4 / IPv6 only contains these characters, the encrypted values contain
            // more (base64)
            $sql = "SELECT id, ip FROM $table WHERE ip REGEXP '^[0-9a-fA-F:.]+\$'";
        }

        $converted = [];

        foreach ($this->connection->fetchAllKeyValue($sql) as $id => $ip) {
            if (null !== ($identifier = $this->clientIdentifier->toCurrent((string) $ip))) {
                $converted[(int) $id] = $identifier;

                if (null !== $limit && \count($converted) >= $limit) {
                    break;
                }
            }
        }

        return $converted;
    }
}
