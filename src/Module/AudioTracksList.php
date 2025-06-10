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

class AudioTracksList extends AudioTracksCore
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

        // Hide if there is an auto_item
        if (Input::get('auto_item')) {
            return '';
        }

        $this->loadDatacontainer('tl_wem_audiotrack');
        $this->loadLanguageFile('tl_wem_audiotrack');

        $this->pids = StringUtil::deserialize($this->wemaudiotracks_categories);

        // Return if there are no archives
        if (empty($this->pids) || !\is_array($this->pids)) {
            return '';
        }

        // Check if we must sync remote feeds
        foreach ($this->pids as $id) {
            $this->syncFeedFromRemote($id);
        }

        return parent::generate();
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
}
