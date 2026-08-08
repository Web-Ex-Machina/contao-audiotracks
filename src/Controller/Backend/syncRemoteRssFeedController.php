<?php

declare(strict_types=1);

/*
 * Geodata Bundle for Contao Open Source CMS
 * @author     Web Ex Machina
 *
 * @see        https://github.com/Web-Ex-Machina/contao-geodata
 * @license    https://www.apache.org/licenses/LICENSE-2.0
 */

namespace WEM\AudioTracksBundle\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Exception;
use WEM\AudioTracksBundle\Model\Category;

/**
 * Provide backend functions to Locations Extension.
 */
class SyncRemoteRssFeedController extends AbstractController
{
    public function __construct(

    ) {
    }

    public function run(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objItem = Category::findByPk($dc->id);

        if ('remote' !== $objItem->type && !$objItem->rssRemoteUrl) {
            return;
        }

        try {
            System::getContainer()->get('wem.audiotracks.rss_feed')->import((int) $dc->id);

            Message::addConfirmation('RSS Feed imported');
        } catch(Exception $exception) {
            Message::addError($exception->getMessage());
        }
    }
}