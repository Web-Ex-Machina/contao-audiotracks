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

use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Environment;
use Contao\FilesModel;
use Contao\Date;
use Contao\Input;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Exception;
use WEM\AudioTracksBundle\Classes\SchemaOrgBuilder;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Util\MP3File;
use Symfony\Component\HttpFoundation\Request;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

abstract class ModuleController extends AbstractFrontendModuleController
{
    protected ModuleModel $model;

    public function __construct(protected readonly SchemaOrgBuilder $schemaOrgBuilder)
    {
    }

    /**
     * Category IDs handled by the module.
     */
    protected array $pids = [];

    /**
     * List config.
     */
    protected array $config = [];

    /**
     * List options.
     */
    protected array $options = [];

    /**
     * List filters.
     */
    protected array $filters = [];

    protected function syncFeedFromRemote($id)
    {
        $objFeed = Category::findByPk($id);

        // Skip if the feed is not remote
        // or if the feed has been updated during the last hour
        if ('remote' !== $objFeed->type || $objFeed->rssRemoteLastSync > strtotime("-1 minute")) {
            return;
        }

        // Launch service
        System::getContainer()->get('wem.audiotracks.rss_feed')->import((int) $id);
    }

    /**
     * Retrieve list filters.
     *
     * @throws Exception
     */
    protected function buildFilters(): void
    {
        // Add fulltext search if asked
        if ($this->model->wemaudiotracks_addSearch) {
            $this->filters[] = [
                'type' => 'text',
                'name' => 'search',
                'label' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['search'],
                'placeholder' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['searchPlaceholder'],
                'value' => Input::get('search') ?: '',
            ];

            if ('' !== Input::get('search') && null !== Input::get('search')) {
                $this->config['search'] = StringUtil::formatKeywords(Input::get('search'));
            }
        }

        // Retrieve and format dropdowns filters
        $filters = StringUtil::deserialize($this->model->wemaudiotracks_filters, true);
        if (!empty($filters)) {
            foreach ($filters as $f) {
                $strName = $f;

                if ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['eval']['multiple']) {
                    $strName .= '[]';
                }

                $filter = [
                    'type' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['inputType'],
                    'name' => $strName,
                    'label' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['label'][0] ?: $GLOBALS['TL_LANG']['tl_wem_audiotrack'][$f][0],
                    'placeholder' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['label'][1] ?: $GLOBALS['TL_LANG']['tl_wem_audiotrack'][$f][1],
                    'value' => Input::get($f) ?: '',
                    'options' => [],
                    'multiple' => (bool)$GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['eval']['multiple'],
                ];

                switch ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['inputType']) {
                    case 'select':
                        $options = [];
                        if (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'])) {
                            $strClass = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'][0];
                            $strMethod = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'][1];

                            
                            $options = System::importStatic($strClass)->$strMethod(null, $this->pids);
                        } elseif (\is_callable($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'])) {
                            $options = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'](null, $this->pids);
                        } elseif (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options'])) {
                            $options = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options'];
                        }

                        foreach ($options as $label) {
                            $filter['options'][] = [
                                'value' => $label,
                                'label' => $label,
                                'selected' => (null !== Input::get($f) && (Input::get($f) === $label || (\is_array(Input::get($f)) && \in_array($label, Input::get($f), true)))),
                            ];
                        }

                        break;

                    case 'text':
                    default:
                        $objOptions = AudioTrack::findItemsGroupByOneField($f);

                        if ($objOptions && 0 < $objOptions->count()) {
                            $filter['type'] = 'select';
                            while ($objOptions->next()) {
                                $filter['options'][] = [
                                    'value' => $objOptions->{$f},
                                    'label' => $objOptions->{$f},
                                    'selected' => (null !== Input::get($f) && Input::get($f) === $objOptions->{$f}),
                                ];
                            }
                        }

                        break;
                }

                if (null !== Input::get($f) && '' !== Input::get($f)) {
                    $this->config[$f] = Input::get($f);
                }

                $this->filters[] = $filter;
            }
        }

