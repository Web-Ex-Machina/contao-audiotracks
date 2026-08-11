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

namespace WEM\AudioTracksBundle\Controller\Frontend;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\Module;
use Contao\ModuleModel;
use Contao\Pagination;
use Contao\PageModel;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Util\MP3File;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

#[AsFrontendModule(
    ReaderController::TYPE, 
    category: 'wem_audiotracks',
    template: 'mod_wem_audiotracks_reader'
)]
class ReaderController extends ModuleController
{
    /**
     * Module name
     */
    public const TYPE = 'wem_audiotracks_reader';

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
         // Return empty Response if there is no auto_item
        if (!$request->query->has('auto_item')) {
            return new Response('');
        }

        $this->track = AudioTrack::findByIdOrAlias($request->query->get('auto_item'));

        if (!$this->track) {
            throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
        }

        if ($model->overviewPage) {
            $template->referer = PageModel::findById($model->overviewPage)->getFrontendUrl();
            $template->back = $model->customLabel ?: $GLOBALS['TL_LANG']['MSC']['newsOverview'];
        }

        $this->overwriteMetadata();

        $template->buffer = $this->parseItem($this->track);
        $template->moduleId = $model->id;

        return $template->getResponse();
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
