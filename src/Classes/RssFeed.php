<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Classes;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Writer\Feed;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;

class RssFeed
{
    protected ContaoFramework $framework;

    public function __construct(
        ContaoFramework $framework,
        private readonly RemoteFeedFetcher $fetcher,
        private readonly TagSynchronizer $tagSynchronizer,
        private readonly LoggerInterface $logger,
    ) {
        $this->framework = $framework;
    }

    /**
     * Generate the RSS feed of a category.
     *
     * @return bool False if there is nothing to generate (unknown category, feed not enabled)
     *
     * @throws FeedWithoutTracksException If the category has no published track
     */
    public function generate(int $id): bool
    {
        return FeedGeneration::Disabled !== $this->generateFeed($id);
    }

    /**
     * Generate the RSS feeds of all the local categories that have one: the new
     * episodes, the episodes that are published or unpublished by their start / stop
     * date, the deleted ones... are taken into account. A feed that fails does not
     * prevent the others from being generated.
     *
     * @return array<int, FeedGeneration|\Throwable> The result for each category
     */
    public function generateAll(): array
    {
        $this->framework->initialize();

        $ids = Database::getInstance()
            ->execute("SELECT id FROM tl_wem_audiotrack_category WHERE type != 'remote' AND rss = '1' AND rssFilename != ''")
            ->fetchEach('id')
        ;

        $results = [];

        foreach ($ids as $id) {
            try {
                $results[(int) $id] = $this->generateFeed((int) $id);
            } catch (\Throwable $e) {
                $results[(int) $id] = $e;
            }
        }

        return $results;
    }

    /**
     * Same as generate(), tells if the file had to be written.
     *
     * @throws FeedWithoutTracksException If the category has no published track
     */
    public function generateFeed(int $id): FeedGeneration
    {
        $this->framework->initialize();

        $objItem = Category::findById($id);

        if (!$objItem || !$objItem->rss || !$objItem->rssFilename) {
            return FeedGeneration::Disabled;
        }

        $feed = $this->createRssFeed($objItem);

        // Retrieve item tracks
        $objTracks = AudioTrack::findItems(['pid' => $objItem->id, 'published' => 1], 0, 0, ['order' => 'date DESC']);

        if (!$objTracks instanceof Collection || 0 === $objTracks->count()) {
            throw new FeedWithoutTracksException(\sprintf('The category %d has no published track', $id));
        }

        $totalDuration = 0;
        $lastChange = (int) $objItem->tstamp;

        while ($objTracks->next()) {
            /** @var AudioTrack $track */
            $track = $objTracks->current();
            $feed = $this->addTrackToRssFeed($track, $objItem, $feed);
            $totalDuration += $objTracks->duration;
            $lastChange = max($lastChange, (int) $objTracks->tstamp, (int) $objTracks->date);
        }

        // The dates of the feed are the ones of its last change (not "now"): the same
        // content gives the same file
        $feed->setDateCreated((int) $objItem->createdAt ?: $lastChange);
        $feed->setDateModified($lastChange);
        $feed->setLastBuildDate($lastChange);

        $feed->setItunesDuration(\sprintf('%02d:%02d:%02d', $totalDuration / 3600, floor($totalDuration / 60) % 60, $totalDuration % 60));

        $buffer = $feed->export($objItem->rssType);

        // The feed is written in the web directory (public/), where the feed URL points to
        $path = $objItem->getRssFeedPath();

        if (is_file($path) && hash_equals(hash('sha256', (string) file_get_contents($path)), hash('sha256', $buffer))) {
            return FeedGeneration::Unchanged;
        }

        (new Filesystem())->dumpFile($path, $buffer);

        return FeedGeneration::Written;
    }

