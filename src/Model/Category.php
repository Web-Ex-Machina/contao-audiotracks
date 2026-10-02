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

use Contao\Environment;
use WEM\UtilsBundle\Model\Model;

/**
 * Reads and writes items.
 */
class Category extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_wem_audiotrack_category';

    /**
     * RSS Folder path
     * 
     * @var string
     */
    protected static $strRssFolder = 'share/audiotracks/rss/';

    /**
     * Generate URL for RSS
     */
    public function getRssFeedUrl(): string
    {
        if (!$this->rssFilename) {
            return $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssNoFilename'];
        }

        return Environment::get('base') . static::$strRssFolder . $this->rssFilename;
    }

    /**
     * Generate path for RSS
     */
    public function getRssFeedPath(): string
    {
        if (!$this->rssFilename) {
            return $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['rssNoFilename'];
        }

        return 'web/' . static::$strRssFolder . $this->rssFilename;
    }
}
