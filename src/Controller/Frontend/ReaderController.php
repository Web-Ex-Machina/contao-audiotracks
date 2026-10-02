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

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\AudioTracksBundle\Model\AudioTrack;
use Contao\System;

#[AsFrontendModule(
    ReaderController::TYPE, 
    category: 'wem_audiotracks',
)]
class ReaderController extends ModuleController
{
    /**
     * Module name
     */
    public const TYPE = 'wem_audiotracks_reader';

    protected ?AudioTrack $track = null;

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Return empty Response if there is no auto_item
        if (!Input::get('auto_item')) {
            return new Response('');
        }

        $this->track = AudioTrack::findByIdOrAlias(Input::get('auto_item'));

        // Unpublished or out of its start / stop dates: the page does not exist
        if (!$this->track || !$this->track->isPublished()) {
            throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
        }

        $this->model = $model;

        if ($model->overviewPage) {
            $template->referer = PageModel::findById($model->overviewPage)->getFrontendUrl();
            $template->back = $model->customLabel ?: $GLOBALS['TL_LANG']['MSC']['newsOverview'];
        }

        $this->overwriteMetadata();

        $item = $this->parseItem($this->track);

        // On its own page, the episode URL is the current one
        $item['schemaOrg']['url'] = Environment::get('uri');

        $template->item = $item;
        $template->item_template = $this->getItemTemplate();

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
