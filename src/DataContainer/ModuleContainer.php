<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\DataContainer;

class ModuleContainer extends \Backend
{
    /**
     * Return all templates as array.
     *
     * @return array
     */
    public function getTemplates()
    {
        return $this->getTemplateGroup('wemaudiotrack_');
    }

    /**
     * Return all categories as array.
     *
     * @return array
     */
    public function getCategories()
    {
        $arrItems = [];
        $objItems = $this->Database->execute('SELECT id, title FROM tl_wem_audiotrack_category ORDER BY title');

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
     *
     * @return array
     */
    public function getFiltersOptions()
    {
        $this->loadDataContainer('tl_wem_audiotrack');
        $fields = [];

        foreach ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields'] as $k => $v) {
            if (!empty($v['eval']) && true === $v['eval']['isAvailableForFilters']) {
                $fields[$k] = $v['label'][0] ?: $k;
            }
        }

        return $fields;
    }
}
