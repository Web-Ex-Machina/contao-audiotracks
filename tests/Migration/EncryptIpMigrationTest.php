<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Migration;

use WEM\AudioTracksBundle\Classes\ClientIdentifier;
use WEM\AudioTracksBundle\Migration\EncryptIpMigration;
use WEM\UtilsBundle\Classes\Encryption;

class EncryptIpMigrationTest extends DatabaseTestCase
{
    private ClientIdentifier $identifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identifier = new ClientIdentifier(new Encryption('test-secret', true));
        $this->createTrackingTable('tl_wem_audiotrack_feedback');
        $this->createTrackingTable('tl_wem_audiotrack_session');
    }

    public function testNothingToDoWithoutRawIp(): void
    {
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 1, 'ip' => $this->identifier->fromIp('203.0.113.7')]);
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 2, 'ip' => 'purged-5']);

        $this->assertFalse((new EncryptIpMigration($this->connection, $this->identifier))->shouldRun());
    }

    public function testRawIpsAreEncryptedInBothTables(): void
    {
        $this->connection->insert('tl_wem_audiotrack_feedback', ['pid' => 1, 'ip' => '203.0.113.7']);
        $this->connection->insert('tl_wem_audiotrack_session', ['pid' => 1, 'ip' => '2001:db8::1']);
        $this->connection->insert('tl_wem_audiotrack_session', ['pid' => 2, 'ip' => $this->identifier->fromIp('198.51.100.1')]);

        $migration = new EncryptIpMigration($this->connection, $this->identifier);
        $this->assertTrue($migration->shouldRun());

        $result = $migration->run();
        $this->assertTrue($result->isSuccessful());

        $this->assertSame(
            [$this->identifier->fromIp('203.0.113.7')],
            $this->connection->fetchFirstColumn('SELECT ip FROM tl_wem_audiotrack_feedback'),
        );
        $this->assertSame(
            [$this->identifier->fromIp('2001:db8::1'), $this->identifier->fromIp('198.51.100.1')],
            $this->connection->fetchFirstColumn('SELECT ip FROM tl_wem_audiotrack_session ORDER BY id'),
        );
        $this->assertFalse($migration->shouldRun(), 'The migration must be idempotent');
    }

    public function testMissingTablesAreIgnored(): void
    {
        $this->connection->executeStatement('DROP TABLE tl_wem_audiotrack_feedback');
        $this->connection->executeStatement('DROP TABLE tl_wem_audiotrack_session');

        $this->assertFalse((new EncryptIpMigration($this->connection, $this->identifier))->shouldRun());
    }
}
