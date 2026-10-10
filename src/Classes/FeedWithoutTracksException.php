<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

/**
 * The RSS feed of a category cannot be generated: it has no published track.
 */
class FeedWithoutTracksException extends \RuntimeException
{
}
