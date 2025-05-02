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
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\Module;
use Contao\Pagination;
use Exception;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Util\MP3File;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

class AudioTracksList extends Module
{
    /**
     * List config.
     */
    protected array $config = [];

    /**
     * List limit.
     */
    protected ?int $limit = 0;

    /**
     * List offset.
     */
    protected int $offset = 0;

    /**
     * List options.
     */
    protected array $options = [];

    /**
     * List filters.
     */
    protected array $filters = [];

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_wem_audiotracks_list';

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

        $this->loadDatacontainer('tl_wem_audiotrack');
        $this->loadLanguageFile('tl_wem_audiotrack');

        $this->pids = StringUtil::deserialize($this->wemaudiotracks_categories);

        // Return if there are no archives
        if (empty($this->pids) || !\is_array($this->pids)) {
            return '';
        }

        return parent::generate();
    }

    public function updateAudiotrackFeedback($pid, $like = true): void
    {
        $strIp = Environment::get('ip');

        if (false === $like && $objFeedback = Feedback::findItems(['pid' => $pid, 'ip' => $strIp], 1)) {
            $objFeedback->delete();
        }

        if (true === $like && 0 === Feedback::countItems(['pid' => $pid, 'ip' => $strIp])) {
            $objFeedback = new Feedback();
            $objFeedback->tstamp = time();
            $objFeedback->createdAt = time();
            $objFeedback->pid = $pid;
            $objFeedback->ip = $strIp;
            $objFeedback->save();
        }
    }

    public function updateAudiotrackSession($pid, $currentTime = 0, $volume = 1, $markAsComplete = false): void
    {
        $strIp = Environment::get('ip');
        $objSession = Session::findItems(['pid' => $pid, 'ip' => $strIp], 1);

        if (!$objSession instanceof Collection) {
            $objSession = new Session();
            $objSession->createdAt = time();
            $objSession->pid = $pid;
            $objSession->ip = $strIp;
        }

        $objSession->tstamp = time();
        $objSession->volume = $volume;
        $objSession->currentTime = $currentTime;
        $objSession->complete = $markAsComplete ? 1 : '';
        $objSession->save();
    }

    /**
     * Compile list.
     * @throws \Exception
     */
    protected function compile(): void
    {
        // Catch Ajax Request
        if (Input::post('TL_AJAX') && (int) $this->id === (int) Input::post('module')) {
            try {
                switch (Input::post('action')) {
                    // Requires audiotrack ID
                    case 'feedback':
                        if (!Input::post('audiotrack')) {
                            throw new Exception('No audiotrack provided');
                        }

                        $this->updateAudiotrackFeedback(
                            Input::post('audiotrack'),
                            'false' !== Input::post('liked'),
                        );

                        $arrResponse['status'] = 'success';
                    break;

                    // Requires audiotrack ID, currentTime, volume and complete
                    case 'syncSession':
                        if (!Input::post('audiotrack')) {
                            throw new Exception('No audiotrack provided');
                        }

                        $this->updateAudiotrackSession(
                            Input::post('audiotrack'),
                            Input::post('currentTime') ?: 0,
                            Input::post('volume') ?: 1,
                            'true' === Input::post('complete'),
                        );

                        $arrResponse['status'] = 'success';
                    break;

                    default:
                        throw new Exception($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['unknownAjaxAction'], Input::post('action'));
                }
            } catch (Exception $e) {
                $arrResponse['status'] = 'error';
                $arrResponse['message'] = $e->getMessage();
            }

            $contaoCsrfTokenManager = System::getContainer()->get('contao.csrf.token_manager');
            $arrResponse['rt'] = $contaoCsrfTokenManager->getDefaultTokenValue();

            echo json_encode($arrResponse);
            exit;
        }

        $this->limit = null;
        $this->offset = (int) $this->skipFirst;
        $this->options = ['order' => 'date DESC'];

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $this->limit = $this->numberOfItems;
        }

        $this->Template->articles = [];
        $this->Template->empty = $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['empty'];

        // Add pids
        $this->config = ['pid' => $this->pids, 'published' => 1];

        // Retrieve filters
        $this->buildFilters();
        $this->Template->filters = $this->filters;

        // Retrieve links
        if ($this->wemaudiotracks_links) {
            $arrLinks = [];
            $arrLinkTypes = unserialize($this->wemaudiotracks_links);
            foreach ($this->pids as $pid) {
                // Get the model
                $objCategory = Category::findByPk($pid);

                if (!array_key_exists($pid, $arrLinks)) {
                    $arrLinks[$pid] = $objCategory->row();
                    $arrLinks[$pid]['links'] = [];
                }

                foreach ($arrLinkTypes as $t) {
                    switch ($t) {
                        case 'rss':
                            $arrLinks[$pid]['links'][$t] = [
                                'href' => $objCategory->getRssFeedUrl(),
                                'title' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['links'][$t],
                                'icon' => '<i class="fa-solid fa-square-rss"></i>'
                            ];
                        break;

                        default:
                            // Nuthin'
                    }
                }
            }

            $this->Template->links = $arrLinks;
        }

        // Get the total number of items
        $intTotal = AudioTrack::countItems($this->config);

        if ($intTotal < 1) {
            return;
        }

        $total = $intTotal - $this->offset;

        // Split the results
        if ($this->perPage > 0 && (!isset($this->limit) || $this->numberOfItems > $this->perPage)) {
            // Adjust the overall limit
            if (isset($this->limit)) {
                $total = min($this->limit, $total);
            }

            // Get the current page
            $id = 'page_n'.$this->id;
            $page = Input::get($id) ?? 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
            }

            // Set limit and offset
            $this->limit = $this->perPage;
            $this->offset += (max($page, 1) - 1) * $this->perPage;
            $skip = (int) $this->skipFirst;

            // Overall limit
            if ($this->offset + $this->limit > $total + $skip) {
                $this->limit = $total + $skip - $this->offset;
            }

            // Add the pagination menu
            $objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        $objItems = AudioTrack::findItems($this->config, ($this->limit ?: 0), ($this->offset ?: 0), $this->options);

        // Add the articles
        if ($objItems instanceof Collection) {
            $this->Template->items = $this->parseItems($objItems);
        }

        $this->Template->module_id = $this->id;
        $this->Template->addFilters = $this->wemaudiotracks_addFilters;
    }

    /**
     * Retrieve list filters.
     *
     * @throws Exception
     */
    protected function buildFilters(): void
    {
        // Add fulltext search if asked
        if ($this->wemaudiotracks_addSearch) {
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
        $filters = StringUtil::deserialize($this->wemaudiotracks_filters);
        if (\is_array($filters) && $filters !== []) {
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
                    'multiple' => (bool)$GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['eval']['multiple'],
                ];

                switch ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['inputType']) {
                    case 'select':
                        $options = [];
                        if (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'])) {
                            $strClass = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'][0];
                            $strMethod = $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['options_callback'][1];

                            $this->import($strClass);
                            $options = $this->$strClass->$strMethod(null, $this->pids);
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
                $this->filters = static::importStatic($callback[0])->{$callback[1]}($this->filters, $this);
            }
        }

        // Hook system to customize list config
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTCONFIG'] as $callback) {
                $this->config = static::importStatic($callback[0])->{$callback[1]}($this->filters, $this->config, $this);
            }
        }

        // Hook system to customize list options
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTOPTIONS'] as $callback) {
                $this->options = static::importStatic($callback[0])->{$callback[1]}($this->filters, $this->config, $this->options, $this);
            }
        }
    }

    /**
     * Parse one or more items and return them as array.
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
     * Parse an item and return it as string.
     *
     * @param AudioTrack $objItem
     *
     * @throws Exception
     */
    protected function parseItem(AudioTrack $objItem, bool $blnAddArchive = false, string $strClass = '', int $intCount = 0): string
    {
        $objTemplate = new FrontendTemplate($this->wemaudiotracks_template);
        $objTemplate->setData($objItem->row());

        if ('' !== $objItem->cssClass) {
            $strClass = ' '.$objItem->cssClass.$strClass;
        }

        $objTemplate->class = $strClass;
        $objTemplate->count = $intCount; // see #5708

        // Add the meta information
        $objTemplate->date = (int) $objItem->date;
        $objTemplate->timestamp = $objItem->date;
        $objTemplate->datetime = date('Y-m-d\TH:i:sP', (int) $objItem->date);

        // Retrieve and parse the picture
        if ($objItem->picture && $objFile = FilesModel::findByUuid($objItem->picture)) {
            $objTemplate->picture =  \Image::get($objFile->path, 300, 300);
        }

        if ($objItem->picture_mobile && $objFile = FilesModel::findByUuid($objItem->picture_mobile)) {
            $objTemplate->picture_mobile = \Image::get($objFile->path, 300, 300);
        }

        
        // If item is from remote, file path is different
        if ('remote' === $objItem->getRelated('pid')->type) {
            $objTemplate->audio = $objItem->audioRemoteUrl;
        } else {
            // If there is no duration and an item
            // Retrieve the duration and save it in the model
            $objFile = FilesModel::findByUuid($objItem->audio);
            if (!$objItem->duration && $objFile) {
                $mp3file = new MP3File($objFile->path);
                $objItem->duration = $mp3file->getDuration();
                $objItem->save();
            }

            $objTemplate->audio = $objFile->path;
        }

        $objTemplate->duration = ($objItem->duration > 3600) ?
            sprintf('%s h %s%s min', number_format($objItem->duration / 3600), $objItem->duration / 60 % 60 < 10 ? '0' : '', $objItem->duration / 60 % 60) :
            sprintf('%s min %s%s s', $objItem->duration / 60 % 60, $objItem->duration % 60 < 10 ? '0' : '', $objItem->duration % 60)
        ;
        $objTemplate->durationRaw = $objItem->duration;

        // Retrieve the feedback from this IP
        $objTemplate->liked = 0 < Feedback::countItems(['pid' => $objItem->id, 'ip' => Environment::get('ip')]);
        $objTemplate->nbLikes = Feedback::countItems(['pid' => $objItem->id]) ?: 0;

        // Retrieve user session if exists
        $objSession = Session::findItems(['pid' => $objItem->id, 'ip' => Environment::get('ip')], 1);

        if ($objSession instanceof Collection) {
            $objTemplate->session = [
                'currentTime' => $objSession->currentTime,
                'volume' => $objSession->volume,
                'complete' => 1 === (int) $objSession->complete,
            ];
        }

        // Let template know if we can download the item
        if ($this->wemaudiotracks_canDownload) {
            $objTemplate->canDownload = true;
        }

        // Hook system to customize item parsing
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'] as $callback) {
                $objTemplate = static::importStatic($callback[0])->{$callback[1]}($objTemplate, $objItem, $this);
            }
        }

        return $objTemplate->parse();
    }
}
