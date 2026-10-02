<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Controller\Frontend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\AudioTracksBundle\Model\AudioTrack;

#[AsFrontendModule(
    ReaderController::TYPE,
    category: 'wem_audiotracks',
)]
class ReaderController extends ModuleController
{
    /**
     * Module name.
     */
    public const TYPE = 'wem_audiotracks_reader';

    protected AudioTrack|null $track = null;

    /**
     * Generate module response.
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Return empty Response if there is no auto_item
        if (!Input::get('auto_item')) {
            return new Response('');
        }

        $this->track = AudioTrack::findByIdOrAlias(Input::get('auto_item'));

        // Unpublished or out of its start / stop dates: the page does not exist
        if (!$this->track instanceof AudioTrack || !$this->track->isPublished()) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        $this->model = $model;

        if ($model->overviewPage) {
            $template->referer = PageModel::findById($model->overviewPage)->getFrontendUrl();
            $template->back = $model->customLabel ?: $GLOBALS['TL_LANG']['MSC']['newsOverview'];
        }

        $item = $this->parseItem($this->track);

        // The canonical URL of an episode, whatever the way it was reached (id or alias,
        // query string)
        $canonical = $this->getCanonicalUrl();

        // On its own page, the episode URL is the canonical one
        $item['schemaOrg']['url'] = $canonical;

        $this->overwriteMetadata($canonical, $item['schemaOrg']);

        $template->item = $item;
        $template->item_template = $this->getItemTemplate();

        return $template->getResponse();
    }

    protected function getCanonicalUrl(): string
    {
        $objPage = $GLOBALS['objPage'] ?? null;

        if ($objPage instanceof PageModel) {
            return $objPage->getAbsoluteUrl('/'.($this->track->alias ?: $this->track->id));
        }

        return strtok((string) Environment::get('uri'), '?');
    }

    /**
     * Title, description, robots, canonical URL and Open Graph / Twitter tags
     * of the episode.
     *
     * @param array $schemaOrg The schema.org description of the episode (name, image, audio...)
     */
    protected function overwriteMetadata(string $canonical, array $schemaOrg): void
    {
        $responseContext = System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext();

        if (!$responseContext || !$responseContext->has(HtmlHeadBag::class)) {
            return;
        }

        /** @var HtmlHeadBag $htmlHeadBag */
        $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
        $htmlDecoder = System::getContainer()->get('contao.string.html_decoder');
        $feed = $this->track->getRelated('pid');

        $title = $htmlDecoder->inputEncodedToPlainText($this->track->title.' - '.$feed->title);
        $description = $this->shorten($htmlDecoder->htmlToPlainText((string) $this->track->description));

        $htmlHeadBag->setTitle($title);
        $htmlHeadBag->setMetaDescription($description);

        // An empty value would erase the robots of the page
        if ($this->track->robots) {
            $htmlHeadBag->setMetaRobots($this->track->robots);
        }

        // The canonical tag is displayed by the page if enabled on the root page, else
        // we add it
        $objPage = $GLOBALS['objPage'] ?? null;

        if ($objPage instanceof PageModel && $objPage->enableCanonical) {
            $htmlHeadBag->setCanonicalUri($canonical);
        } else {
            // The link tags of the head bag are rendered as <meta> by the page template
            $GLOBALS['TL_HEAD'][] = '<link rel="canonical" href="'.htmlspecialchars($canonical, ENT_QUOTES).'">';
        }

        // Open Graph (a song: the audio, its duration and its date can be given) and Twitter
        $og = [
            'og:type' => 'music.song',
            'og:title' => $htmlDecoder->inputEncodedToPlainText((string) $this->track->title),
            'og:description' => $description,
            'og:url' => $canonical,
            'og:site_name' => $objPage instanceof PageModel ? $objPage->rootPageTitle : null,
            'og:locale' => isset($schemaOrg['inLanguage']) ? str_replace('-', '_', $schemaOrg['inLanguage']) : null,
            'og:image' => $schemaOrg['image'] ?? null,
            'og:audio' => $schemaOrg['associatedMedia']['contentUrl'] ?? null,
            'og:audio:type' => $schemaOrg['associatedMedia']['encodingFormat'] ?? null,
            'music:duration' => $this->track->duration > 0 ? (int) $this->track->duration : null,
            'music:release_date' => $schemaOrg['datePublished'] ?? null,
            'twitter:card' => empty($schemaOrg['image']) ? 'summary' : 'summary_large_image',
        ];

        foreach ($og as $property => $content) {
            if (null === $content || '' === $content) {
                continue;
            }

            // Twitter cards use the "name" attribute, Open Graph the "property" one
            $attribute = str_starts_with($property, 'twitter:') ? 'name' : 'property';
            $htmlHeadBag->addMetaTag((new HtmlAttributes())->set($attribute, $property)->set('content', (string) $content));
        }
    }

    private function shorten(string $text, int $length = 300): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)).'…' : $text;
    }
}
