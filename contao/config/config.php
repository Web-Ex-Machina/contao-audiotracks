<?php

declare(strict_types=1);

use Contao\ArrayUtil;
use WEM\AudioTracksBundle\Controller\Backend\SyncRemoteRssFeedController;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Category;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;
use WEM\AudioTracksBundle\Model\Tag;

/*
 * Back end modules
 */
ArrayUtil::arrayInsert(
    $GLOBALS['BE_MOD']['content'], 
    count($GLOBALS['BE_MOD']['content']), 
    [
        'wemaudiotracks' => [
            'tables' => ['tl_wem_audiotrack_category', 'tl_wem_audiotrack', 'tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_tag', 'tl_wem_audiotrack_session'],
            'syncRemoteRss' => [SyncRemoteRssFeedController::class, 'run'],
        ],
    ]
);

// Models
$GLOBALS['TL_MODELS'][AudioTrack::getTable()] = AudioTrack::class;
$GLOBALS['TL_MODELS'][Category::getTable()] = Category::class;
$GLOBALS['TL_MODELS'][Feedback::getTable()] = Feedback::class;
$GLOBALS['TL_MODELS'][Session::getTable()] = Session::class;
$GLOBALS['TL_MODELS'][Tag::getTable()] = Tag::class;

// File Usage bundle
$GLOBALS['FILE_USAGE']['tl_wem_audiotrack'] = [
    'labelColumn' => ['title'],
    'parent' => false,
    'href' => '/contao?do=wemaudiotracks&table=tl_wem_audiotrack&act=edit&id=%id%',
];
$GLOBALS['TL_LANG']['FILE_USAGE']['tl_wem_audiotrack'] = &$GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['FILE_USAGE']['tableName'];
