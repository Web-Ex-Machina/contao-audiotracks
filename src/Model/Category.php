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

use Contao\System;
use Symfony\Component\Filesystem\Path;
use WEM\AudioTracksBundle\Classes\BaseUrl;
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
     * RSS Folder path.
     *
     * @var string
     */
    protected static $strRssFolder = 'share/audiotracks/rss/';

    /**
     * Generate URL for RSS, null if the category has no RSS filename.
     */
    public function getRssFeedUrl(): string|null
    {
        if (!$this->rssFilename) {
            return null;
        }

        return BaseUrl::get().static::$strRssFolder.$this->rssFilename;
    }

    /**
     * Generate the absolute path for RSS (inside the web directory, ie: public/),
     * null if the category has no RSS filename.
     */
    public function getRssFeedPath(): string|null
    {
        if (!$this->rssFilename) {
            return null;
        }

        return Path::join(System::getContainer()->getParameter('contao.web_dir'), static::$strRssFolder, $this->rssFilename);
    }
}