        // Hook system to customize filters
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS'] as $callback) {
                $this->filters = System::importStatic($callback[0])->{$callback[1]}($this->filters, $this);
            }
        }

        // Hook system to customize list config
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG'] as $callback) {
                $this->config = System::importStatic($callback[0])->{$callback[1]}($this->filters, $this->config, $this);
            }
        }

        // Hook system to customize list options
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS'] as $callback) {
                $this->options = System::importStatic($callback[0])->{$callback[1]}($this->filters, $this->config, $this->options, $this);
            }
        }
    }

    /**
     * Parse one or more items and return their template data as array.
     *
     * @throws Exception
     */
    protected function parseItems(Collection $objItems, bool $blnAddArchive = false): array
    {
        $limit = $objItems->count();

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrArticles = [];

        while ($objItems->next()) {
            /** @var AudioTrack $objItem */
            $objItem = $objItems->current();

            $arrArticles[] = $this->parseItem($objItem, $blnAddArchive, ((1 === ++$count) ? ' first' : '').(($count === $limit) ? ' last' : '').((0 === ($count % 2)) ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Resolve the item template identifier (e.g. "audiotracks/item/full").
     * Handles the legacy "wemaudiotrack_*" values saved before the Twig migration.
     */
    protected function getItemTemplate(): string
    {
        $template = (string) $this->model->wemaudiotracks_template;

        if ('' === $template || 'wemaudiotrack_default' === $template) {
            return 'audiotracks/item';
        }

        if (str_starts_with($template, 'wemaudiotrack_')) {
            return 'audiotracks/item/'.substr($template, \strlen('wemaudiotrack_'));
        }

        return $template;
    }

    /**
     * Parse an item and return the data given to the item template.
     *
     * @throws Exception
     */
    protected function parseItem(AudioTrack $objItem, bool $blnAddArchive = false, string $strClass = '', int $intCount = 0): array
    {
        $arrData = $objItem->row();

        if ('' !== (string) $objItem->cssClass) {
            $strClass = ' '.$objItem->cssClass.$strClass;
        }

        $arrData['class'] = $strClass;
        $arrData['count'] = $intCount;

        // Add the meta information
        $arrData['date'] = $objItem->date ? Date::parse(Config::get('dateFormat'), (int) $objItem->date) : '';
        $arrData['timestamp'] = $objItem->date;
        $arrData['datetime'] = $objItem->date ? date('Y-m-d\TH:i:sP', (int) $objItem->date) : '';

        // Prepare teaser
        $arrData['teaser'] = StringUtil::substr((string) $objItem->description, 300);

        // Tags
        $arrData['tagList'] = array_values(StringUtil::deserialize($objItem->tags, true));

        $isRemote = 'remote' === $objItem->getRelated('pid')->type;

        // Retrieve and parse the pictures
        if ($isRemote && $objItem->pictureRemoteUrl) {
            $arrData['picture'] = $objItem->pictureRemoteUrl;
            $arrData['pictureMobile'] = null;
        } else {
            $arrData['picture'] = FilesModel::findByUuid($objItem->picture)?->path;
            $arrData['pictureMobile'] = FilesModel::findByUuid($objItem->picture_mobile)?->path;
        }

        // If item is from remote, file path is different
        if ($isRemote) {
            $arrData['audio'] = $objItem->audioRemoteUrl;
        } else {
            // If there is no duration, retrieve it and save it in the model
            $objFile = FilesModel::findByUuid($objItem->audio);

            if (!$objItem->duration && $objFile) {
                $mp3file = new MP3File($objFile->path);
                $objItem->duration = $mp3file->getDuration();
                $objItem->save();
            }

            $arrData['audio'] = $objFile?->path;
        }

        $arrData['duration'] = ($objItem->duration > 3600) ?
            sprintf('%s h %s%s min', number_format($objItem->duration / 3600), $objItem->duration / 60 % 60 < 10 ? '0' : '', $objItem->duration / 60 % 60) :
            sprintf('%s min %s%s s', $objItem->duration / 60 % 60, $objItem->duration % 60 < 10 ? '0' : '', $objItem->duration % 60)
        ;
        $arrData['durationRaw'] = $objItem->duration;

        // Retrieve the feedback from this IP
        $arrData['liked'] = 0 < Feedback::countItems(['pid' => $objItem->id, 'ip' => Environment::get('ip')]);
        $arrData['nbLikes'] = Feedback::countItems(['pid' => $objItem->id]);

        // Retrieve user session if exists
        $objSession = Session::findItems(['pid' => $objItem->id, 'ip' => Environment::get('ip')], 1);
        $arrSession = [];

        if ($objSession instanceof Collection) {
            $arrSession = [
                'currentTime' => $objSession->currentTime,
                'volume' => $objSession->volume,
                'complete' => 1 === (int) $objSession->complete,
            ];
        }

        $arrData['session'] = $arrSession;

        // Let template know if we can download the item
        $arrData['canDownload'] = (bool) $this->model->wemaudiotracks_canDownload;

        if ($objTarget = PageModel::findWithDetails($this->model->jumpTo)) {
            $arrData['jumpTo'] = $objTarget->getFrontendUrl('/'.$objItem->alias);
        }

        // schema.org JSON-LD, given to the template with add_schema_org()
        $arrData['schemaOrg'] = $this->schemaOrgBuilder->buildEpisode($objItem, $objItem->getRelated('pid'), $arrData);

        // Hook system to customize item parsing
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'] as $callback) {
                $arrData = System::importStatic($callback[0])->{$callback[1]}($arrData, $objItem, $this);
            }
        }

        return $arrData;
    }
}
