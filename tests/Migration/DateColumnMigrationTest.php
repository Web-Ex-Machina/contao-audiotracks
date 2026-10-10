<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Migration;

use WEM\AudioTracksBundle\Migration\DateColumnMigration;

class DateColumnMigrationTest extends DatabaseTestCase
{
    public function testIntegerColumnDoesNotRun(): void
    {
        $this->connection->executeStatement('CREATE TABLE tl_wem_audiotrack (id INT AUTO_INCREMENT PRIMARY KEY, date INT UNSIGNED NOT NULL DEFAULT 0)');

        $this->assertFalse((new DateColumnMigration($this->connection))->shouldRun());
    }

    public function testInvalidDatesOfAStringColumnAreReset(): void
    {
        $this->connection->executeStatement("CREATE TABLE tl_wem_audiotrack (id INT AUTO_INCREMENT PRIMARY KEY, date VARCHAR(10) NOT NULL DEFAULT '')");

        foreach (['', '1700000000', 'bad'] as $date) {
            $this->connection->insert('tl_wem_audiotrack', ['date' => $date]);
        }

        $migration = new DateColumnMigration($this->connection);
        $this->assertTrue($migration->shouldRun());
        $migration->run();

        $this->assertSame(['0', '1700000000', '0'], $this->connection->fetchFirstColumn('SELECT date FROM tl_wem_audiotrack ORDER BY id'));
        $this->assertFalse($migration->shouldRun());
    }

    public function testMissingTableDoesNotRun(): void
    {
        $this->assertFalse((new DateColumnMigration($this->connection))->shouldRun());
    }
}
