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
use Contao\CoreBundle\Exception\PageOutOfRangeException;
use Contao\CoreBundle\Pagination\PaginationConfig;
use Contao\CoreBundle\Pagination\PaginationFactoryInterface;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WEM\AudioTracksBundle\Classes\SchemaOrgBuilder;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\UtilsBundle\Classes\StringUtil;

#[AsFrontendModule(
    ListController::TYPE,
    category: 'wem_audiotracks',
)]
class ListController extends ModuleController
{
    /**
     * Module name.
     */
    public const TYPE = 'wem_audiotracks_list';

    public function __construct(
        SchemaOrgBuilder $schemaOrgBuilder,
        private readonly PaginationFactoryInterface $paginationFactory,
    ) {
        parent::__construct($schemaOrgBuilder);
    }

    /**
     * Generate module response.
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Return empty Response if there is an auto_item
        if ($request->query->has('auto_item')) {
            return new Response('');
        }

        $this->pids = StringUtil::deserialize($model->wemaudiotracks_categories, true);

        if ([] === $this->pids) {
            return new Response('');
        }

        $this->model = $model;

        $this->options = ['order' => 'date DESC'];

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

        // Number of items to skip at the beginning, and maximum number of items
        // displayed (all the pages included)
        $skip = (int) $model->skipFirst;
        $maxItems = $model->numberOfItems > 0 ? (int) $model->numberOfItems : 0;

        // Total of the items of the list
        $total = max(0, AudioTrack::countItems($this->config) - $skip);

        if ($maxItems > 0) {
            $total = min($maxItems, $total);
        }

        if ($total < 1) {
            return $template->getResponse();
        }

        $offset = $skip;
        $length = $maxItems;
        $pagination = null;

        // Split the results in pages
        if ($model->perPage > 0) {
            try {
                // The parameter name is the same as before, the urls of the pages do not change
                $pagination = $this->paginationFactory->create(new PaginationConfig('page_n'.$model->id, $total, (int) $model->perPage));
            } catch (PageOutOfRangeException $e) {
                throw new PageNotFoundException('Page not found', previous: $e);
            }

            $offset += $pagination->getOffset();
            // The last page can be shorter, and the maximum number of items applies to the
            // whole list
            $length = min((int) $model->perPage, $total - $pagination->getOffset());
            $template->pagination = $pagination;
        }

        $objItems = AudioTrack::findItems($this->config, $length, $offset, $this->options);

        // Add the articles
        if ($objItems instanceof Collection) {
            $items = $this->parseItems($objItems);

            // The graph keeps one node per schema.org type, so a list is described with a
            // single ItemList
            $listItems = [];

            foreach ($items as $index => $item) {
                if (!empty($item['schemaOrg'])) {
                    $listItems[] = ['@type' => 'ListItem', 'position' => ($pagination?->getOffset() ?? 0) + $index + 1, 'item' => $item['schemaOrg']];
                }

                unset($items[$index]['schemaOrg']);
            }

            if ([] !== $listItems) {
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
            $objCategory = Category::findById($pid);

            if (!\array_key_exists($pid, $links)) {
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
                            'icon' => '<i class="fa-solid fa-square-rss"></i>',
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
