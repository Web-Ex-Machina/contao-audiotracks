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
use Contao\Message;
use WEM\AudioTracksBundle\Classes\ClientIdentifier;

/**
 * Tells, in the lists of the listening sessions and of the likes, what the
 * settings of the bundle do to this data: how long it is kept, and how the
 * visitors are stored. They are set in config/config.yaml, not in the back end,
 * so the people who look at the data would not know otherwise.
 */
class TrackingDataNoticeContainer
{
    public function __construct(
        private readonly int $retentionMonths,
        private readonly string $identifierMode,
    ) {
    }

    #[AsCallback(table: 'tl_wem_audiotrack_session', target: 'config.onload')]
    #[AsCallback(table: 'tl_wem_audiotrack_feedback', target: 'config.onload')]
    public function showNotice(): void
    {
        $lang = $GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS'] ?? [];

        Message::addInfo(
            $this->retentionMonths > 0
                ? \sprintf($lang['retentionMonths'] ?? '%d', $this->retentionMonths)
                : ($lang['retentionNone'] ?? ''),
        );
        Message::addInfo(ClientIdentifier::MODE_HMAC === $this->identifierMode ? ($lang['identifierHmac'] ?? '') : ($lang['identifierEncryption'] ?? ''));
    }
}
