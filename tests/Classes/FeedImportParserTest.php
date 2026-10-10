<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Classes\FeedImportParser;

class FeedImportParserTest extends TestCase
{
    public function testTagsMergeCategoriesAndKeywords(): void
    {
        $tags = FeedImportParser::tags(['Gaming', ['term' => 'Tech', 'label' => 'Technology'], '  Retro   games '], 'rpg, Gaming; indie ,,');

        $this->assertSame(['Gaming', 'Technology', 'Retro games', 'rpg', 'indie'], $tags);
    }

    public function testTagsAreDeduplicatedIgnoringTheCase(): void
    {
        $this->assertSame(['Jeux'], FeedImportParser::tags(['Jeux', 'jeux', 'JEUX'], 'jeux'));
    }

    public function testTagsDropHtmlEmptyAndTooLongValues(): void
    {
        $tags = FeedImportParser::tags(['<b>Bold</b>', '', '   ', str_repeat('x', FeedImportParser::MAX_TAG_LENGTH + 1), 'Fish &amp; chips']);

        $this->assertSame(['Bold', 'Fish & chips'], $tags);
    }

    public function testTagsAreLimited(): void
    {
        $tags = FeedImportParser::tags(array_map(static fn (int $i): string => 'tag'.$i, range(1, 50)));

        $this->assertCount(FeedImportParser::MAX_TAGS, $tags);
        $this->assertSame([], FeedImportParser::tags(null, null));
    }

    public function testAuthorsFromTheFeedReader(): void
    {
        $authors = FeedImportParser::authors([
            ['name' => 'Alice', 'email' => 'alice@example.org'],
            ['name' => 'Alice', 'email' => 'ALICE@example.org'],
            ['email' => 'bob@example.org'],
            ['name' => 'Carol', 'uri' => 'https://example.org/carol'],
            'garbage',
            ['name' => '', 'email' => ''],
        ]);

        $this->assertSame(
            [
                ['name' => 'Alice', 'email' => 'alice@example.org', 'uri' => ''],
                ['name' => 'bob@example.org', 'email' => 'bob@example.org', 'uri' => ''],
                ['name' => 'Carol', 'email' => '', 'uri' => 'https://example.org/carol'],
            ],
            $authors,
        );
    }

    public function testTheCastAuthorIsOnlyUsedWithoutOtherAuthor(): void
    {
        $this->assertSame([['name' => 'Role You Fool', 'email' => '', 'uri' => '']], FeedImportParser::authors(null, 'Role You Fool'));
        $this->assertSame([['name' => 'Alice', 'email' => '', 'uri' => '']], FeedImportParser::authors([['name' => 'Alice']], 'Role You Fool'));
        $this->assertSame([], FeedImportParser::authors(null, ' '));
    }

    #[DataProvider('owners')]
    public function testParseOwner(string $owner, string $name, string $email): void
    {
        $this->assertSame(['name' => $name, 'email' => $email, 'uri' => ''], FeedImportParser::parseOwner($owner));
    }

    public static function owners(): iterable
    {
        yield 'email (name)' => ['contact@example.org (Jane Doe)', 'Jane Doe', 'contact@example.org'];
        yield 'name <email>' => ['Jane Doe <contact@example.org>', 'Jane Doe', 'contact@example.org'];
        yield 'quoted name <email>' => ['"Jane Doe" <contact@example.org>', 'Jane Doe', 'contact@example.org'];
        yield 'name (something)' => ['Jane Doe (host)', 'Jane Doe', ''];
        yield 'email only' => ['contact@example.org', 'contact@example.org', 'contact@example.org'];
        yield 'name only' => ['Jane Doe', 'Jane Doe', ''];
    }

    #[DataProvider('urls')]
    public function testUrl(mixed $url, string $expected): void
    {
        $this->assertSame($expected, FeedImportParser::url($url));
    }

    public static function urls(): iterable
    {
        yield 'https' => ['https://example.org/cover.jpg', 'https://example.org/cover.jpg'];
        yield 'trimmed' => ['  http://example.org/c.png ', 'http://example.org/c.png'];
        yield 'javascript' => ['javascript:alert(1)', ''];
        yield 'relative' => ['/cover.jpg', ''];
        yield 'too long' => ['https://example.org/'.str_repeat('a', 300), ''];
        yield 'null' => [null, ''];
    }
}
