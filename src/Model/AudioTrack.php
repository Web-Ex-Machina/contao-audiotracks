<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Model;

use Contao\Model\Collection;
use Contao\System;
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
     * @param array $arrConfig  Request Config
     * @param int   $intLimit   Query Limit
     * @param int   $intOffset  Query Offset
     * @param array $arrOptions Query Options
     *
     * @throws \Exception
     */
    public static function findItems(array $arrConfig = [], int $intLimit = 0, int $intOffset = 0, array $arrOptions = []): Collection|null
    {
        $t = static::$strTable;
        // Catch sorting by subtable
        if (!empty($arrOptions['order']) && str_contains($arrOptions['order'], 'mostLiked')) {
            $arrOptions['select'] = $t.'.*, COUNT(twaf.id) AS nbLikes';
            $arrOptions['join'][] = \sprintf('LEFT JOIN tl_wem_audiotrack_feedback twaf on %s.id = twaf.pid', $t);
            $arrOptions['group'] = $t.'.id';

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
     */
    public static function formatStatement(string $strField, $varValue, string $strOperator = '='): array
    {
        $arrColumns = [];
        $t = static::$strTable;

        switch ($strField) {
            case 'pid':
                if (!$varValue || !\is_array($varValue)) {
                    $varValue = [$varValue];
                }

                $arrColumns[] = \sprintf(\sprintf("%s.pid IN('%%s')", $t), implode("','", $varValue));
                break;

            // Respect the publication state and the start / stop dates (not in preview mode)
            case 'published':
                if (!$varValue || static::inPreviewMode()) {
                    break;
                }

                $time = time();
                $arrColumns[] = \sprintf("(%s.published = '1' AND (%s.start = '' OR %s.start <= %d) AND (%s.stop = '' OR %s.stop > %d))", $t, $t, $t, $time, $t, $t, $time);
                break;

            case 'tags':
                $arrColumns[] = \sprintf(\sprintf("%s.id IN(SELECT twat.pid FROM tl_wem_audiotrack_tag twat WHERE twat.tag IN('%%s'))", $t), implode("','", $varValue));
                break;

            case 'search':
                $strKeywords = implode('|', $varValue);
                $arrColumns[] = \sprintf("(%s.title REGEXP '%s' OR %s.description REGEXP '%s')", $t, $strKeywords, $t, $strKeywords);
                break;

            // Load parent
            default:
                $arrColumns = array_merge($arrColumns, parent::formatStatement($strField, $varValue, $strOperator));
        }

        return $arrColumns;
    }

    /**
     * Check if the item can be displayed in the frontend (published and within its
     * start / stop dates).
     */
    public function isPublished(): bool
    {
        if (static::inPreviewMode()) {
            return true;
        }

        $time = time();

        return '1' === (string) $this->published
            && (!$this->start || (int) $this->start <= $time)
            && (!$this->stop || (int) $this->stop > $time);
    }

    protected static function inPreviewMode(): bool
    {
        return System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
    }
}
