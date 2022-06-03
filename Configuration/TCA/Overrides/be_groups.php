<?php
defined('TYPO3_MODE') or die();

/***************************************************************
 *  Copyright notice
 * 
 *  (c) 2022 Jonathan Starck <info@cretection.it>
 *
 *  Originally
 *  (c) 2012 Michael Klapper <michael.klapper@morphodo.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Cretection\BeGroups\Service\TceMain;

$ll = 'LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:';

$beGroupKindIconPath = PathUtility::stripPathSitePrefix(ExtensionManagementUtility::extPath('be_groups')) . "Resources/Public/Icons/svgs/selicon_be_groups_tx_begroups_kind_";
$extconf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('be_groups');

$tempColumns = [
    'tx_begroups_kind' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.tx_begroups_kind',
        'config'  => [
            'type'  => 'select',
            'renderType' => 'selectSingle',
            'showIconTable' => 1,
            "items" => [
                [$ll . 'be_groups.tx_begroups_kind.I.0', '0', $beGroupKindIconPath . '0.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.1', '1', $beGroupKindIconPath . '1.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.2', '2', $beGroupKindIconPath . '2.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.3', '3', $beGroupKindIconPath . '3.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.4', '4', $beGroupKindIconPath . '4.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.5', '5', $beGroupKindIconPath . '5.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.6', '6', $beGroupKindIconPath . '6.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.7', '7', $beGroupKindIconPath . '7.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.8', '8', $beGroupKindIconPath . '8.svg'],
                [$ll . 'be_groups.tx_begroups_kind.I.9', '9', $beGroupKindIconPath . '9.svg'],
            ],
            "size"     => 1,
            "maxitems" => 1,
        ],
    ],
    'subgroup_r' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.rights',
        'description' => $ll . 'be_groups.subgroup.rights.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.rights.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 1',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_l' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.languages',
        'description' => $ll . 'be_groups.subgroup.languages.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'newRecordLinkPosition' => 'bottom',
                'expandAll' => 1,
                'expandSingle' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.languages.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 2',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_pa' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.page_access',
        'description' => $ll . 'be_groups.subgroup.page_access.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.page_access.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 4',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_fm' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.file_mounts',
        'description' => $ll . 'be_groups.subgroup.file_mounts.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.file_mounts.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 5',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_pm' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.page_mounts',
        'description' => $ll . 'be_groups.subgroup.page_mounts.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.page_mounts.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 6',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_ts' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.tsconfig',
        'description' => $ll . 'be_groups.subgroup.tsconfig.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.tsconfig.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 7',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_ws' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.workspaces',
        'description' => $ll . 'be_groups.subgroup.workspaces.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.workspaces.div',
                    '--div--',
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 8',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
    'subgroup_cat' => [
        'exclude' => 1,
        'label'   => $ll . 'be_groups.subgroup.categories',
        'description' => $ll . 'be_groups.subgroup.categories.description',
        'config'  => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'appearance' => [
                'expandAll' => 1,
            ],
            'items' => [
                [
                    $ll . 'be_groups.subgroup.categories.div',
                    '--div--'
                ],
            ],
            'foreign_table' => 'be_groups',
            'foreign_table_where' => ' AND be_groups.tx_begroups_kind = 9',
            'size' => 10,
            'maxitems' => 999,
            'minitems' => 0,
            'multiple' => 1,
        ],
    ],
];


ExtensionManagementUtility::addTCAcolumns("be_groups", $tempColumns, 1);
ExtensionManagementUtility::addToAllTCAtypes("be_groups","tx_begroups_kind;;;;1-1-1",'','after:title');
unset($tempColumns);


/* $tabExtended       = '';
$tabExtendedFields = '';
if (ExtensionManagementUtility::isLoaded('news')) {
    $tabExtendedFields .= 'tt_news_categorymounts;;;;1-1-1, ';
}
if (trim($tabExtendedFields) != '') {
    $tabExtended = '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended, ' . $tabExtendedFields;
}

$filePermissions = '--div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.file_permissions, file_permissions,';
 */

// register the new types field
$GLOBALS['TCA']['be_groups']['ctrl']['default_sortby']      = 'ORDER BY tx_begroups_kind, title ASC';
$GLOBALS['TCA']['be_groups']['ctrl']['label_userFunc']      = 'Cretection\BeGroups\Service\TceMain\LabelHelper->getCombinedTitle';
$GLOBALS['TCA']['be_groups']['ctrl']['type']                = 'tx_begroups_kind';
$GLOBALS['TCA']['be_groups']['ctrl']['typeicon_column']     = 'tx_begroups_kind';
$GLOBALS['TCA']['be_groups']['ctrl']['typeicon_classes']    = array (
    '0' => 'tx_begroups_kind_0',
    '1' => 'tx_begroups_kind_1',
    '2' => 'tx_begroups_kind_2',
    '3' => 'tx_begroups_kind_3',
    '4' => 'tx_begroups_kind_4',
    '5' => 'tx_begroups_kind_5',
    '6' => 'tx_begroups_kind_6',
    '7' => 'tx_begroups_kind_7',
    '8' => 'tx_begroups_kind_8',
    '9' => 'tx_begroups_kind_9',
);


