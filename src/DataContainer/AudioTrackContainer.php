<?php

declare(strict_types=1);

/**
 * Geodata for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/contao-audiotracks
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\DataContainer;

use Contao\Backend;
use Contao\Database;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\Versions;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\UtilsBundle\Classes\StringUtil;
use WEM\UtilsBundle\Model\Model;

class AudioTrackContainer extends Backend
{
    /**
     * Format items list.
     *
     * @param array $r
     *
     * @return string
     */
    public function listItems($r)
    {
        return sprintf(
            '%s',
            $r['title']
        );
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (null !== Input::get('tid') && \strlen(Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), ('1' === Input::get('state')), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label, 'data-state="'.($row['published'] ? 1 : 0).'"').'</a> ';
    }

    /**
     * Disable/enable a job.
     *
     * @param int           $intId
     * @param bool          $blnVisible
     * @param DataContainer $dc
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null): void
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $intId; // see #8043
        }

        // Trigger the onload_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['config']['onload_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['config']['onload_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                } elseif (\is_callable($callback)) {
                    $callback($dc);
                }
            }
        }

        // Set the current record
        if ($dc) {
            $objRow = $this->Database->prepare('SELECT * FROM tl_wem_audiotrack WHERE id=?')
                                     ->limit(1)
                                     ->execute($intId)
            ;

            if ($objRow->numRows) {
                $dc->activeRecord = $objRow;
            }
        }

        $objVersions = new Versions('tl_wem_audiotrack', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, $dc);
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_wem_audiotrack SET tstamp=$time, published='".($blnVisible ? '1' : '')."' WHERE id=?")
                       ->execute($intId)
        ;

        if ($dc) {
            $dc->activeRecord->tstamp = $time;
            $dc->activeRecord->published = ($blnVisible ? '1' : '');
        }

        // Trigger the onsubmit_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_wem_audiotrack']['config']['onsubmit_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_wem_audiotrack']['config']['onsubmit_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                } elseif (\is_callable($callback)) {
                    $callback($dc);
                }
            }
        }

        $objVersions->create();
    }

    /**
     * Retrieve tags in the parent table.
     *
     * @return array ['tag1','tag2', ...]
     */
    public function getTags(?DataContainer $dc, ?array $arrPids = null): array
    {
        if (null !== $dc) {
            $objItem = AudioTrack::findByPk($dc->id);
            $objCategory = $objItem->getRelated('pid');

            if (!$objCategory->tags) {
                return [];
            }

            return deserialize($objCategory->tags);
        }

        if (null !== $arrPids) {
            $arrTags = [];
            foreach ($arrPids as $id) {
                $objCategory = Category::findByPk($id);

                if (!$objCategory || !$objCategory->tags) {
                    continue;
                }

                $arrTags = array_merge($arrTags, deserialize($objCategory->tags));
            }

            return array_unique($arrTags);
        }

        return [];
    }

    public function syncAudioTrackTagsPivotTable($varValue, $dc)
    {
        $this->syncData(deserialize($varValue), 'tl_wem_audiotrack_tag', $dc->id, 'pid', 'tag');

        return $varValue;
    }

    /**
     * Sync basic data between pivot tables.
     *
     * @param [array]  $varValues       [Usually an array of IDs]
     * @param [string] $strTable        [Table where to sync]
     * @param [int]    $intParentId     [Parent ID]
     * @param [string] $strParentField  [Parent Field]
     * @param [string] $strForeignField [Foreign field where to sync values]
     */
    public function syncData($varValues, $strTable, $intParentId, $strParentField, $strForeignField): void
    {
        // Found Model class
        $stdModel = Model::getClassFromTable($strTable);

        // step 1 - update existing recipients, add new ones
        foreach ($varValues as $id) {
            $objModel = $stdModel::findItems([$strParentField => $intParentId, $strForeignField => $id], 1);

            if (!$objModel) {
                $objModel = new $stdModel();
                $objModel->createdAt = time();
                $objModel->$strParentField = $intParentId;
                $objModel->$strForeignField = $id;
            }

            $objModel->tstamp = time();
            $objModel->save();
        }

        // step 2 - remove all ids not in $varValues
        if ($varValues) {
            Database::getInstance()->prepare(
                sprintf(
                    "DELETE FROM %s WHERE %s = %s AND %s NOT IN ('%s')",
                    $strTable,
                    $strParentField,
                    $intParentId,
                    $strForeignField,
                    implode("','", $varValues)
                )
            )->execute();
        }
    }
}
