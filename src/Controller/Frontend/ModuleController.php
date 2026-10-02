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
use Doctrine\DBAL\ArrayParameterType;
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
use Symfony\Component\HttpFoundation\Request;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

abstract class ModuleController extends AbstractFrontendModuleController
{
    protected ModuleModel $model;

    public function __construct(
        protected readonly SchemaOrgBuilder $schemaOrgBuilder,
    )
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

    /**
     * What the items need, loaded once (see preload()).
     *
     * @var array<int, Category>
     */
    private array $categories = [];

    /**
     * @var array<string, FilesModel|null> indexed by binary uuid
     */
    private array $files = [];

    /**
     * @var array<int, int> likes counter by item
     */
    private array $likes = [];

    private PageModel|false|null $targetPage = false;

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
        /** @var AudioTrack[] $items */
        $items = $objItems->getModels();
        $limit = \count($items);

        if ($limit < 1) {
            return [];
        }

        // Everything the items need is loaded with a few queries, not with a few queries per item
        $this->preload($items);

        $count = 0;
        $arrArticles = [];

        foreach ($items as $objItem) {
            $arrArticles[] = $this->parseItem($objItem, $blnAddArchive, ((1 === ++$count) ? ' first' : '').(($count === $limit) ? ' last' : '').((0 === ($count % 2)) ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Load, for a list of items, their categories, their files and their likes counters at once.
     * The getters below use it, and fall back on a query for what has not been preloaded (reader).
     *
     * @param AudioTrack[] $items
     */
    protected function preload(array $items): void
    {
        $ids = array_map(static fn (AudioTrack $item): int => (int) $item->id, $items);
        $pids = array_values(array_unique(array_map(static fn (AudioTrack $item): int => (int) $item->pid, $items)));

        // Categories
        $categories = Category::findMultipleByIds($pids);

        foreach ($categories ?? [] as $category) {
            $this->categories[(int) $category->id] = $category;
        }

        // Files: audio and pictures of the items
        $uuids = [];

        foreach ($items as $item) {
            foreach (['picture', 'picture_mobile', 'audio'] as $field) {
                if ($item->$field) {
                    $uuids[$item->$field] = $item->$field;
                }
            }
        }

        if ([] !== $uuids) {
            foreach (FilesModel::findMultipleByUuids(array_values($uuids)) ?? [] as $file) {
                $this->files[$file->uuid] = $file;
            }

            foreach ($uuids as $uuid) {
                $this->files[$uuid] ??= null;
            }
        }

        // Likes counters
        $this->likes = array_fill_keys($ids, 0);

        $counters = System::getContainer()->get('database_connection')->fetchAllKeyValue(
            'SELECT pid, COUNT(*) FROM tl_wem_audiotrack_feedback WHERE pid IN (?) GROUP BY pid',
            [$ids],
            [ArrayParameterType::INTEGER]
        );

        foreach ($counters as $pid => $counter) {
            $this->likes[(int) $pid] = (int) $counter;
        }
    }

    protected function getCategory(int $pid): ?Category
    {
        return $this->categories[$pid] ??= Category::findByPk($pid);
    }

    protected function getFile(mixed $uuid): ?FilesModel
    {
        if (!$uuid) {
            return null;
        }

        if (!\array_key_exists($uuid, $this->files)) {
            $this->files[$uuid] = FilesModel::findByUuid($uuid);
        }

        return $this->files[$uuid];
    }

    protected function getLikes(int $id): int
    {
        return $this->likes[$id] ??= Feedback::countItems(['pid' => $id]);
    }

    /**
     * The page the items link to, found once for all the items.
     */
    protected function getTargetPage(): ?PageModel
    {
        if (false === $this->targetPage) {
            $this->targetPage = $this->model->jumpTo ? PageModel::findWithDetails($this->model->jumpTo) : null;
        }

        return $this->targetPage;
    }

    /**
     * Resolve the item template identifier (e.g. "audiotracks/item/full").
     * Falls back to the default one if the template of the module does not exist (anymore).
     */
    protected function getItemTemplate(): string
    {
        $template = (string) $this->model->wemaudiotracks_template;

        if ('' === $template || !$this->container->get('twig')->getLoader()->exists('@Contao/'.$template.'.html.twig')) {
            return 'audiotracks/item';
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
        $objCategory = $this->getCategory((int) $objItem->pid);

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

        $isRemote = 'remote' === $objCategory?->type;

        // Retrieve and parse the pictures
        if ($isRemote && $objItem->pictureRemoteUrl) {
            $arrData['picture'] = $objItem->pictureRemoteUrl;
            $arrData['pictureMobile'] = null;
        } else {
            $arrData['picture'] = $this->getFile($objItem->picture)?->path;
            $arrData['pictureMobile'] = $this->getFile($objItem->picture_mobile)?->path;
        }

        // If item is from remote, file path is different
        $arrData['audio'] = $isRemote ? $objItem->audioRemoteUrl : $this->getFile($objItem->audio)?->path;

        // The duration is computed when the item is saved (and by a migration for the old ones), never during a page view
        $arrData['duration'] = ($objItem->duration > 3600) ?
            sprintf('%s h %s%s min', number_format($objItem->duration / 3600), $objItem->duration / 60 % 60 < 10 ? '0' : '', $objItem->duration / 60 % 60) :
            sprintf('%s min %s%s s', $objItem->duration / 60 % 60, $objItem->duration % 60 < 10 ? '0' : '', $objItem->duration % 60)
        ;
        $arrData['durationRaw'] = $objItem->duration;

        // Nothing that depends on the visitor (liked, listening session) is rendered here, the page can be cached:
        // the player gets it from the StateController. The likes counter is the same for everybody.
        $arrData['nbLikes'] = $this->getLikes((int) $objItem->id);

        // Let template know if we can download the item
        $arrData['canDownload'] = (bool) $this->model->wemaudiotracks_canDownload;

        if ($objTarget = $this->getTargetPage()) {
            // Items without alias (imported before the alias generation) are reachable with their id
            $arrData['jumpTo'] = $objTarget->getFrontendUrl('/'.($objItem->alias ?: $objItem->id));
        }

        // schema.org JSON-LD, given to the template with add_schema_org()
        $arrData['schemaOrg'] = $objCategory ? $this->schemaOrgBuilder->buildEpisode($objItem, $objCategory, $arrData) : null;

        // Hook system to customize item parsing
        if (isset($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM']) && \is_array($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'])) {
            foreach ($GLOBALS['TL_HOOKS']['WEMAUDIOTRACKSPARSEITEM'] as $callback) {
                $arrData = System::importStatic($callback[0])->{$callback[1]}($arrData, $objItem, $this);
            }
        }

        return $arrData;
    }
}