    /**
     * Imports a remote feed: the show (title, cover, categories, owner...) and
     * its episodes.
     *
     * @param bool $force Download the feed even if the server says it has not changed since the last import
     *
     * @throws \RuntimeException If the feed cannot be downloaded or read, nothing is changed in that case
     */
    public function import(int $id, bool $force = false): void
    {
        $this->framework->initialize();

        $objItem = Category::findById($id);

        // Only remote categories with a remote url can be imported
        if (!$objItem || 'remote' !== $objItem->type || !$objItem->rssRemoteUrl) {
            return;
        }

        $fetched = $this->fetcher->fetch(
            $objItem->rssRemoteUrl,
            $force ? null : ($objItem->rssRemoteEtag ?: null),
            $force ? null : ($objItem->rssRemoteModified ?: null),
        );

        // Not modified since the last import: nothing to download nor to import
        if (null === $fetched) {
            $objItem->rssRemoteLastSync = time();
            $objItem->save();

            return;
        }

        try {
            $feed = Reader::importString($fetched['body']);
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('the content is not a valid RSS / Atom feed: '.$throwable->getMessage(), 0, $throwable);
        }

        // Update the columns of the show, the feed is the source of truth
        $objItem->title = $feed->getTitle() ?: $objItem->title;
        $objItem->description = (string) ($feed->getDescription() ?? '');
        $objItem->language = (string) ($feed->getLanguage() ?? '');
        $objItem->rssLink = (string) ($feed->getLink() ?? '');
        $objItem->createdAt = $feed->getDateCreated()?->getTimestamp() ?? $objItem->createdAt;
        $objItem->tstamp = $feed->getDateModified()?->getTimestamp() ?? time();
        $objItem->rssCopyright = (string) ($feed->getCopyright() ?? '');
        $objItem->tracksType = (string) ($feed->getPodcastType() ?? '');
        $objItem->complete = $feed->isComplete() ? '1' : '';
        $objItem->explicit = (bool) $feed->getExplicit() ? '1' : '';

        // Cover: the url of the remote image is kept (nothing is downloaded)
        $image = $feed->getImage();
        $cover = FeedImportParser::url($feed->getItunesImage()) ?: FeedImportParser::url(\is_array($image) ? ($image['uri'] ?? '') : '');

        if ('' !== $cover) {
            $objItem->pictureRemoteUrl = $cover;
        }

        if (\is_array($image)) {
            $objItem->pictureAlt = $image['title'] ?? $objItem->pictureAlt;
            $objItem->pictureTitle = $image['title'] ?? $objItem->pictureTitle;
        }

        // Update categories
        $categories = $feed->getItunesCategories();
        if ($categories) {
            $data = array_keys($categories);

            if ([] !== $data) {
                $objItem->categories = serialize($data);
            }
        }

        // Owner: "email (name)", "name <email>", "email" or "name"
        $owner = $feed->getOwner();
        $objItem->authors = serialize($owner ? [FeedImportParser::parseOwner((string) $owner)] : []);

        $objItem->save();

        // Import tracks
        $arrImportedIds = [];
        $arrTags = [];

        foreach ($feed as $entry) {
            $objTrack = $this->importTrack($entry, $objItem);
            $arrImportedIds[] = $objTrack->uuid;
            $arrTags = array_merge($arrTags, StringUtil::deserialize($objTrack->tags, true));
        }

        // Episodes that are no longer in the feed are unpublished (nothing is done if
        // the feed is empty, it is probably an error on the remote side)
        if ([] !== $arrImportedIds) {
            Database::getInstance()
                ->prepare(\sprintf(
                    "UPDATE tl_wem_audiotrack SET published = '' WHERE pid = ? AND uuid != '' AND uuid NOT IN (%s)",
                    implode(',', array_fill(0, \count($arrImportedIds), '?')),
                ))
                ->execute($objItem->id, ...$arrImportedIds)
            ;
        }

        // The tags of the episodes are the ones the back end offers: the show knows all
        // of them
        $known = StringUtil::deserialize($objItem->tags, true);
        $objItem->tags = serialize(FeedImportParser::tags(array_merge($known, $arrTags)) ?: $known);

        $objItem->rssRemoteEtag = $fetched['etag'] ?? '';
        $objItem->rssRemoteModified = $fetched['lastModified'] ?? '';
        $objItem->rssRemoteLastSync = time();
        $objItem->save();

        $this->logger->info(\sprintf('Audiotracks: the remote feed of the category %d was imported (%d episode(s)).', $id, \count($arrImportedIds)));
    }

