<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Migration;

use WEM\AudioTracksBundle\Migration\UniqueTrackingDataMigration;

class UniqueTrackingDataMigrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTrackingTable('tl_wem_audiotrack_feedback');
        $this->createTrackingTable('tl_wem_audiotrack_session');
    }

    public function testCleanDataDoesNotRun(): void
    {
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 1, 'ip' => 'a']);
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 2, 'ip' => 'a']);

        $this->assertFalse((new UniqueTrackingDataMigration($this->connection))->shouldRun());
    }

    public function testDuplicatesKeepTheMostRecentRow(): void
    {
        foreach ([[1, 'a'], [1, 'a'], [1, 'a'], [1, 'b'], [2, 'a']] as [$pid, $ip]) {
            $this->connection->insert('tl_wem_audiotrack_session', ['pid' => $pid, 'ip' => $ip]);
        }

        $migration = new UniqueTrackingDataMigration($this->connection);
        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());

        // ids 1 and 2 are the older duplicates of (1, a)
        $this->assertSame([3, 4, 5], array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM tl_wem_audiotrack_session ORDER BY id')));
        $this->assertFalse($migration->shouldRun());
    }

    public function testDetachedFeedbacksGetAUniquePlaceholder(): void
    {
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 1, 'ip' => '']);
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 1, 'ip' => '']);

        $migration = new UniqueTrackingDataMigration($this->connection);
        $this->assertTrue($migration->shouldRun());
        $migration->run();

        $this->assertSame(
            [UniqueTrackingDataMigration::PURGED_PREFIX.'1', UniqueTrackingDataMigration::PURGED_PREFIX.'2'],
            $this->connection->fetchFirstColumn('SELECT ip FROM tl_wem_audiotrack_feedback ORDER BY id'),
        );
        $this->assertFalse($migration->shouldRun());
    }
}
