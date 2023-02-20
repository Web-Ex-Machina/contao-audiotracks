<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Model;

/**
 * Reads and writes items.
 */
class AudioTrack extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_wem_audiotrack';

    /**
     * Generic statements format.
     *
     * @param string $strField    [Column to format]
     * @param mixed  $varValue    [Value to use]
     * @param string $strOperator [Operator to use, default "="]
     *
     * @return array
     */
    public static function formatStatement($strField, $varValue, $strOperator = '=')
    {
        try {
            $arrColumns = [];
            $t = static::$strTable;

            switch ($strField) {
                case 'pid':
                    if (!$varValue || !\is_array($varValue)) {
                        $varValue = [$varValue];
                    }

                    $arrColumns[] = sprintf("$t.pid IN('%s')", implode("','", $varValue));
                break;

                case 'tags':
                    $arrColumns[] = sprintf("$t.id IN(SELECT twat.pid FROM tl_wem_audiotrack_tag twat WHERE twat.tag IN('%s'))", implode("','", $varValue));
                break;

                case 'search':
                    
                break;

                // Load parent
                default:
                    $arrColumns = array_merge($arrColumns, parent::formatStatement($strField, $varValue, $strOperator));
            }

            return $arrColumns;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