    /**
     * Generate the feed part of the RSS.
     *
     * @todo setItunesSubtitle
     */
    protected function createRssFeed($objItem): Feed
    {
        $feed = new Feed();
        $feed->setTitle(html_entity_decode($objItem->title));
        $feed->setLanguage($objItem->language);

        $feed->setDateCreated(time());
        $feed->setDateModified(time());
        $feed->setLastBuildDate(time());

        // If there is no namespace at this point, generate one and save it
        if (!$objItem->rssNamespace) {
            $objItem->rssNamespace = (string) Uuid::v4();
            $objItem->save();
        }

        $namespace = Uuid::fromString($objItem->rssNamespace);
        $feed->setId((string) Uuid::v5($namespace, $objItem->alias));

        // Feed Description
        $desc = $objItem->rssDescription ?: $objItem->description;
        $feed->setDescription(strip_tags($desc));
        $feed->setItunesSummary(strip_tags($desc));

        // Feed Link
        $feed->setLink($objItem->rssLink);
        $feed->setItunesNewFeedUrl($objItem->getRssFeedUrl());
        $feed->setFeedLink($objItem->getRssFeedUrl(), $objItem->rssType);
        $feed->setPodcastIndexFunding([
            'title' => html_entity_decode($objItem->title),
            'url' => $objItem->getRssFeedUrl(),
        ]);

        // Feed Authors
        if ($objItem->authors) {
            $authors = StringUtil::deserialize($objItem->authors, true);
            $names = [];
            $feed->addAuthors($authors);

            foreach ($authors as $a) {
                $names[] = $a['name'];
            }

            $feed->addItunesAuthors($names);
            $feed->addItunesOwners($authors);
        }

        // Feed owner
        $feed->setPodcastIndexLocked([
            'value' => 'no',
            'owner' => html_entity_decode($objItem->title),
        ]);

        // Feed copyright
        if ($objItem->rssCopyright) {
            $feed->setCopyright($objItem->rssCopyright);
        }

        // Feed hub
        if ($objItem->rssHub) {
            $feed->addHub($objItem->rssHub);
        }

        // Feed picture
        if ($objFile = FilesModel::findByUuid($objItem->picture)) {
            $feed->setImage([
                'uri' => BaseUrl::get().$objFile->path,
                'title' => $objItem->title,
                'link' => BaseUrl::get().$objFile->path,
            ]);
            $feed->setItunesImage(BaseUrl::get().$objFile->path);
        }

        // Feed categories
        $arrCategories = StringUtil::deserialize($objItem->categories, true);
        if ([] !== $arrCategories) {
            foreach ($arrCategories as $c) {
                $feed->addCategory([
                    'term' => $c,
                    'label' => $c,
                ]);
            }

            $feed->setItunesCategories($arrCategories);
        }

        // Itunes fields "yes" would hide the podcast from Apple Podcasts
        $feed->setItunesBlock('no');
        $feed->setItunesType($objItem->tracksType);
        $feed->setItunesExplicit('1' === $objItem->explicit);
        $feed->setItunesComplete('1' === $objItem->complete);

        return $feed;
    }

    /**
     * Generate an entry of the RSS.
     *
     * @todo setItunesSubtitle
     * @todo setItunesIsClosedCaptioned
     */
    protected function addTrackToRssFeed(AudioTrack $objItem, Category $objCategory, Feed $feed): Feed
    {
        $entry = $feed->createEntry();

        $entry->setId((string) $objItem->id);
        $entry->setTitle(html_entity_decode($objItem->title));
        $entry->setItunesTitle(html_entity_decode($objItem->title));

        if ($objItem->authors) {
            $authors = StringUtil::deserialize($objItem->authors, true);
            $entry->addAuthors($authors);

            foreach ($authors as $a) {
                $entry->addItunesAuthor($a['name']);
            }
        }

        $entry->setDateModified((int) $objItem->date);
        $entry->setDateCreated((int) $objItem->date);
        $entry->setDescription(strip_tags($objItem->description));
        $entry->setItunesSummary(strip_tags($objItem->description));
        $entry->setContent($objItem->description);
        $entry->setCopyright($objCategory->rssCopyright);

        $uuid = $objItem->picture ?: $objCategory->picture;
        // The episode picture is only an iTunes image, the enclosure is the audio file
        if ($objFile = FilesModel::findByUuid($uuid)) {
            $entry->setItunesImage(BaseUrl::get().$objFile->path);
        }

        $arrCategories = StringUtil::deserialize($objCategory->categories, true);
        if ([] !== $arrCategories) {
            foreach ($arrCategories as $c) {
                $entry->addCategory([
                    'term' => $c,
                    'label' => $c,
                ]);
            }
        }

        $entry->setItunesExplicit('1' === $objItem->explicit);
        $entry->setItunesDuration(\sprintf('%02d:%02d:%02d', $objItem->duration / 3600, floor($objItem->duration / 60) % 60, $objItem->duration % 60));
        $entry->setItunesSeason((int) $objItem->season);
        $entry->setItunesEpisode((int) $objItem->episode);
        $entry->setItunesEpisodeType($objItem->type);

        // Add audio as enclosure
        if ($objFile = FilesModel::findByUuid($objItem->audio)) {
            $entry->setEnclosure([
                'type' => mime_content_type($objFile->path),
                'uri' => BaseUrl::get().$objFile->path,
                'length' => filesize($objFile->path),
            ]);
        }

        $feed->addEntry($entry);

        return $feed;
    }

