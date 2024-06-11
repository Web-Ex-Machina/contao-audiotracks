<?php

declare(strict_types=1);

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
use Contao\System;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\UtilsBundle\Classes\StringUtil;

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
        $scope = System::getContainer()->get('wem.scope_matcher');
        if ($scope->isBackend()) {
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

    /**
     * Compile list.
     * @throws \Exception
     */
    protected function compile()
    {
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

        $objItems = AudioTrack::findItems($this->config, ($this->limit ?: 0), ($this->offset ?: 0));

        // Add the articles
        if (null !== $objItems) {
            $this->Template->articles = $this->parseItems($objItems);
        }

        $this->Template->moduleId = $this->id;
    }

    /**
     * Retrieve list filters.
     *
     * @return array|null Array of available filters, parsed
     * @throws \Exception
     */
    protected function buildFilters(): ?array
    {
        if (!$this->wemaudiotracks_filters) {
            exit();
        }

        // Retrieve and format dropdowns filters
        $filters = \Contao\StringUtil::deserialize($this->wemaudiotracks_filters);
        if (\is_array($filters) && !empty($filters)) {
            foreach ($filters as $f) {
                $filter = [
                    'type' => $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['inputType'],
                    'name' => $f,
                    'label' => $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['label'][0] ?: $GLOBALS['TL_LANG']['tl_wem_job'][$f][0],
                    'value' => Input::get($f) ?: '',
                    'options' => [],
                    'multiple' => (bool)$GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['eval']['multiple'],
                ];

                switch ($GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['inputType']) {
                    case 'select':
                        $options = [];
                        if (\is_array($GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options_callback'])) {
                            $strClass = $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options_callback'][0];
                            $strMethod = $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options_callback'][1];

                            $this->import($strClass);
                            $options = $this->$strClass->$strMethod($this);
                        } elseif (\is_callable($GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options_callback'])) {
                            $options = $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options_callback']($this);
                        } elseif (\is_array($GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options'])) {
                            $options = $GLOBALS['TL_DCA']['tl_wem_job']['fields'][$f]['options'];
                        }
                        foreach ($options as $value => $label) {
                            $filter['options'][] = [
                                'value' => $value,
                                'label' => $label,
                                'selected' => (null !== Input::get($f) && (Input::get($f) === $value || (\is_array(Input::get($f)) && \in_array($value, Input::get($f))))),
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
        exit();
    }

    /**
     * Parse one or more items and return them as array.
     *
     * @param Collection $objItems
     * @param bool $blnAddArchive
     *
     * @return array
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
     * @param bool $blnAddArchive
     * @param string $strClass
     * @param int $intCount
     *
     * @return string
     */
    protected function parseItem(NewsModel $objItem, bool $blnAddArchive = false, string $strClass = '', int $intCount = 0): string
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
            $objTemplate->picture = Image::get($objFile->path, 300, 300);
        }

        // Fetch the audio file
        if ($objFile = FilesModel::findByUuid($objItem->audio)) {
            $objTemplate->audio = $objFile->path;
            $objTemplate->isImage = @is_array(getimagesize($objFile->path));
        } else {
            $objTemplate->audio = null;
        }

        return $objTemplate->parse();
    }
}
