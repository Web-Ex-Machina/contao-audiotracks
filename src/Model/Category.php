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
    protected static $strRssFolder = 'bundles/audiotracks/rss/';

    public function getRssFeedUrl()
    {
        if (!$this->rssFilename) {
            throw new \Exception("Cannot generate RSS Feed url as category does not have a RSS filename setup");
        }

        return Environment::get('base') . static::$strRssFolder . $this->rssFilename;
    }
}
