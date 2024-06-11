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

namespace WEM\AudioTracksBundle\Model;

use Exception;

use WEM\UtilsBundle\Model\Model;

/**
 * Reads and writes items.
 */
class AudioTrack extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_wem_audiotrack';

    /**
     * Find items, depends on the arguments.
     *
     * @param array $arrConfig  [Request Config]
     * @param int   $intLimit   [Query Limit]
     * @param int   $intOffset  [Query Offset]
     * @param array $arrOptions [Query Options]
     *
     * @return Collection
     */
    public static function findItems($arrConfig = [], $intLimit = 0, $intOffset = 0, array $arrOptions = [])
    {
        $t = static::$strTable;
        // Catch sorting by subtable
        if ($arrOptions['order'] && false !== strpos($arrOptions['order'], 'mostLiked')) {
            $arrOptions['select'] = $t . '.*, COUNT(twaf.id) AS nbLikes';
            $arrOptions['join'][] = sprintf('LEFT JOIN tl_wem_audiotrack_feedback twaf on %s.id = twaf.pid', $t);
            $arrOptions['group'] = $t . '.id';

            $arrOptions['order'] = 'DESC' === substr($arrOptions['order'], -4, 4) ? 'nbLikes DESC' : 'nbLikes ASC';
        }
        return parent::findItems($arrConfig, $intLimit, $intOffset, $arrOptions);
    }

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
        $arrColumns = [];
        $t = static::$strTable;
        switch ($strField) {
            case 'pid':
                if (!$varValue || !\is_array($varValue)) {
                    $varValue = [$varValue];
                }

                $arrColumns[] = sprintf(sprintf('%s.pid IN(\'%%s\')', $t), implode("','", $varValue));
            break;

            case 'tags':
                $arrColumns[] = sprintf(sprintf('%s.id IN(SELECT twat.pid FROM tl_wem_audiotrack_tag twat WHERE twat.tag IN(\'%%s\'))', $t), implode("','", $varValue));
            break;

            case 'search':
                $strKeywords = implode('|', $varValue);
                $arrColumns[] = sprintf('(%s.title REGEXP \'%s\' OR %s.description REGEXP \'%s\')', $t, $strKeywords, $t, $strKeywords);
            break;

            // Load parent
            default:
                $arrColumns = array_merge($arrColumns, parent::formatStatement($strField, $varValue, $strOperator));
        }
        return $arrColumns;
    }
}
