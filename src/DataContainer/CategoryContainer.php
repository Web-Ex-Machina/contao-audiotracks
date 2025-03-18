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
use Contao\Message;
use Contao\System;
use Exception;
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

        System::getContainer()->get('wem.audiotracks.rss_feed')->generate((int) $dc->id);

        Message::addConfirmation('RSS Feed saved');
    }
}

