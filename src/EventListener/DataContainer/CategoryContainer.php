<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Database;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Symfony\Component\Uid\Uuid;
use WEM\AudioTracksBundle\Classes\FeedWithoutTracksException;
use WEM\AudioTracksBundle\Model\Category;

class CategoryContainer
{
    /**
     * Display the location of the rss feed.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'config.onload')]
    public function displayRssUrl(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = Category::findById($dc->id);

        $url = $objItem->rss ? $objItem->getRssFeedUrl() : null;

        if (!$url) {
            return;
        }

        Message::addInfo(\sprintf($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssLocation'], \sprintf('<a href="%1$s" title="%2$s" target="_blank">%1$s</a>', $url, $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssGoTo'])));
    }

    /**
     * Generate the RSS feed.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'config.onsubmit')]
    public function generateRssFeed(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = Category::findById($dc->id);

        if (!$objItem->rss) {
            return;
        }

        try {
            if (System::getContainer()->get('wem.audiotracks.rss_feed')->generate((int) $dc->id)) {
                Message::addConfirmation($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssSaved']);
            }
        } catch (FeedWithoutTracksException) {
            Message::addError($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssNoTracks']);
        } catch (\Exception $exception) {
            Message::addError($exception->getMessage());
        }
    }

    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'list.operations.syncRemoteRss.button')]
    public function syncRemoteRssButton(DataContainerOperation $operation): void
    {
        $row = $operation->getRecord();
        // Only remote categories with a remote url can be synced
        if ('remote' !== ($row['type'] ?? '') || empty($row['rssRemoteUrl'])) {
            $operation->hide();
        }

        $url = Controller::addToUrl($operation->getUrl().'&amp;id='.$row['id']);
        $operation->setUrl($url);
    }

    /**
     * Auto-generate an article alias if it has not been set yet.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'fields.alias.save')]
    public function generateAlias(mixed $varValue, DataContainer $dc): string
    {
        $aliasExists = static fn (string $alias): bool => Database::getInstance()->prepare('SELECT id FROM tl_wem_audiotrack_category WHERE alias=? AND id!=?')->execute($alias, $dc->id)->numRows > 0;

        // Generate an alias if there is none
        if (!$varValue) {
            $varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->title, $dc->activeRecord->id, $aliasExists);
        } elseif ($aliasExists($varValue)) {
            throw new \Exception(\sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }

    /**
     * Auto-generate an article alias if it has not been set yet.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_wem_audiotrack_category', target: 'fields.rssNamespace.load')]
    public function generateNamespace(mixed $varValue, DataContainer $dc): string
    {
        if (!$varValue) {
            return (string) Uuid::v4();
        }

        return $varValue;
    }
}
