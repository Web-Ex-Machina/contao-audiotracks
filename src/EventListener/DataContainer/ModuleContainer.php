<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\Database;

class ModuleContainer
{
    public function __construct(private readonly FinderFactory $finderFactory)
    {
    }

    /**
     * Return all templates as array.
     */
    #[AsCallback(table: 'tl_module', target: 'fields.wemaudiotracks_template.options')]
    public function getTemplates(): array
    {
        return $this->finderFactory->create()
            ->identifier('audiotracks/item')
            ->extension('html.twig')
            ->withVariants()
            ->excludePartials()
            ->asTemplateOptions()
        ;
    }

    /**
     * Return all categories as array.
     */
    #[AsCallback(table: 'tl_module', target: 'fields.wemaudiotracks_categories.options')]
    public function getCategories(): array
    {
        $arrItems = [];
        $objItems = Database::getInstance()->execute('SELECT id, title FROM tl_wem_audiotrack_category ORDER BY title');

        if (!$objItems || 0 === $objItems->count()) {
            return $arrItems;
        }

        while ($objItems->next()) {
            $arrItems[$objItems->id] = $objItems->title;
        }

        return $arrItems;
    }

    /**
     * Return all available filters.
     */
    #[AsCallback(table: 'tl_module', target: 'fields.wemaudiotracks_filters.options')]
    public function getFiltersOptions(): array
    {
        Controller::loadDataContainer('tl_wem_audiotrack');
        $fields = [];

        foreach ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'] as $k => $v) {
            if (!empty($v['eval']) && true === $v['eval']['isAvailableForFilters']) {
                $fields[$k] = $v['label'][0] ?: $k;
            }
        }

        return $fields;
    }
}
