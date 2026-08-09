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

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Exception;
use WEM\AudioTracksBundle\Model\Category;
use Symfony\Component\Uid\Uuid;

class CategoryContainer
{
    /**
     * Display the location of the rss feed
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'config.onload')]
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
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'config.onsubmit')]
    public function generateRssFeed(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = Category::findByPk($id);

        if (!$objItem->rss) {
            return;
        }

        try {
            System::getContainer()->get('wem.audiotracks.rss_feed')->generate((int) $dc->id);

            Message::addConfirmation('RSS Feed saved');
        } catch(\Exception $exception) {
            Message::addError($exception->getMessage());
        }
    }

    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'list.operations.syncRemoteRss.button')]
    public function (DataContainerOperation $operation): void
    {
        dump($operation);
        if ('remote' !== $row['type'] && !$row['rssRemoteUrl']) {
            $operation->hide();
        }

        $url = $this->addToUrl($href . '&amp;id=' . Input::get('id'));
        $operation->setUrl($url);
    }

    /**
     * Auto-generate an article alias if it has not been set yet.
     * @throws Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'fields.alias.save')]
    public function generateAlias(mixed $varValue, DataContainer $dc): string
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
     * Auto-generate an article alias if it has not been set yet.
     * @throws Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'fields.rssNamespace.load')]
    public function generateNamespace(mixed $varValue, DataContainer $dc): string
    {
        dump($varValue);
        die;

        if (!$varValue) {
            return (string) Uuid::v4();
        }

        return $varValue;
    }
}

