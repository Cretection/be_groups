<?php

declare(strict_types=1);

use Cretection\BeGroups\Domain\Kind\GroupKind;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    $labels = 'be_groups.db:';

    // The groups are grouped by kind with roles first. The list is not filtered: a filtered select
    // drops stored values that are not part of its items on every save. Which groups may be
    // assigned is enforced by the DataHandler rules (R4).
    $itemGroups = [
        GroupKind::Role->value => $labels . 'kind.group.roles',
        GroupKind::Classic->value => $labels . 'kind.group.legacy',
    ];
    foreach (GroupKind::cases() as $kind) {
        if ($kind->isBuildingBlock() && ($kind !== GroupKind::Workspace || ExtensionManagementUtility::isLoaded('workspaces'))) {
            $itemGroups[$kind->value] = $labels . 'kind.group.notAssignable.' . $kind->value;
        }
    }

    $usergroupConfig = &$GLOBALS['TCA']['be_users']['columns']['usergroup'];
    $usergroupConfig['description'] = $labels . 'be_users.usergroup.description';
    $usergroupConfig['config']['foreign_table_item_group'] = GroupKind::FIELD_NAME;
    $usergroupConfig['config']['itemGroups'] = $itemGroups;
})();
