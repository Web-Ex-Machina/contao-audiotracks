<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

class FeedbackContainer
{
    /**
     * Format items list.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_feedback', target: 'list.sorting.child_record')]
    public function listItems(array $r): string
    {
        return substr((string) $r['ip'], 0, 12).'…';
    }
}