    /**
     * Generate a unique alias for an imported track.
     */
    protected function generateAlias(string $title, int $id): string
    {
        $aliasExists = static fn (string $alias): bool => Database::getInstance()
            ->prepare('SELECT id FROM tl_wem_audiotrack WHERE alias = ? AND id != ?')
            ->execute($alias, $id)
            ->numRows > 0
        ;

        return System::getContainer()->get('contao.slug')->generate($title, [], $aliasExists);
    }

    protected function importTrack($entry, $objCategory): AudioTrack
    {
        // Try to retrieve an existing track
        $objTrack = AudioTrack::findItems(['pid' => $objCategory->id, 'uuid' => $entry->getId()], 1);
        $isNew = !$objTrack instanceof Collection;

        if ($isNew) {
            $objTrack = new AudioTrack();
            $objTrack->uuid = $entry->getId();
            $objTrack->pid = $objCategory->id;

            // A new episode is published by default, but we never republish an episode that
            // has been unpublished on purpose (or because it left the feed)
            $objTrack->published = 1;
        } else {
            /** @var AudioTrack $objTrack */
            $objTrack = $objTrack->current();
        }

        // The feed is the source of truth: the editorial fields are overwritten at each import
        $objTrack->tstamp = $entry->getDateModified()->getTimestamp();
        $objTrack->createdAt = $entry->getDateCreated()->getTimestamp();
        $objTrack->title = (string) $entry->getTitle();

        // The alias is used in the url of the reader
        if (!$objTrack->alias) {
            $objTrack->alias = $this->generateAlias((string) $objTrack->title, (int) $objTrack->id);
        }

        $objTrack->date = $entry->getDateCreated()->getTimestamp();
        $objTrack->season = $entry->getSeason() ?: 1;
        $objTrack->episode = $entry->getEpisode() ?: 1;
        $objTrack->type = (string) ($entry->getEpisodeType() ?? '');
        $objTrack->description = (string) ($entry->getDescription() ?? '');
        $objTrack->explicit = $objCategory->explicit;
        // The audio file is the enclosure, the link is the web page of the episode
        $enclosure = $entry->getEnclosure();
        $objTrack->audioRemoteUrl = $enclosure && !empty($enclosure->url) ? $enclosure->url : $entry->getLink();

        // Parse duration: "ss", "mm:ss" or "hh:mm:ss"
        $duration = $entry->getDuration();
        if ($duration) {
            $seconds = 0;

            foreach (explode(':', (string) $duration) as $chunk) {
                $seconds = $seconds * 60 + (int) $chunk;
            }

            $objTrack->duration = $seconds;
        }

        // Update picture: the url of the remote image
        $picture = FeedImportParser::url($entry->getItunesImage());

        if ('' !== $picture) {
            $objTrack->pictureRemoteUrl = $picture;
            $objTrack->pictureText = '';
        }

        // Authors (author, dc:creator, then itunes:author). Without any, the ones
        // already there are kept
        $authors = FeedImportParser::authors($entry->getAuthors(), $entry->getCastAuthor());

        if ([] !== $authors) {
            $objTrack->authors = serialize($authors);
        }

        // Tags: the categories and the keywords of the episode. Without any, the ones already
        // there are kept (itunes:keywords is deprecated but still used by many feeds)
        $tags = FeedImportParser::tags($entry->getCategories(), @$entry->getKeywords());

        if ([] !== $tags) {
            $objTrack->tags = serialize($tags);
        }

        // Save entry
        $objTrack->save();

        // The back end fills the pivot table of the tags when a track is saved, the
        // model does not
        if ([] !== $tags) {
            $this->tagSynchronizer->sync((int) $objTrack->id, $tags);
        }

        return $objTrack;
    }
}
