<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\DataContainer;

class AudioTrackTagContainer extends \Backend
{
    /**
     * Format items list.
     *
     * @param array $r
     *
     * @return string
     */
    public function listItems($r)
    {
        return sprintf(
            '%s',
            $r['tag']
        );
    }
}
