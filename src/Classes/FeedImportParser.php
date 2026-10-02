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

/**
 * Turns what a remote feed contains (authors, owner, categories, keywords) into
 * the data of the bundle. Only pure functions: nothing here touches the database.
 */
final class FeedImportParser
{
    public const MAX_TAGS = 20;

    public const MAX_TAG_LENGTH = 64;

    public const MAX_AUTHORS = 10;

    /**
     * Tags from the categories of an episode and its keywords ("a, b; c"). Trimmed,
     * without duplicates (the case is ignored), too long ones are dropped.
     *
     * @param iterable<string|null>|null $categories
     *
     * @return list<string>
     */
    public static function tags(iterable|null $categories, string|null $keywords = null): array
    {
        $candidates = [];

        foreach ($categories ?? [] as $category) {
            $candidates[] = (string) (\is_array($category) ? ($category['label'] ?? $category['term'] ?? '') : $category);
        }

        foreach (preg_split('/[,;]/', (string) $keywords) ?: [] as $keyword) {
            $candidates[] = $keyword;
        }

        $tags = [];

        foreach ($candidates as $candidate) {
            $tag = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($candidate), ENT_QUOTES | ENT_HTML5)));
            $key = mb_strtolower($tag);

            if ('' === $tag || mb_strlen($tag) > self::MAX_TAG_LENGTH || isset($tags[$key])) {
                continue;
            }

            $tags[$key] = $tag;

            if (\count($tags) >= self::MAX_TAGS) {
                break;
            }
        }

        return array_values($tags);
    }

    /**
     * Authors of an episode, in the format of the "authors" field: [name, email, uri].
     *
     * @param iterable<array<string, string>>|null $authors    Authors read by the feed reader (author, dc:creator)
     * @param string|null                          $castAuthor The itunes:author, used when there is no other author
     *
     * @return list<array{name: string, email: string, uri: string}>
     */
    public static function authors(iterable|null $authors, string|null $castAuthor = null): array
    {
        $rows = [];

        foreach ($authors ?? [] as $author) {
            if (!\is_array($author)) {
                continue;
            }

            $email = trim((string) ($author['email'] ?? ''));
            $name = trim((string) ($author['name'] ?? ''));

            // "email" alone is an author too, the address is the only name we have
            $rows[] = ['name' => '' !== $name ? $name : $email, 'email' => $email, 'uri' => trim((string) ($author['uri'] ?? ''))];
        }

        if ([] === array_filter($rows, static fn (array $row): bool => '' !== $row['name']) && '' !== trim((string) $castAuthor)) {
            $rows = [self::parseOwner((string) $castAuthor)];
        }

        $unique = [];

        foreach ($rows as $row) {
            $key = mb_strtolower($row['name'].'|'.$row['email']);

            if ('' !== $row['name'] && !isset($unique[$key])) {
                $unique[$key] = $row;
            }
        }

        return \array_slice(array_values($unique), 0, self::MAX_AUTHORS);
    }

    /**
     * An owner / author written "email (name)", "name <email>", "email" or "name".
     *
     * @return array{name: string, email: string, uri: string}
     */
    public static function parseOwner(string $owner): array
    {
        $owner = trim($owner);

        if (preg_match('/^(.*?)\s*\((.*)\)$/', $owner, $m)) {
            [$name, $email] = self::looksLikeEmail($m[1]) ? [$m[2], $m[1]] : [$m[1], ''];
        } elseif (preg_match('/^(.*?)\s*<([^>]*)>$/', $owner, $m)) {
            [$name, $email] = [$m[1], $m[2]];
        } elseif (self::looksLikeEmail($owner)) {
            [$name, $email] = [$owner, $owner];
        } else {
            [$name, $email] = [$owner, ''];
        }

        $name = trim($name, " \t\"'");

        return ['name' => '' !== $name ? $name : $email, 'email' => self::looksLikeEmail($email) ? $email : '', 'uri' => ''];
    }

    /**
     * An http(s) url that fits in the database, or an empty string.
     */
    public static function url(mixed $url): string
    {
        $url = trim((string) $url);

        return \strlen($url) <= 255 && 1 === preg_match('#^https?://[^\s]+$#i', $url) ? $url : '';
    }

    private static function looksLikeEmail(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
