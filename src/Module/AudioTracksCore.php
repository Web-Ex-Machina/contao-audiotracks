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

abstract class AudioTracksCore extends Module
{
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
        if ('remote' === $objItem->getRelated('pid')->type && $objItem->pictureRemoteUrl) {
            $objTemplate->picture =  $objItem->pictureRemoteUrl;
        } else {
            if ($objItem->picture && $objFile = FilesModel::findByUuid($objItem->picture)) {
                $objTemplate->picture =  \Image::get($objFile->path, 300, 300);
            }

            if ($objItem->picture_mobile && $objFile = FilesModel::findByUuid($objItem->picture_mobile)) {
                $objTemplate->picture_mobile = \Image::get($objFile->path, 300, 300);
            }
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
