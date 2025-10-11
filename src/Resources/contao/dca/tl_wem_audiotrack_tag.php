<?php

declare(strict_types=1);

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wem_audiotrack_tag'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_wem_audiotrack',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
            ],
        ],
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
            'foreignKey' => 'tl_wem_audiotrack.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
        ],
        'createdAt' => [
            'default' => time(),
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tag' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];
