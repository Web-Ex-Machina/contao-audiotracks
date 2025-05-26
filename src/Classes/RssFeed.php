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
        $feed->setItunesType($objItem->tracksType);
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

    public function import(int $id): void
    {
        $objItem = Category::findByPk($id);

        if ('remote' !== $objItem->type && !$objItem->rssRemoteUrl) {
            return;
        }

        $feed = Reader::import($objItem->rssRemoteUrl);

        // Update local columns
        // @todo add controls
        $objItem->title = $feed->getTitle();
        $objItem->description = $feed->getDescription();
        $objItem->language = $feed->getLanguage();
        $objItem->rssLink = $feed->getLink();
        $objItem->createdAt = $feed->getDateCreated()->getTimestamp();
        $objItem->tstamp = $feed->getDateModified()->getTimestamp();
        $objItem->rssRemoteLastSync = $feed->getDateModified()->getTimestamp();
        $objItem->rssCopyright = $feed->getCopyright();
        $objItem->tracksType = $feed->getPodcastType();
        $objItem->complete = (bool) $feed->getPodcastType() ? '1' : '';
        $objItem->explicit = (bool) $feed->getExplicit() ? '1' : '';

        // Update picture
        $picture = $feed->getImage();
        if ($picture && !empty($picture)) {
            // @todo retrieve picture
            $objItem->pictureAlt = $picture['title'];
            $objItem->pictureTitle = $picture['title'];
        }

        // Update categories
        $categories = $feed->getItunesCategories();
        if ($categories && !empty($categories)) {
            $data = [];
            foreach ($categories as $k => $c) {
                $data[] = $k;
            }

            if (!empty($data)) {
                $objItem->categories = serialize($data);
            }
        }

        // Parse authors & owners
        $arrAuthors = [];

        // @todo Update authors
        $authors = $feed->getAuthors();
        if ($authors && !empty($authors)) {
            foreach ($authors as $a) {

            }
        }

        // Update owner
        // @todo try with more formats?
        $owner = $feed->getOwner();
        if ($owner) {
            $str = explode(' (', $owner);
            $arrAuthors[] = [
                'name' => substr($str[1], 0, -1),
                'email' => $str[0],
                'uri' => '',
            ];
        }

        $objItem->authors = serialize($arrAuthors);

        // Save item
        $objItem->save();

        $data = [
            'getImage' => $feed->getImage(),
            'getGenerator' => $feed->getGenerator(),
            'getHubs' => $feed->getHubs(),
            'entries'      => [],
        ];

        // Import tracks
        foreach ($feed as $entry) {
            $edata = [
                'id'           => $entry->getId(),
                'title'        => $entry->getTitle(),
                'description'  => $entry->getDescription(),
                'dateCreated'  => $entry->getDateCreated(),
                'dateModified' => $entry->getDateModified(),
                'authors'      => $entry->getAuthors(),
                'link'         => $entry->getLink(),
                'content'      => $entry->getContent(),
                'enclosure'    => $entry->getEnclosure(),
                'baseUrl'      => $entry->getBaseUrl(),
                'links'        => $entry->getLinks(),
                'permalink'    => $entry->getPermalink(),
                'commentCount' => $entry->getCommentCount(),
                'categories'   => $entry->getCategories(),
                'source'       => $entry->getSource(),
                'explicit'     => $entry->getPlayPodcastExplicit(),
                'castAuthor'   => $entry->getCastAuthor(),
                'duration'     => $entry->getDuration(),
                'subtitle'     => $entry->getSubtitle(),
                'itunesImage'  => $entry->getItunesImage(),
                'episode'      => $entry->getEpisode(),
                'episodeType'  => $entry->getEpisodeType(),
                'isCC'         => $entry->isClosedCaptioned(),
                'season'       => $entry->getSeason(),
                'transcript'   => $entry->getTranscript(),
                'chapters'     => $entry->getChapters(),
                'soundBites'   => $entry->getSoundbites(),
            ];
            $data['entries'][] = $edata;
            $objTrack = $this->importTrack($entry, $objItem);
        }
    }

    protected function importTrack($entry, $objCategory): AudioTrack
    {
        // Try to retrieve an existing track
        $objTrack = AudioTrack::findItems(['pid' => $objCategory->id, 'uuid' => $entry->getId()], 1);

        if (!$objTrack) {
            $objTrack = new AudioTrack();
            $objTrack->uuid = $entry->getId();
            $objTrack->pid = $objCategory->id;
        } else {
            $objTrack = $objTrack->current();
        }

        $objTrack->tstamp = $entry->getDateModified()->getTimestamp();
        $objTrack->createdAt = $entry->getDateCreated()->getTimestamp();
        $objTrack->title = $entry->getTitle();
        $objTrack->date = $entry->getDateCreated()->getTimestamp();
        $objTrack->season = $entry->getSeason() ?: 1;
        $objTrack->episode = $entry->getEpisode() ?: 1;
        $objTrack->audioRemoteUrl = $entry->getLink();
        $objTrack->type = $entry->getEpisodeType();
        $objTrack->description = $entry->getDescription();
        $objTrack->explicit = $objCategory->explicit;
        // $objTrack->tags = $entry->getTitle();
        $objTrack->published = 1;

        // Parse duration
        $duration = $entry->getDuration();
        if ($duration) {
            $chunks = explode(':', $duration);
            $objTrack->duration = ((int) $chunks[0] * 60 * 60) + ((int) $chunks[1] * 60) + (int) $chunks[2];
        }

        // Update enclosure
        $enclosure = $entry->getEnclosure();

            
        if ($enclosure) {
            // @todo
        }

        // Update picture
        $picture = $entry->getItunesImage();

        if ($picture) {
            $objTrack->pictureRemoteUrl = $picture;
            $objTrack->pictureText = '';
        }

        // Update authors
        $authors = $entry->getAuthors();
        if ($authors) {
            // @todo
        }

        // Save entry
        $objTrack->save();

        return $objTrack;
    }
}