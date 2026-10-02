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
 * The period during which a track is published: from its start date to its stop
 * date (both are optional).
 */
final class PublicationPeriod
{
    /**
     * A track that stops before it starts, or at the very moment it starts, would
     * never be displayed.
     *
     * @param int $start Timestamp, 0 if there is none
     * @param int $stop  Timestamp, 0 if there is none
     */
    public static function isValid(int $start, int $stop): bool
    {
        return $start < 1 || $stop < 1 || $stop > $start;
    }
}
