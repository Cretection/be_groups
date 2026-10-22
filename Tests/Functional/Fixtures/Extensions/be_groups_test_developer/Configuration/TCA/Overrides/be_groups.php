<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// The permission fields of the extension, which the manual does not show
ExtensionManagementUtility::addTCAcolumns('be_groups', [
    'tx_myextension_permissions' => [
        'label' => 'be_groups_test_developer.db:be_groups.tx_myextension_permissions',
        'config' => [
            'type' => 'input',
        ],
    ],
    'tx_myextension_news_permissions' => [
        'label' => 'be_groups_test_developer.db:be_groups.tx_myextension_news_permissions',
        'config' => [
            'type' => 'number',
        ],
    ],
]);

// The examples of the chapter Developer as the manual shows them (ManualTest compares them)

ExtensionManagementUtility::addToAllTCAtypes(
    'be_groups',
    'tx_myextension_permissions',
    'acl,classic',
    'after:custom_options',
);

ExtensionManagementUtility::addTcaSelectItem(
    'be_groups',
    'tx_begroups_kind',
    [
        'label' => 'be_groups_test_developer.db:be_groups.kind.news',
        'value' => 'my_news',
        'icon' => 'content-news',
        'group' => 'buildingBlocks',
    ],
);

$GLOBALS['TCA']['be_groups']['ctrl']['typeicon_classes']['my_news'] = 'content-news';
$GLOBALS['TCA']['be_groups']['types']['my_news'] = [
    'title' => 'be_groups_test_developer.db:be_groups.kind.news',
    'showitem' => '
        --div--;core.form.tabs:general,
            title, tx_begroups_kind, tx_myextension_news_permissions,
        --div--;core.form.tabs:access,
            hidden,
        --div--;core.form.tabs:notes,
            description,
        --div--;core.form.tabs:extended,
    ',
];

// Classic groups show every permission field
ExtensionManagementUtility::addToAllTCAtypes(
    'be_groups',
    'tx_myextension_news_permissions',
    'classic',
    'after:custom_options',
);

$GLOBALS['TCA']['be_groups']['types']['role']['columnsOverrides']['subgroup']['config']['itemGroups']['my_news']
    = 'be_groups_test_developer.db:be_groups.kind.news';
