<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Symfony\Component\HttpFoundation\RedirectResponse;
use WEM\AudioTracksBundle\Classes\FeedGeneration;
use WEM\AudioTracksBundle\Classes\FeedWithoutTracksException;

/**
 * Back end action: generates the RSS feeds of all the local categories (button of
 * the list of the categories).
 */
class GenerateFeedsController extends AbstractController
{
    public function run(DataContainer $dc): RedirectResponse
    {
        System::loadLanguageFile('default');
        $written = 0;
        $unchanged = 0;
        $empty = 0;

        foreach (System::getContainer()->get('wem.audiotracks.rss_feed')->generateAll() as $id => $result) {
            if (FeedGeneration::Written === $result) {
                ++$written;
            } elseif (FeedGeneration::Unchanged === $result) {
                ++$unchanged;
            } elseif ($result instanceof FeedWithoutTracksException) {
                ++$empty;
            } elseif ($result instanceof \Throwable) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssGenerateError'], $id, $result->getMessage()));
            }
        }

        Message::addConfirmation(\sprintf($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssAllGenerated'], $written, $unchanged, $empty));

        return new RedirectResponse(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'wemaudiotracks']));
    }
}
