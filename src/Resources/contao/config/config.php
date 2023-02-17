<?php

declare(strict_types=1);

/*
 * Back end modules
 */
array_insert($GLOBALS['BE_MOD']['content'], count($GLOBALS['BE_MOD']['content']), [
    'wemaudiotracks' => [
        'tables' => ['tl_wem_audiotrack_category', 'tl_wem_audiotrack', 'tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_tag', 'tl_wem_audiotrack_session'],
    ],
]);

/*
 * Front end modules
 */
array_insert($GLOBALS['FE_MOD'], 2, [
    'wemaudiotracks' => [
        'wemaudiotrackslist' => WEM\AudioTracksBundle\Module\AudioTracksList::class,
    ],
]);

// Models
$GLOBALS['TL_MODELS'][\WEM\AudioTracksBundle\Model\AudioTrack::getTable()] = WEM\AudioTracksBundle\Model\AudioTrack::class;
$GLOBALS['TL_MODELS'][\WEM\AudioTracksBundle\Model\Category::getTable()] = WEM\AudioTracksBundle\Model\Category::class;
$GLOBALS['TL_MODELS'][\WEM\AudioTracksBundle\Model\Feedback::getTable()] = WEM\AudioTracksBundle\Model\Feedback::class;
$GLOBALS['TL_MODELS'][\WEM\AudioTracksBundle\Model\Session::getTable()] = WEM\AudioTracksBundle\Model\Session::class;
