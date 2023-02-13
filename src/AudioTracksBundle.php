<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the bundle.
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */
class AudioTracksBundle extends Bundle
{
    public function syncAudioTrackTagsPivotTable($varValue, $dc)
    {
        $this->syncData($varValues, 'tl_wem_audiotrack_tag', $dc->id, 'pid', 'tag');

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
                    'DELETE FROM %s WHERE %s = %s AND %s NOT IN (%s)',
                    $strTable,
                    $strParentField,
                    $intParentId,
                    $strForeignField,
                    implode(',', array_map('intval', $varValues))
                )
            )->execute();
        }
    }
}
