<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Module;

use Contao\Module;
use Contao\Input;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Util\MP3File;
use Patchwork\Utf8;

class AudioTracksList extends Module
{
    /**
     * List config.
     */
    protected $config = [];

    /**
     * List limit.
     */
    protected $limit = 0;

    /**
     * List offset.
     */
    protected $offset = 0;

    /**
     * List options.
     */
    protected $options = [];

    /**
     * List filters.
     */
    protected $filters = [];

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_wem_audiotracks_list';

    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['wemaudiotrackslist'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        $this->loadDatacontainer('tl_wem_audiotrack');
        $this->loadLanguageFile('tl_wem_audiotrack');

        $this->pids = \StringUtil::deserialize($this->wemaudiotracks_categories);

        // Return if there are no archives
        if (empty($this->pids) || !\is_array($this->pids)) {
            return '';
        }

        return parent::generate();
    }

    /**
     * Compile list.
     */
    protected function compile()
    {
        // Catch Ajax Request
        if(Input::post("TL_AJAX") && $this->id === Input::post('module')) {
            try {
                switch(Input::post('action')) {
                    // Requires audiotrack ID
                    case 'feedback':
                        if (!Input::post('audiotrack')) {
                            throw new Exception("No audiotrack provided");
                        }

                        $this->updateAudiotrackFeedback(
                            Input::post('audiotrack'),
                            "false" === Input::post('liked') ? false : true,
                        );

                        $arrResponse['status'] = 'success';
                    break;

                    // Requires audiotrack ID, currentTime, volume and complete
                    case 'syncSession':
                        if (!Input::post('audiotrack')) {
                            throw new Exception("No audiotrack provided");
                        }

                        $this->updateAudiotrackSession(
                            Input::post('audiotrack'),
                            Input::post('currentTime') ?: 0,
                            Input::post('volume') ?: 1,
                            "true" === Input::post('complete') ? true : false,
                        );

                        $arrResponse['status'] = 'success';
                    break;

                    default:
                        throw new Exception(sprintf($GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['unknownAjaxAction']), Input::post('action'));
                }
            }
            catch(Exception $e) {
                $arrResponse['status'] = 'error';
                $arrResponse['message'] = $e->getMessage();
            }

            $arrResponse['rt'] = \RequestToken::get();

            echo json_encode($arrResponse);
            die;
        }

        $this->limit = null;
        $this->offset = (int) $this->skipFirst;

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

        // Get the total number of items
        $intTotal = AudioTrack::countItems($this->config);

        if ($intTotal < 1) {
            return;
        }

        $total = $intTotal - $offset;

        // Split the results
        if ($this->perPage > 0 && (!isset($this->limit) || $this->numberOfItems > $this->perPage)) {
            // Adjust the overall limit
            if (isset($this->limit)) {
                $total = min($this->limit, $total);
            }

            // Get the current page
            $id = 'page_n'.$this->id;
            $page = \Input::get($id) ?? 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.\Environment::get('uri'));
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
            $objPagination = new \Pagination($total, $this->perPage, \Config::get('maxPaginationLinks'), $id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        $objItems = AudioTrack::findItems($this->config, ($this->limit ?: 0), ($this->offset ?: 0));

        // Add the articles
        if (null !== $objItems) {
            $this->Template->items = $this->parseItems($objItems);
        }

        $this->Template->module_id = $this->id;
    }

    /**
     * Retrieve list filters.
     *
     * @return array [Array of available filters, parsed]
     */
    protected function buildFilters()
    {
        // Retrieve and format dropdowns filters
        $filters = deserialize($this->wemaudiotracks_filters);
        if (\is_array($filters) && !empty($filters)) {
            foreach ($filters as $f) {
                $strName = $f;

                if($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['eval']['multiple']) {
                    $strName .= '[]';
                }

                $filter = [
                    'type' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['inputType'],
                    'name' => $strName,
                    'label' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['label'][0] ?: $GLOBALS['TL_LANG']['tl_wem_audiotrack'][$f][0],
                    'value' => \Input::get($f) ?: '',
                    'options' => [],
                    'multiple' => $GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['eval']['multiple'] ? true : false,
                ];

                switch ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'][$f]['inputType']) {
                    case 'select':
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

                        foreach ($options as $value => $label) {
                            $filter['options'][] = [
                                'value' => $label,
                                'label' => $label,
                                'selected' => (null !== \Input::get($f) && (\Input::get($f) === $value || (\is_array(\Input::get($f)) && \in_array($value, \Input::get($f))))),
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
                                    'selected' => (null !== \Input::get($f) && \Input::get($f) === $objOptions->{$f}),
                                ];
                            }
                        }
                        break;
                }

                if (null !== \Input::get($f) && '' !== \Input::get($f)) {
                    $this->config[$f] = \Input::get($f);
                }

                $this->filters[] = $filter;
            }
        }

