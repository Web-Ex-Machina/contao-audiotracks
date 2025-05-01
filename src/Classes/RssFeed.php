<?php

namespace WEM\AudioTracksBundle\Classes;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Message;
use Exception;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Writer\Feed;
use Symfony\Component\Uid\Uuid;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\AudioTrack;

class RssFeed
{
    protected ContaoFramework $framework;

    public function __construct(
        ContaoFramework $framework,
    )
    {
        $this->framework = $framework;
        $this->framework->initialize();
    }

    /**
     * Generate the RSS feed
     */
    public function generate(int $id): void
    {        
        $objItem = Category::findByPk($id);

        if (!$objItem->rss) {
            return;
        }

        $url = $objItem->getRssFeedUrl();
        $feed = $this->createRssFeed($objItem);

        // Retrieve item tracks
        $objTracks = AudioTrack::findItems(['pid' => $objItem->id, 'published' => 1], 0, 0, ['order' => 'date DESC']);

        if (!$objTracks || 0 === $objTracks->count()) {
            Message::addError('No tracks found, no RSS generated');
            return;
        }

        $totalDuration = 0;
        while ($objTracks->next()) {
            $feed = $this->addTrackToRssFeed($objTracks->current(), $objItem, $feed);
            $totalDuration += $objTracks->duration;
        }

        $feed->setItunesDuration(sprintf('%02d:%02d:%02d', $totalDuration/3600, floor($totalDuration/60)%60, $totalDuration%60));

        $buffer = $feed->export($objItem->rssType);

        // Open the file
        $path = $objItem->getRssFeedPath();
        $objFile = new File($path);
        $objFile->write($buffer);
        $objFile->close();
    }

    /**
     * Generate the feed part of the RSS
     * 
     * @var WEM\AudioTracksBundle\Model\Category
     * 
     * @return Laminas\Feed\Writer\Feed 
     * 
     * @todo setItunesSubtitle
     */
    protected function createRssFeed($objItem): Feed
    {
        $feed = new Feed;
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
            $authors = unserialize($objItem->authors);
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
                'uri' => Environment::get('base') . $objFile->path,
                'title' => $objItem->title,
                'link' => Environment::get('base') . $objFile->path,
            ]);
            $feed->setItunesImage(Environment::get('base') . $objFile->path);
        }

        // Feed categories
        $arrCategories = unserialize($objItem->categories);
        if (is_iterable($arrCategories)) {
            foreach ($arrCategories as $c) {
                $feed->addCategory([
                    "term" => $c,
                    "label" => $c,
                ]);
            }
            $feed->setItunesCategories($arrCategories);
        }

        // Itunes fields
        $feed->setItunesBlock("yes");
        $feed->setItunesType($objItem->type);
        $feed->setItunesExplicit('1' === $objItem->explicit);
        $feed->setItunesComplete('1' === $objItem->complete);

        return $feed;
    }

    /**
     * Generate an entry of the RSS
     * 
     * @var WEM\AudioTracksBundle\Model\AudioTrack
     * @var WEM\AudioTracksBundle\Model\Category
     * @var Laminas\Feed\Writer\Feed
     * 
     * @return Laminas\Feed\Writer\Feed 
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
            $authors = unserialize($objItem->authors);
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
        if ($objFile = FilesModel::findByUuid($uuid)) {
            $entry->setEnclosure([
                'type' => mime_content_type($objFile->path),
                'uri' => Environment::get('base') . $objFile->path,
                'length' => filesize($objFile->path)
            ]);

            $entry->setItunesImage(Environment::get('base') . $objFile->path);
        }

        $arrCategories = unserialize($objCategory->categories);
        if (is_iterable($arrCategories)) {
            foreach ($arrCategories as $c) {
                $entry->addCategory([
                    "term" => $c,
                    "label" => $c,
                ]);
            }
        }

        $entry->setItunesExplicit('1' === $objItem->explicit);
        $entry->setItunesDuration(sprintf('%02d:%02d:%02d', $objItem->duration/3600, floor($objItem->duration/60)%60, $objItem->duration%60));
        $entry->setItunesSeason((int) $objItem->season);
        $entry->setItunesEpisode((int) $objItem->episode);
        $entry->setItunesEpisodeType($objItem->type);

        // Add audio as enclosure
        if ($objFile = FilesModel::findByUuid($objItem->audio)) {
            $entry->setEnclosure([
                'type' => mime_content_type($objFile->path),
                'uri' => Environment::get('base') . $objFile->path,
                'length' => filesize($objFile->path)
            ]);
        }

        $feed->addEntry($entry);

        return $feed;
    }
}