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
 * What happened to the RSS feed of a category when it was generated.
 */
enum FeedGeneration: string
{
    // The feed was not generated: unknown category, or the feed is not enabled
    case Disabled = 'disabled';

    // The file was written
    case Written = 'written';

    // The file already had the right content, it was not touched
    case Unchanged = 'unchanged';
}