if (ExtensionManagementUtility::isLoaded('be_groups') && array_key_exists('onlyShowMetaGroup', $extconf) && $extconf['onlyShowMetaGroup'] === '1') {
    $GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['foreign_table_where'] = 'AND be_groups.tx_begroups_kind = 3 ORDER BY be_groups.tx_begroups_kind, be_groups.title';
} else {
    $GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['foreign_table_where'] = 'ORDER BY be_groups.tx_begroups_kind, be_groups.title';
}



// Improve visibility of subgroups in usergroup field to show only META groups
/* $GLOBALS['TCA']['be_groups']['columns']['file_mountpoints']['config']['renderType']= 'selectCheckBox';
$GLOBALS['TCA']['be_groups']['columns']['file_mountpoints']['config']['wizards'] = null; */
$GLOBALS['TCA']['be_groups']['columns']['subgroup']['config']['foreign_table_where'] = 'AND tx_begroups_kind NOT IN(3) AND NOT(be_groups.uid = ###THIS_UID###) AND be_groups.hidden=0 ORDER BY be_groups.tx_begroups_kind,be_groups.title';
/*$GLOBALS['TCA']['be_groups']['columns']['subgroup']['config']['wizards']['add'] = array(
    'icon' => 'action-add',
    'params' => array(
        'pid' => 0,
        'setValue' => 'prepend',
        'table' => 'be_groups',
    ),
    'module' => array(
        'name' => 'wizard_add',
    ),
    'title' => 'LLL:EXT:lang/locallang_tca.xml:be_users.usergroup_add_title',
    'type' => 'script',
); */

/**
0 = all
1 = authorization + extensions
2 = language
3 = meta
4 = page access group
5 = starting point of files system
6 = starting point of page tree
7 = tsconfig
8 = workspace
9 = category
 */

// define the new types and their showitems
$GLOBALS['TCA']['be_groups']['types']['0'] = array (
    'showitem' => '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
        title,
        tx_begroups_kind,
        subgroup,
        --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.base_rights,
        groupMods,
        availableWidgets,
        mfa_providers,
        tables_select,
        tables_modify,
        pagetypes_select,
        non_exclude_fields,
        explicit_allowdeny,
        allowed_languages,
        custom_options,
        --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.mounts_and_workspaces,
        workspace_perms,
        db_mountpoints,
        file_mountpoints,
        file_permissions,
        category_perms,
        --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.options,
        TSconfig,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
        hidden,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
        description,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended'
);
$GLOBALS['TCA']['be_groups']['types']['1'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.modul_rights,
        groupMods,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.table_rights,
        tables_select,
        tables_modify,
        non_exclude_fields,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.page_rights,
        pagetypes_select,
        explicit_allowdeny,
        custom_options,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.file_permissions,
        file_permissions,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.widget_rights,
        availableWidgets,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.mfa_rights,
        mfa_providers,
');
$GLOBALS['TCA']['be_groups']['types']['2'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.language_rights,
        allowed_languages,
');

$GLOBALS['TCA']['be_groups']['types']['3'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.rights,
        subgroup_r,
        subgroup_pa,
        subgroup_ts,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.mounts,
        subgroup_fm,
        subgroup_pm,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.languages,
        subgroup_l,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.ws_cat,
        subgroup_ws,
        subgroup_cat,
');

$GLOBALS['TCA']['be_groups']['types']['4'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
');

$GLOBALS['TCA']['be_groups']['types']['5'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.file_mounts,
        file_mountpoints,
        file_permissions,
');
$GLOBALS['TCA']['be_groups']['types']['6'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.db_mounts,
        db_mountpoints,'
);

$GLOBALS['TCA']['be_groups']['types']['7'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.tsconfig,
        TSconfig,
');
$GLOBALS['TCA']['be_groups']['types']['8'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.workspace_rights,
        workspace_perms,
');

$GLOBALS['TCA']['be_groups']['types']['9'] = array (
    'showitem' => '
        hidden,
        title,
        tx_begroups_kind,
        description,
        --div--;LLL:EXT:be_groups/Resources/Private/Language/locallang_db.xlf:be_groups.tabs.category_perms,
        category_perms,
');