<?php

declare(strict_types=1);
use WEM\AudioTracksBundle\Model;

/*
 * Back end modules
 */
Contao\ArrayUtil::arrayInsert($GLOBALS['BE_MOD']['content'], count($GLOBALS['BE_MOD']['content']), [
    'wemaudiotracks' => [
        'tables' => ['tl_wem_audiotrack_category', 'tl_wem_audiotrack', 'tl_wem_audiotrack_feedback'],
    ],
]);

/*
 * Front end modules
 */
Contao\ArrayUtil::arrayInsert($GLOBALS['FE_MOD'], 2, [
    'wemaudiotracks' => [
        'wemaudiotrackslist' => WEM\AudioTracksBundle\Module\AudioTracksList::class,
    ],
]);

// Models
$GLOBALS['TL_MODELS'][Model\AudioTrack::getTable()] = Model\AudioTrack::class;
$GLOBALS['TL_MODELS'][Model\Category::getTable()] = Model\Category::class;
$GLOBALS['TL_MODELS'][Model\Feedback::getTable()] = Model\Feedback::class;
