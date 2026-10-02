<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use WEM\AudioTracksBundle\Classes\TagSynchronizer;
use WEM\AudioTracksBundle\Tests\Migration\DatabaseTestCase;

class TagSynchronizerTest extends DatabaseTestCase
{
    private TagSynchronizer $synchronizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement('DROP TABLE IF EXISTS tl_wem_audiotrack_tag');
        $this->connection->executeStatement("CREATE TABLE tl_wem_audiotrack_tag (id INT AUTO_INCREMENT PRIMARY KEY, tstamp INT NOT NULL DEFAULT 0, createdAt INT NOT NULL DEFAULT 0, pid INT NOT NULL DEFAULT 0, tag VARCHAR(255) NOT NULL DEFAULT '')");
        $this->synchronizer = new TagSynchronizer($this->connection);
    }

    public function testTagsAreAddedRemovedAndKept(): void
    {
        $this->synchronizer->sync(1, ['a', "b'c", 'd']);
        $this->assertSame(['a', "b'c", 'd'], $this->tags(1));

        $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_wem_audiotrack_tag WHERE pid = 1 AND tag = ?', ["b'c"]);

        $this->synchronizer->sync(1, ["b'c", 'e']);
        $this->assertSame(["b'c", 'e'], $this->tags(1));

        // a tag that stays keeps its row
        $this->assertSame($ids, $this->connection->fetchFirstColumn('SELECT id FROM tl_wem_audiotrack_tag WHERE pid = 1 AND tag = ?', ["b'c"]));
    }

    public function testAnEmptyListRemovesEverything(): void
    {
        $this->synchronizer->sync(1, ['a', 'b']);
        $this->synchronizer->sync(2, ['a']);
        $this->synchronizer->sync(1, []);

        $this->assertSame([], $this->tags(1));
        $this->assertSame(['a'], $this->tags(2), 'the tags of the other tracks are not touched');
    }

    public function testDuplicatesAndRepeatedSyncDoNotCreateDuplicates(): void
    {
        $this->synchronizer->sync(1, ['a', 'a', 'b']);
        $this->synchronizer->sync(1, ['a', 'b']);

        $this->assertSame(['a', 'b'], $this->tags(1));
    }

    private function tags(int $pid): array
    {
        return $this->connection->fetchFirstColumn('SELECT tag FROM tl_wem_audiotrack_tag WHERE pid = ? ORDER BY tag', [$pid]);
    }
}
