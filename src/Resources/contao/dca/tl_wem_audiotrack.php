<?php

declare(strict_types=1);

use Contao\Config;
use Contao\DataContainer;
use Contao\DC_Table;
use WEM\AudioTracksBundle\DataContainer\AudioTrackContainer;

$GLOBALS['TL_DCA']['tl_wem_audiotrack'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_wem_audiotrack_category',
        'ctable' => ['tl_wem_audiotrack_feedback', 'tl_wem_audiotrack_tag', 'tl_wem_audiotrack_session'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'onsubmit_callback' => [
            [AudioTrackContainer::class, 'generateRssFeed']
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['date ASC'],
            'headerFields' => ['title', 'tags'],
            'panelLayout' => 'filter;sort,search,limit',
            'child_record_callback' => [AudioTrackContainer::class, 'listItems'],
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
            'toggle' => [
                'icon' => 'visible.svg',
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => [AudioTrackContainer::class, 'toggleIcon'],
                'showInHeader' => true,
            ],
            'feedbacks' => [
                'href' => 'table=tl_wem_audiotrack_feedback',
                'icon' => 'member.gif',
            ],
            'sessions' => [
                'href' => 'table=tl_wem_audiotrack_session',
                'icon' => 'su.gif',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '
            {title_legend},title,date,season,episode,audio,type,duration;
            {content_legend},description,explicit,tags;
            {picture_legend},picture,picture_mobile,pictureText;
            {author_legend},authors;
            {publish_legend},published,start,stop
        ',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'pid' => [
            'foreignKey' => 'tl_wem_audiotrack_category.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
        ],
        'createdAt' => [
            'default' => time(),
            'flag' => 8,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'maxlength' => 255],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'date' => [
            'exclude' => true,
            'inputType' => 'text',
            'flag' => 8,
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'season' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'maxlength' => 255],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'episode' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'maxlength' => 255],
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        'audio' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'tl_class' => 'clr', 'extensions' => 'mp3,ogg,wav', 'mandatory'=>true],
            'sql' => 'binary(16) NULL',
        ],
        'type' => [
            'exclude' => true,
            'inputType' => 'select',
            'eval'=> array('tl_class'=>'w50', 'mandatory' => true),
            'options' => ['full', 'trailer', 'bonus'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack']['type'],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'duration' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50', 'rgxp' => 'digit'],
            'save_callback' => [
                [AudioTrackContainer::class, 'retrieveAudioTrackDuration']
            ],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'description' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql' => 'mediumtext NULL',
        ],
        'explicit' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'tags' => [
            'exclude' => true,
            'flag' => 1,
            'inputType' => 'select',
            'options_callback' => [AudioTrackContainer::class, 'getTags'],
            'save_callback' => [
                [AudioTrackContainer::class, 'syncAudioTrackTagsPivotTable']
            ],
            'eval' => ['doNotCopy' => true, 'chosen' => true, 'includeBlankOption' => true, 'multiple' => true, 'tl_class' => 'w50', 'isAvailableForFilters'=>true],
            'sql' => "blob NULL",
        ],
        'picture' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'tl_class' => 'clr', 'extensions' => Config::get('validImageTypes')],
            'sql' => 'binary(16) NULL',
        ],
        'picture_mobile' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'tl_class' => 'clr', 'extensions' => Config::get('validImageTypes')],
            'sql' => 'binary(16) NULL',
        ],
        'pictureText' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql' => 'mediumtext NULL',
        ],
        'authors' => [
            'exclude' => true,
            'inputType' => 'multiColumnWizard',
            'load_callback' => [
                [AudioTrackContainer::class, 'getParentValue'],
            ],
            'eval' => [
                'columnFields' => [
                    'name' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack']['authors']['name'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['mandatory' => true],
                    ],
                    'email' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack']['authors']['email'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['rgxp' => 'email', 'mandatory' => true],
                    ],
                    'uri' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack']['authors']['uri'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['rgxp' => 'url', 'mandatory' => true],
                    ],
                ]
            ],
            'sql' => 'blob NULL',
        ],
        'published' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'start' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'stop' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
    ],
];
