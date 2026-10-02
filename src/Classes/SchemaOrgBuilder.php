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

use Contao\CoreBundle\String\HtmlDecoder;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;

/**
 * Builds the schema.org JSON-LD of an audiotrack.
 *
 * An audiotrack is a podcast episode: https://schema.org/PodcastEpisode
 *  - partOfSeason -> PodcastSeason (the "season" field)
 *  - partOfSeries -> PodcastSeries (the category)
 *  - associatedMedia -> AudioObject (the audio file)
 *
 * The result is meant to be given to the Twig function add_schema_org().
 */
class SchemaOrgBuilder
{
    private const MIME_TYPES = [
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
    ];

    /**
     * The series is the same for all the episodes of a category: built once per category.
     *
     * @var array<int, array>
     */
    private array $series = [];

    public function __construct(
        private readonly HtmlDecoder $htmlDecoder,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array $data The item template data (see ModuleController::parseItem())
     */
    public function buildEpisode(AudioTrack $track, Category $category, array $data): array
    {
        $series = $this->buildSeries($category);

        $episode = [
            '@type' => 'PodcastEpisode',
            'name' => $track->title,
            'description' => $this->toPlainText((string) $track->description),
            'url' => !empty($data['jumpTo']) ? $this->absoluteUrl($data['jumpTo']) : null,
            'datePublished' => $track->date ? date('c', (int) $track->date) : null,
            'episodeNumber' => is_numeric($track->episode) ? (int) $track->episode : null,
            'image' => $this->absoluteUrl($data['picture'] ?? null),
            'inLanguage' => $category->language ?: null,
            'isFamilyFriendly' => $this->isFamilyFriendly($track, $category),
            'keywords' => $data['tagList'] ? implode(', ', $data['tagList']) : null,
            'author' => $this->buildAuthors($track, $category),
            'partOfSeries' => $series,
        ];

        if ($track->season) {
            $episode['partOfSeason'] = array_filter(
                [
                    '@type' => 'PodcastSeason',
                    'name' => $this->buildSeasonName($category, (string) $track->season),
                    'seasonNumber' => is_numeric($track->season) ? (int) $track->season : $track->season,
                    'partOfSeries' => $series,
                ],
                static fn ($v): bool => null !== $v && '' !== $v,
            );
        }

        if (!empty($data['audio'])) {
            $duration = $this->isoDuration((int) $track->duration);
            $audio = [
                '@type' => 'AudioObject',
                'contentUrl' => $this->absoluteUrl($data['audio']),
                'encodingFormat' => $this->getMimeType((string) $data['audio']),
                'duration' => $duration,
            ];

            $episode['associatedMedia'] = $audio;
            $episode['duration'] = $duration;
        }

        if (!empty($data['nbLikes'])) {
            $episode['interactionStatistic'] = [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/LikeAction',
                'userInteractionCount' => (int) $data['nbLikes'],
            ];
        }

        return $this->clean($episode);
    }

    private function buildSeries(Category $category): array
    {
        return $this->series[(int) $category->id] ??= $this->clean([
            '@type' => 'PodcastSeries',
            'name' => $category->title,
            'description' => $this->toPlainText((string) $category->description),
            'url' => $category->rssLink ?: null,
            'image' => $this->getFileUrl($category->picture) ?? ($category->pictureRemoteUrl ?: null),
            'inLanguage' => $category->language ?: null,
            'webFeed' => $category->rss && $category->rssFilename ? $category->getRssFeedUrl() : null,
            'isFamilyFriendly' => !$category->explicit,
        ]);
    }

    private function buildSeasonName(Category $category, string $season): string
    {
        return \sprintf('%s - %s %s', $category->title, $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['season'] ?? 'Season', $season);
    }

    /**
     * Authors are defined on the track, or inherited from its category. Emails are
     * not exposed on purpose.
     */
    private function buildAuthors(AudioTrack $track, Category $category): array|null
    {
        $authors = StringUtil::deserialize($track->authors, true) ?: StringUtil::deserialize($category->authors, true);
        $persons = [];

        foreach ($authors as $author) {
            if (empty($author['name'])) {
                continue;
            }

            $persons[] = array_filter([
                '@type' => 'Person',
                'name' => $author['name'],
                'url' => $author['uri'] ?? null,
            ]);
        }

        return $persons ?: null;
    }

    private function isFamilyFriendly(AudioTrack $track, Category $category): bool
    {
        return !$track->explicit && !$category->explicit;
    }

    private function getMimeType(string $path): string|null
    {
        return self::MIME_TYPES[strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))] ?? null;
    }

    private function getFileUrl(mixed $uuid): string|null
    {
        if (!$uuid || !($file = FilesModel::findByUuid($uuid))) {
            return null;
        }

        return $this->absoluteUrl($file->path);
    }

    private function absoluteUrl(string|null $url): string|null
    {
        if (!$url) {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return BaseUrl::fromRequest($this->requestStack->getCurrentRequest()).ltrim($url, '/');
    }

    private function toPlainText(string $html): string|null
    {
        $text = trim($this->htmlDecoder->htmlToPlainText($html));

        return '' === $text ? null : $text;
    }

    /**
     * Seconds to ISO 8601 duration, ie: 3476 => PT57M56S.
     */
    private function isoDuration(int $seconds): string|null
    {
        if ($seconds < 1) {
            return null;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return 'PT'.($h ? $h.'H' : '').($m ? $m.'M' : '').($s ? $s.'S' : '');
    }

    /**
     * Removes the empty values, keeps the booleans.
     */
    private function clean(array $data): array
    {
        return array_filter($data, static fn ($v): bool => null !== $v && '' !== $v && [] !== $v);
    }
}
