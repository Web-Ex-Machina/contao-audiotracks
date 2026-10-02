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
use Symfony\Component\HttpFoundation\RedirectResponse;
use WEM\AudioTracksBundle\Model\Category;

/**
 * Provide backend functions to Locations Extension.
 */
class SyncRemoteRssFeedController extends AbstractController
{
    public function run(DataContainer $dc): RedirectResponse
    {
        $redirect = new RedirectResponse(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'wemaudiotracks']));

        if (!$dc->id) {
            return $redirect;
        }

        $objItem = Category::findById($dc->id);

        if (!$objItem || 'remote' !== $objItem->type || !$objItem->rssRemoteUrl) {
            return $redirect;
        }

        try {
            System::getContainer()->get('wem.audiotracks.rss_feed')->import((int) $dc->id, true);

            Message::addConfirmation($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssImported']);
        } catch (\Exception $exception) {
            Message::addError(\sprintf($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssImportError'], $exception->getMessage()));
        }

        return $redirect;
    }
}
