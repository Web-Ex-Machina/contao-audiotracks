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
use Contao\FilesModel;
use Contao\Message;
use Contao\System;
use Exception;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Writer\Feed;
use WEM\AudioTracksBundle\Model\Category;

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

        try {
            $feed = Reader::import($url);
        } catch(Exception $e) {
            // It means feed does not exist, create it
            $feed = $this->createRssFeed($objItem);
        }

        // Update Feed value
        $feed->setDateModified(time());

        $buffer = $feed->export($objItem->rssType);

        dump($buffer);
        die;
    }

    /**
     * Generate the feed part of the RSS
     * 
     * @var WEM\AudioTracksBundle\Model\Category
     * 
     * @return Laminas\Feed\Writer\Feed 
     */
    protected function createRssFeed($objItem): Feed
    {
        $feed = new Feed;
        $feed->setTitle($objItem->title);
        $feed->setDescription($objItem->rssDescription ?: $objItem->description);
        $feed->setLink($objItem->rssLink);
        $feed->setFeedLink($objItem->getRssFeedUrl(), $objItem->rssType);
        $feed->addAuthor([
            'name'  => $objItem->authorName,
            'email' => $objItem->authorEmail,
            'uri'   => $objItem->authorUri,
        ]);
        $feed->setDateCreated(time());
        $feed->setLanguage($objItem->language);
        $feed->setCopyright($objItem->rssCopyright);
        $feed->addHub($objItem->rssHub);

        if ($objFile = FilesModel::findByUuid($objItem->picture)) {
            $feed->setImage([
                'uri' => Environment::get('base') . $objFile->path,
                'title' => $objItem->title,
                'link' => $objItem->authorUri,
            ]);
        }

        $arrCategories = unserialize($objItem->categories);
        if (is_iterable($arrCategories)) {
            foreach ($arrCategories as $c) {
                $feed->addCategory([
                    "term" => $c,
                    "label" => $c,
                ]);
            }
        }

        return $feed;
    }
}

