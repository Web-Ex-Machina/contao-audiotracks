<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/contao-audiotracks
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\DataContainer;

use Contao\Backend;
use Contao\DataContainer;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Message;
use Contao\System;
use Exception;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Writer\Feed;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\AudioTrack;

class CategoryContainer extends Backend
{
    /**
     * Auto-generate an article alias if it has not been set yet.
     * @throws Exception
     */
    public function generateAlias($varValue, DataContainer $dc): string
    {
        $aliasExists = fn(string $alias): bool => $this->Database->prepare('SELECT id FROM tl_wem_audiotrack_category WHERE alias=? AND id!=?')->execute($alias, $dc->id)->numRows > 0;

        // Generate an alias if there is none
        if (!$varValue) {
            $varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->title, $dc->activeRecord->id, $aliasExists);
        } elseif ($aliasExists($varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }

    /**
     * Display the location of the rss feed
     */
    public function displayRssUrl(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }
        
        $objItem = Category::findByPk($dc->id);

        if (!$objItem->rss) {
            return;
        }

        $url = $objItem->getRssFeedUrl();

        Message::addInfo('RSS Feed is located at: <a href="' . $url . '" title="Go to RSS Feed" target="_blank">' . $url . '</a>');
    }

    /**
     * Generate the RSS feed
     */
    public function generateRssFeed(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }
        
        $objItem = Category::findByPk($dc->id);

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

        Message::addConfirmation('RSS Feed saved');
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

        $desc = $objItem->rssDescription ?: $objItem->description;
        $feed->setDescription(strip_tags($desc));
        $feed->setItunesSummary(strip_tags($desc));
        $feed->setLink($objItem->rssLink);
        $feed->setItunesNewFeedUrl($objItem->getRssFeedUrl());
        $feed->setFeedLink($objItem->getRssFeedUrl(), $objItem->rssType);

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

        $feed->setDateCreated(time());
        $feed->setDateModified(time());
        $feed->setLastBuildDate(time());
        $feed->setLanguage($objItem->language);
        $feed->setCopyright($objItem->rssCopyright);
        $feed->addHub($objItem->rssHub);

        if ($objFile = FilesModel::findByUuid($objItem->picture)) {
            $feed->setImage([
                'uri' => Environment::get('base') . $objFile->path,
                'title' => $objItem->title,
                'link' => $objItem->authorUri,
            ]);
            $feed->setItunesImage(Environment::get('base') . $objFile->path);
        }

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
     * @todo setItunesDuration
     * @todo setItunesTitle
     * @todo setItunesSubtitle
     * @todo setItunesSummary
     * @todo setItunesImage
     * @todo setItunesEpisode
     * @todo setItunesEpisodeType
     * @todo setItunesIsClosedCaptioned
     * @todo setItunesSeason
     */
    protected function addTrackToRssFeed(AudioTrack $objItem, Category $objCategory, Feed $feed): Feed
    {
        $entry = $feed->createEntry();

        $entry->setId((string) $objItem->id);
        $entry->setTitle(html_entity_decode($objItem->title));
        $entry->setLink('http://www.example.com/all-your-base-are-belong-to-us');
        
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
        $entry->setContent($objItem->description);
        $entry->setCopyright($objCategory->rssCopyright);

        $uuid = $objItem->picture ?: $objCategory->picture;
        if ($objFile = FilesModel::findByUuid($uuid)) {
            $entry->setEnclosure([
                'type' => 'image',
                'uri' => Environment::get('base') . $objFile->path,
                'length' => filesize($objFile->path)
            ]);
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

        $feed->addEntry($entry);

        return $feed;
    }
}

