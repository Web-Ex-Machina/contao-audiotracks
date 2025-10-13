<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'wemaudiotracks_addFilters';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wemaudiotrackslist'] = '
    {title_legend},name,headline,type;
    {config_legend},wemaudiotracks_categories,wemaudiotracks_addFilters,wemaudiotracks_canDownload,wemaudiotracks_links;
    {list_legend},jumpTo,numberOfItems,skipFirst,perPage;
    {template_legend:collapsed},wemaudiotracks_template,customTpl;
    {expert_legend:collapsed},guests,cssID
';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wemaudiotracksreader'] = '
    {title_legend},name,headline,type;
    {config_legend},wemaudiotracks_canDownload,wemaudiotracks_links,overviewPage,customLabel;
    {template_legend:collapsed},wemaudiotracks_template,customTpl;
    {expert_legend:collapsed},guests,cssID
';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['wemaudiotracks_addFilters'] = 'wemaudiotracks_filters,wemaudiotracks_addSearch';

$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_categories'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => [WEM\AudioTracksBundle\DataContainer\ModuleContainer::class, 'getCategories'],
    'eval' => ['multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_addFilters'] = [
    'exclude' => true,
    'filter' => true,
    'flag' => \Contao\DataContainer::SORT_INITIAL_LETTER_ASC,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'doNotCopy' => true],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_filters'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [WEM\AudioTracksBundle\DataContainer\ModuleContainer::class, 'getFiltersOptions'],
    'eval' => ['chosen' => true, 'multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_addSearch'] = [
    'exclude' => true,
    'filter' => true,
    'flag' => \Contao\DataContainer::SORT_INITIAL_LETTER_ASC,
    'inputType' => 'checkbox',
    'eval' => ['doNotCopy' => true],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_canDownload'] = [
    'exclude' => true,
    'filter' => true,
    'flag' => \Contao\DataContainer::SORT_INITIAL_LETTER_ASC,
    'inputType' => 'checkbox',
    'eval' => ['doNotCopy' => true],
    'sql' => "char(1) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_links'] = [
    'exclude' => true,
    'inputType' => 'checkboxWizard',
    'options' => ['rss'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['wemaudiotracks_links'],
    'eval' => ['multiple' => true,],
    'sql' => 'blob NULL',
];
$GLOBALS['TL_DCA']['tl_module']['fields']['wemaudiotracks_template'] = [
    'default' => 'wemaudiotrack_default',
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [WEM\AudioTracksBundle\DataContainer\ModuleContainer::class, 'getTemplates'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];
