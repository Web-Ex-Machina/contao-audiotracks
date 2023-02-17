<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Model;

/**
 * Reads and writes items.
 */
class Session extends \WEM\UtilsBundle\Model\Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_wem_audiotrack_session';
}