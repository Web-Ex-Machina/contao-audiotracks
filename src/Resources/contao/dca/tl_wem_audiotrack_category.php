<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_wem_audiotrack_category'] = [
    // Config
    'config' => [
        'dataContainer' => 'Table',
        'ctable' => ['tl_wem_audiotrack'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'onload_callback' => [
            [WEM\AudioTracksBundle\DataContainer\CategoryContainer::class, 'displayRssUrl']
        ],
        'onsubmit_callback' => [
            [WEM\AudioTracksBundle\DataContainer\CategoryContainer::class, 'generateRssFeed']
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'alias' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['title'],
            'flag' => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s',
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
                'href' => 'table=tl_wem_audiotrack',
                'icon' => 'edit.gif',
            ],
            'header' => [
                'href' => 'act=edit',
                'icon' => 'header.gif',
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
        ],
    ],

    // Palettes
    'palettes' => [
        '__selector__' => ['rss'],
        'default' => '
            {title_legend},title,alias,description;
            {picture_legend},picture,pictureAlt,pictureTitle,pictureSize;
            {settings_legend},type,language,categories,tags,explicit,complete;
            {author_legend},authors;
            {rss_legend},rss;
        ',
    ],

    'subpalettes' => [
        'rss' => 'rssType,rssFilename,rssLink,rssHub,rssCopyright,rssDescription'
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
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
        'alias' => [
            'exclude' => true,
            'inputType' => 'text',
            'search' => true,
            'eval' => ['rgxp' => 'alias', 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'save_callback' => [
                [WEM\AudioTracksBundle\DataContainer\CategoryContainer::class, 'generateAlias'],
            ],
            'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
        'categories' => [
            'exclude' => true,
            'inputType' => 'listWizard',
            'eval' => ['tl_class'=>'clr', 'tl_class' => 'clr long'],
            'sql' => "blob NULL"
        ],
        'description' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql' => 'mediumtext NULL',
        ],
        'picture' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['filesOnly' => true, 'fieldType' => 'radio', 'tl_class' => 'clr', 'extensions' => Contao\Config::get('validImageTypes')],
            'sql' => 'binary(16) NULL',
        ],
        'pictureAlt' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('maxlength'=>255, 'tl_class'=>'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'pictureTitle' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => array('maxlength'=>255, 'tl_class'=>'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'pictureSize' => [
            'exclude' => true,
            'inputType' => 'imageSize',
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval' => array('rgxp'=>'natural', 'includeBlankOption'=>true, 'nospace'=>true, 'helpwizard'=>true, 'tl_class'=>'w50'),
            'sql' => "varchar(128) COLLATE ascii_bin NOT NULL default ''"
        ],
        'type' => [
            'exclude' => true,
            'inputType' => 'select',
            'eval'=> array('tl_class'=>'w50'),
            'options' => ['episodic', 'serial'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack_category']['type'],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'language' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'eval'=> array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
            'options_callback' => static function () {
                return System::getContainer()->get('contao.intl.locales')->getLocales(null, false);
            },
            'sql' => "varchar(64) NOT NULL default ''"
        ],
        'explicit' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'complete' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true, 'tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'tags' => [
            'exclude' => true,
            'inputType' => 'listWizard',
            'eval' => ['tl_class'=>'clr'],
            'sql' => "blob NULL"
        ],
        'authors' => [
            'exclude' => true,
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'columnFields' => [
                    'name' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack_category']['authors']['name'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['mandatory' => true],
                    ],
                    'email' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack_category']['authors']['email'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['rgxp' => 'email', 'mandatory' => true],
                    ],
                    'uri' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack_category']['authors']['uri'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['rgxp' => 'url', 'mandatory' => true],
                    ],
                ]
            ],
            'sql' => 'blob NULL',
        ],
        'rss' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true, 'submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'rssType' => [
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['rss', 'atom'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wem_audiotrack_category']['rssType'],
            'eval' => ['tl_class'=>'clr'],
            'sql' => "varchar(16) NOT NULL default ''"
        ],
        'rssFilename' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class'=>'w50'],
            'sql' => "text NULL"
        ],
        'rssLink' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'rssDescription' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql' => 'mediumtext NULL',
        ],
        'rssHub' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'rssCopyright' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''"
        ],    
    ],
];