        // Add fulltext search if asked
        if ($this->wemaudiotracks_addSearch) {
            $this->filters[] = [
                'type' => 'text',
                'name' => 'search',
                'label' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['search'],
                'placeholder' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['searchPlaceholder'],
                'value' => \Input::get('search') ?: '',
            ];

            if ('' !== \Input::get('search') && null !== \Input::get('search')) {
                $this->config['search'] = StringUtil::formatKeywords(\Input::get('search'));
            }
        }

        // Hook system to customize filters
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSLISTFILTERS'] as $callback) {
                $this->filters = static::importStatic($callback[0])->{$callback[1]}($this->filters, $this);
            }
        }
    }

    /**
     * Parse one or more items and return them as array.
     *
     * @param Model\Collection $objItems
     * @param bool             $blnAddArchive
     *
     * @return array
     */
    protected function parseItems($objItems, $blnAddArchive = false)
    {
        $limit = $objItems->count();

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrArticles = [];

        while ($objItems->next()) {
            /** @var NewsModel $objArticle */
            $objArticle = $objItems->current();

            $arrArticles[] = $this->parseItem($objArticle, $blnAddArchive, ((1 === ++$count) ? ' first' : '').(($count === $limit) ? ' last' : '').((0 === ($count % 2)) ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Parse an item and return it as string.
     *
     * @param NewsModel $objItem
     * @param bool      $blnAddArchive
     * @param string    $strClass
     * @param int       $intCount
     *
     * @return string
     */
    protected function parseItem($objItem, $blnAddArchive = false, $strClass = '', $intCount = 0)
    {
        $objTemplate = new \FrontendTemplate($this->wemaudiotracks_template);
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
        if ($objItem->picture && $objFile = \FilesModel::findByUuid($objItem->picture)) {
            $objTemplate->picture = \Image::get($objFile->path, 300, 300);
        }

        // Fetch the audio file
        if ($objFile = \FilesModel::findByUuid($objItem->audio)) {
            $objTemplate->audio = $objFile->path;
            
            // Use library to get file duration
            $mp3file = new MP3File($objFile->path);
            $duration = $mp3file->getDurationEstimate();

            $objTemplate->duration = ($duration > 3600) ?
                sprintf('%sh%s min', $duration / 3600, $duration / 60 % 60) : 
                sprintf('%s:%s min', $duration / 60 % 60, $duration % 60)
            ;
            $objTemplate->durationRaw = $duration;
        } else {
            $objTemplate->audio = null;
        }

        // Retrieve the feedback from this IP
        $objTemplate->liked = 0 < Feedback::countItems(['pid' => $objItem->id, 'ip' => \Environment::get('ip')]);
        $objTemplate->nbLikes = Feedback::countItems(['pid' => $objItem->id]) ?: 0;

        // Retrieve user session if exists
        $objSession = Session::findItems(['pid' => $objItem->id, 'ip' => \Environment::get('ip')], 1);

        if ($objSession) {
            $objTemplate->session = [
                "currentTime" => $objSession->currentTime,
                "volume" => $objSession->volume,
                "complete" => 1 === (int) $objSession->complete,
            ];
        }

        // Hook system to customize item parsing
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'] as $callback) {
                static::importStatic($callback[0])->{$callback[1]}($objTemplate, $objItem, $this);
            }
        }

        return $objTemplate->parse();
    }

    public function updateAudiotrackFeedback($pid, $like = true)
    {
        $strIp = \Environment::get('ip');
        
        if (false === $like && $objFeedback = Feedback::findItems(['pid' => $pid, 'ip' => $strIp], 1)) {
            $objFeedback->delete();
        }

        if(true === $like && 0 === Feedback::countItems(['pid' => $pid, 'ip' => $strIp])) {
            $objFeedback = new Feedback();
            $objFeedback->tstamp = time();
            $objFeedback->createdAt = time();
            $objFeedback->pid = $pid;
            $objFeedback->ip = $strIp;
            $objFeedback->save();
        }
    }


    public function updateAudiotrackSession($pid, $currentTime = 0, $volume = 1, $markAsComplete = false)
    {
        $strIp = \Environment::get('ip');
        $objSession = Session::findItems(['pid' => $pid, 'ip' => $strIp], 1);

        if (!$objSession) {
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
}
