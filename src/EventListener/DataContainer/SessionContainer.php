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

namespace WEM\AudioTracksBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

class SessionContainer
{
    /**
     * Format items list.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_session', target: 'list.sorting.child_record')]
    public function listItems(array $r): string
    {
        return sprintf(
            '%s',
            $r['ip']
        );
    }
}
