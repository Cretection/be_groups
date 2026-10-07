<?php

declare(strict_types=1);

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

(static function (): void {
    $labels = 'be_groups.db:';
    $settings = new ExtensionSettings(GeneralUtility::makeInstance(ExtensionConfiguration::class));

    // Users get roles. "classic" groups stay assignable while they are allowed (decision E4).
    $assignableKinds = [GroupKind::Role->value => $labels . 'kind.group.roles'];
    if ($settings->isClassicGroupsAllowed()) {
        $assignableKinds[GroupKind::Classic->value] = $labels . 'kind.group.legacy';
    }
    $quotedKinds = implode(', ', array_map(static fn(string $kind): string => '\'' . $kind . '\'', array_keys($assignableKinds)));

    $usergroupConfig = &$GLOBALS['TCA']['be_users']['columns']['usergroup'];
    $usergroupConfig['description'] = $labels . 'be_users.usergroup.description';
    $usergroupConfig['config']['foreign_table_where'] = sprintf(
        'AND {#be_groups}.{#%s} IN (%s) ORDER BY be_groups.title',
        GroupKind::FIELD_NAME,
        $quotedKinds,
    );
    $usergroupConfig['config']['foreign_table_item_group'] = GroupKind::FIELD_NAME;
    $usergroupConfig['config']['itemGroups'] = $assignableKinds;
})();
