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

/*
 * Back end modules
 */
array_insert($GLOBALS['BE_MOD']['content'], \count($GLOBALS['BE_MOD']['content']), [
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
$GLOBALS['TL_MODELS'][\WEM\AudioTracksBundle\Model\Tag::getTable()] = WEM\AudioTracksBundle\Model\Tag::class;

// File Usage bundle
$GLOBALS['FILE_USAGE']['tl_wem_audiotrack'] = [
    'labelColumn' => ['title'],
    'parent' => false,
    'href' => '/contao?do=wemaudiotracks&table=tl_wem_audiotrack&act=edit&id=%id%',
];
$GLOBALS['TL_LANG']['FILE_USAGE']['tl_wem_audiotrack'] = &$GLOBALS['TL_LANG']['WEM']['AUDIOTRACKS']['FILE_USAGE']['tableName'];
