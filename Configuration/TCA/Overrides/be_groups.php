<?php

declare(strict_types=1);

use Cretection\BeGroups\Backend\GroupRecordTitle;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    $table = 'be_groups';
    $kindField = GroupKind::FIELD_NAME;
    $labels = 'be_groups.db:';

    $availableKinds = array_values(array_filter(
        GroupKind::cases(),
        static fn(GroupKind $kind): bool => $kind !== GroupKind::Workspace || ExtensionManagementUtility::isLoaded('workspaces'),
    ));

    // The kind selector, grouped into roles, building blocks and the legacy "classic" kind.
    // Whether "classic" may be chosen is decided at runtime (KindSelection form data provider).
    $items = [];
    foreach ($availableKinds as $kind) {
        $items[] = [
            'label' => $labels . 'kind.' . $kind->value,
            'value' => $kind->value,
            'icon' => $kind->iconIdentifier(),
            'group' => match (true) {
                $kind === GroupKind::Role => 'roles',
                $kind === GroupKind::Classic => 'legacy',
                default => 'buildingBlocks',
            },
        ];
    }
    ExtensionManagementUtility::addTCAcolumns($table, [
        $kindField => [
            'label' => $labels . 'be_groups.tx_begroups_kind',
            'description' => $labels . 'be_groups.tx_begroups_kind.description',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $items,
                'itemGroups' => [
                    'roles' => $labels . 'kind.group.roles',
                    'buildingBlocks' => $labels . 'kind.group.buildingBlocks',
                    'legacy' => $labels . 'kind.group.legacy',
                ],
                'default' => GroupKind::Classic->value,
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
            ],
            // Changing the kind changes which permissions a group may grant.
            'authenticationContext' => [
                'group' => 'be.userManagement',
            ],
        ],
    ]);

    $GLOBALS['TCA'][$table]['ctrl']['type'] = $kindField;
    // Wherever TYPO3 shows the title of a group, it is prefixed with its kind, e.g. "META: Editors".
    $GLOBALS['TCA'][$table]['ctrl']['label_userFunc'] = GroupRecordTitle::class . '->render';
    $GLOBALS['TCA'][$table]['ctrl']['typeicon_column'] = $kindField;
    $GLOBALS['TCA'][$table]['ctrl']['default_sortby'] = 'title';
    // Monochrome icons of the core, so they follow the light and the dark backend theme.
    $GLOBALS['TCA'][$table]['ctrl']['typeicon_classes']['default'] = 'actions-users';
    foreach ($availableKinds as $kind) {
        $GLOBALS['TCA'][$table]['ctrl']['typeicon_classes'][$kind->value] = $kind->iconIdentifier();
    }

    // "classic" keeps the core form of TYPO3 including all fields added by other extensions.
    ExtensionManagementUtility::addToAllTCAtypes($table, $kindField, '', 'after:title');
    $GLOBALS['TCA'][$table]['types'][GroupKind::Classic->value] = $GLOBALS['TCA'][$table]['types'][0];
    $GLOBALS['TCA'][$table]['types'][GroupKind::Classic->value]['title'] = $labels . 'kind.classic';

    // Each building block only shows (and may only carry) the fields of its concern.
    $hasField = static fn(string $field): bool => isset($GLOBALS['TCA'][$table]['columns'][$field]);
    $kindFields = [
        GroupKind::Role->value => 'subgroup;' . $labels . 'be_groups.subgroup.role,',
        GroupKind::AccessControl->value => implode('', [
            '--palette--;;authentication,',
            '--div--;core.form.tabs:recordpermissions,',
            '--palette--;;permissionGeneral,',
            '--palette--;;permissionSpecific,',
            '--div--;core.form.tabs:modulepermissions,',
            'groupMods,',
            $hasField('availableWidgets') ? 'availableWidgets,' : '',
            'custom_options,',
        ]),
        GroupKind::PageGroup->value => '',
        GroupKind::DatabaseMount->value => 'db_mountpoints,',
        GroupKind::FileMount->value => 'file_mountpoints,',
        GroupKind::FileOperations->value => 'file_permissions,',
        GroupKind::CategoryMount->value => 'category_perms,',
        GroupKind::Language->value => '--palette--;;permissionLanguages,',
        GroupKind::TsConfig->value => 'TSconfig, tsconfig_includes,',
        GroupKind::Workspace->value => 'workspace_perms,',
    ];
    foreach ($availableKinds as $kind) {
        if (!isset($kindFields[$kind->value])) {
            continue;
        }
        $GLOBALS['TCA'][$table]['types'][$kind->value] = [
            'title' => $labels . 'kind.' . $kind->value,
            'showitem' => '--div--;core.form.tabs:general, title, ' . $kindField . ', '
                . $kindFields[$kind->value]
                . '--div--;core.form.tabs:access, hidden,'
                . '--div--;core.form.tabs:notes, description,'
                . '--div--;core.form.tabs:extended,',
        ];
    }

    // The members of a role are grouped by kind. The list is not filtered: a filtered select drops
    // stored values that are not part of its items on every save. Which groups may be added is
    // enforced by the DataHandler rules, and the order of the members (TSconfig) is kept.
    $memberGroups = [];
    foreach ($availableKinds as $kind) {
        if ($kind->isBuildingBlock()) {
            $memberGroups[$kind->value] = $labels . 'kind.' . $kind->value;
        }
    }
    $memberGroups[GroupKind::Role->value] = $labels . 'kind.group.notAllowedInRole.role';
    $memberGroups[GroupKind::Classic->value] = $labels . 'kind.group.notAllowedInRole.classic';
    $GLOBALS['TCA'][$table]['types'][GroupKind::Role->value]['columnsOverrides']['subgroup'] = [
        'description' => $labels . 'be_groups.subgroup.role.description',
        'config' => [
            'foreign_table_item_group' => $kindField,
            'itemGroups' => $memberGroups,
        ],
    ];

    // Roles combine many building blocks: allow more members than the 255 characters of the core.
    $GLOBALS['TCA'][$table]['columns']['subgroup']['config']['dbFieldLength'] = 2048;
})();
