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

use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Feedback;

class FeedbackContainer
{
    public function __construct(private readonly CacheTagManager $cacheTagManager)
    {
    }

    /**
     * A like deleted in the back end changes the counter of the cached pages.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_feedback', target: 'config.ondelete')]
    public function invalidateLikes(DataContainer $dc): void
    {
        $feedback = $dc->id ? Feedback::findById($dc->id) : null;

        if (null !== $feedback) {
            $this->cacheTagManager->invalidateTags([AudioTrack::getLikesCacheTag((int) $feedback->pid)]);
        }
    }

    /**
     * Format items list.
     */
    #[AsCallback(table: 'tl_wem_audiotrack_feedback', target: 'list.sorting.child_record')]
    public function listItems(array $r): string
    {
        return substr((string) $r['ip'], 0, 12).'…';
    }
}
