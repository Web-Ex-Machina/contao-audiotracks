<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\DataContainer;

use Exception;
use Contao\Backend;
use Contao\DataContainer;
use Contao\System;

class CategoryContainer extends Backend
{
    /**
     * Auto-generate an article alias if it has not been set yet.
     *
     * @throws Exception
     *
     * @return string
     */
    public function generateAlias($varValue, DataContainer $dc)
    {
        $aliasExists = function (string $alias) use ($dc): bool {
            return $this->Database->prepare('SELECT id FROM tl_wem_audiotrack_category WHERE alias=? AND id!=?')->execute($alias, $dc->id)->numRows > 0;
        };

        // Generate an alias if there is none
        if (!$varValue) {
            $varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->title, $dc->activeRecord->id, $aliasExists);
        } elseif ($aliasExists($varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }
}
