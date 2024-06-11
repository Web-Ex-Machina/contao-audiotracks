<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\DataContainer;

use Contao\Backend;

class FeedbackContainer extends Backend
{
    /**
     * Format items list.
     */
    public function listItems(array $r): string
    {
        return sprintf(
            '%s',
            $r['ip']
        );
    }
}
