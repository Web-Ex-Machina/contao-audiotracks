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

namespace WEM\AudioTracksBundle\Module;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\Module;
use Contao\Pagination;
use Contao\PageModel;
use Exception;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Util\MP3File;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

class AudioTracksReader extends AudioTracksCore
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_wem_audiotracks_reader';

    /**
     * Display a wildcard in the back end
     */
    public function generate(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '. mb_strtoupper($GLOBALS['TL_LANG']['FMD']['wemaudiotrackslist'][0], 'UTF-8').' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        // Hide if no auto_item
        if (!Input::get('auto_item')) {
            return '';
        }

        $this->track = AudioTrack::findByIdOrAlias(Input::get('auto_item'));

        if (!$this->track) {
            throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
        }

        return parent::generate();
    }

    /**
     * Compile list.
     * @throws \Exception
     */
    protected function compile(): void
    {
        $this->catchAjaxRequests();

        global $objPage;


        if ($this->overviewPage) {
            $this->Template->referer = PageModel::findById($this->overviewPage)->getFrontendUrl();
            $this->Template->back = $this->customLabel ?: $GLOBALS['TL_LANG']['MSC']['newsOverview'];
        }

        $this->overwriteMetadata();

        $this->Template->buffer = $this->parseItem($this->track);
        $this->Template->moduleId = $this->id;
    }

    protected function overwriteMetadata(): void
    {
        $responseContext = System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext();
        $feed = $this->track->getRelated('pid');

        if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
            /** @var HtmlHeadBag $htmlHeadBag */
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
            $htmlDecoder = System::getContainer()->get('contao.string.html_decoder');

            $htmlHeadBag->setTitle($htmlDecoder->inputEncodedToPlainText($this->track->title.' - '.$feed->title));
            $htmlHeadBag->setMetaDescription($htmlDecoder->htmlToPlainText($this->track->description));
            $htmlHeadBag->setMetaRobots($this->track->robots ?: '');
        }
    }
}
