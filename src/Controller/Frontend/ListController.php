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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\Pagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\UtilsBundle\Classes\StringUtil;
use Contao\System;

#[AsFrontendModule(
    ListController::TYPE, 
    category: 'wem_audiotracks',
)]
class ListController extends ModuleController
{
    /**
     * Module name
     */
    public const TYPE = 'wem_audiotracks_list';

    /**
     * List limit.
     */
    protected ?int $limit = 0;

    /**
     * List offset.
     */
    protected int $offset = 0;

    /**
     * Current page.
     */
    protected int $page = 1;

    /**
     * Generate module response
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Return empty Response if there is an auto_item
        if ($request->query->has('auto_item')) {
            return new Response('');
        }

        $this->pids = StringUtil::deserialize($model->wemaudiotracks_categories, true);

        if (empty($this->pids)) {
            return new Response('');
        }

        $this->model = $model;

        $this->limit = $model->numberOfItems > 0 ? $model->numberOfItems : null;
        $this->offset = (int) $model->skipFirst;
        $this->options = ['order' => 'date DESC'];

        $template->articles = [];
        $template->empty = $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['empty'];

        // Build config
        $this->config = ['pid' => $this->pids, 'published' => 1];

         // Retrieve filters
        $this->buildFilters();
        $template->filters = $this->filters;
        $template->add_filters = (bool) $model->wemaudiotracks_addFilters;

        // Retrieve feed links
        if ($model->wemaudiotracks_links) {
            $types = StringUtil::deserialize($model->wemaudiotracks_links, true);
            $template->links = $this->getLinks($types);
        }

        // Get the total number of items
        $intTotal = AudioTrack::countItems($this->config);

        if ($intTotal < 1) {
            return $template->getResponse();
        }

        $this->page = 1;
        $total = $intTotal - $model->offset;

        // Split the results
        if ($model->perPage > 0 && (!isset($model->limit) || $model->numberOfItems > $model->perPage)) {
            // Adjust the overall limit
            if (isset($this->limit)) {
                $total = min($model->limit, $total);
            }

            // Get the current page
            $id = 'page_n'.$model->id;
            $this->page = (int) (Input::get($id) ?? 1);

            // Do not index or cache the page if the page number is outside the range
            if ($this->page < 1 || $this->page > max(ceil($total / $model->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
            }

            // Set limit and offset
            $this->limit = $model->perPage;
            $this->offset += (max($this->page, 1) - 1) * $model->perPage;
            $skip = (int) $model->skipFirst;

            // Overall limit
            if ($model->offset + $model->limit > $total + $skip) {
                $model->limit = $total + $skip - $model->offset;
            }

            // Add the pagination menu
            $objPagination = new Pagination($total, $model->perPage, Config::get('maxPaginationLinks'), $id);
            $template->pagination = $objPagination->generate("\n  ");
        }

        $objItems = AudioTrack::findItems(
            $this->config, 
            ($this->limit !== null && $this->limit !== 0 ? $this->limit : 0), 
            ($this->offset), 
            $this->options
        );

        // Add the articles
        if ($objItems instanceof Collection) {
            $items = $this->parseItems($objItems);

            // The graph keeps one node per schema.org type, so a list is described with a single ItemList
            $listItems = [];
            foreach ($items as $index => $item) {
                if (!empty($item['schemaOrg'])) {
                    $listItems[] = ['@type' => 'ListItem', 'position' => $this->offset + $index + 1, 'item' => $item['schemaOrg']];
                }

                unset($items[$index]['schemaOrg']);
            }

            if ($listItems) {
                $template->schema_org = ['@type' => 'ItemList', 'itemListElement' => $listItems];
            }

            $template->items = $items;
            $template->item_template = $this->getItemTemplate();
        }


        return $template->getResponse();
    }

    protected function getLinks(array $types): array
    {
        $links = [];
        
        foreach ($this->pids as $pid) {
            // Get the model
            $objCategory = Category::findByPk($pid);

            if (!array_key_exists($pid, $links)) {
                $links[$pid] = $objCategory->row();
                $links[$pid]['links'] = [];
            }

            foreach ($types as $t) {
                switch ($t) {
                    case 'rss':
                        // No link if the category has no feed
                        if (!$objCategory->rss || !($href = $objCategory->getRssFeedUrl())) {
                            break;
                        }

                        $links[$pid]['links'][$t] = [
                            'href' => $href,
                            'title' => $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['links'][$t],
                            'icon' => '<i class="fa-solid fa-square-rss"></i>'
                        ];
                    break;

                    default:
                        // Nuthin'
                }
            }
        }

        return $links;
    }
}
