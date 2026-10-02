<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;

/**
 * The migrations use MySQL syntax: they run against a throwaway database. Set
 * AUDIOTRACKS_TEST_DSN (default: the ddev database server) or the tests are skipped.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $dsn = getenv('AUDIOTRACKS_TEST_DSN') ?: 'pdo-mysql://root:root@db:3306/audiotracks_test';
        $params = (new DsnParser(['pdo-mysql' => 'pdo_mysql']))->parse($dsn);
        $database = $params['dbname'];
        unset($params['dbname']);

        try {
            $server = DriverManager::getConnection($params);
            $server->executeStatement('CREATE DATABASE IF NOT EXISTS `'.$database.'`');
            $this->connection = DriverManager::getConnection($params + ['dbname' => $database]);
            $this->connection->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('No test database available: '.$e->getMessage());
        }

        foreach (['tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_session', 'tl_wem_audiotrack'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
    }

    protected function createTrackingTable(string $table): void
    {
        $this->connection->executeStatement("CREATE TABLE $table (id INT AUTO_INCREMENT PRIMARY KEY, pid INT NOT NULL DEFAULT 0, ip VARCHAR(255) NOT NULL DEFAULT '')");
    }
}
