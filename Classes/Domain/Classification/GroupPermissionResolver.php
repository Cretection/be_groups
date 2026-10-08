<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Cretection\BeGroups\Domain\Classification;

use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Determines what TYPO3 grants a backend user through the groups, modelled after TYPO3 14:
 * GroupResolver::fetchGroupsRecursive() (subgroups before their group; hidden, deleted and
 * missing groups and cycles are skipped) and BackendUserAuthentication::fetchGroupData()
 * (fields merged as sets, TSconfig in the order of the last occurrence of each group).
 * Both are internal API of the core, hence this model. It only serves to verify that a
 * conversion changes nothing.
 *
 * @internal
 */
final readonly class GroupPermissionResolver
{
    /**
     * The fields BackendUserAuthentication::fetchGroupData() merges from all groups.
     */
    private const MERGED_FIELDS = [
        'db_mountpoints', 'file_mountpoints', 'groupMods', 'availableWidgets', 'mfa_providers', 'tables_select',
        'tables_modify', 'pagetypes_select', 'non_exclude_fields', 'explicit_allowdeny', 'allowed_languages',
        'custom_options', 'file_permissions', 'category_perms',
    ];

    /**
     * @param string $usergroupList the stored groups of a user (be_users.usergroup)
     * @param array<int, DatabaseRow> $groups all non-deleted groups by uid, hidden ones included
     */
    public function resolve(string $usergroupList, array $groups): GrantedPermissions
    {
        $resolvedGroups = $this->resolveGroups(GeneralUtility::intExplode(',', $usergroupList, true), $groups, []);

        $fields = array_fill_keys(self::MERGED_FIELDS, []);
        $workspacePermissions = 0;
        $uids = [];
        foreach ($resolvedGroups as $group) {
            $uids[] = $group->getUid();
            foreach (self::MERGED_FIELDS as $fieldName) {
                $fields[$fieldName] = [...$fields[$fieldName], ...$this->readValues($fieldName, $group->get($fieldName))];
            }
            $workspacePermissions |= (int)$group->get('workspace_perms');
        }
        // Tables that may be modified may be read as well.
        $fields['tables_select'] = [...$fields['tables_select'], ...$fields['tables_modify']];
        foreach ($fields as $fieldName => $values) {
            $values = array_values(array_unique($values));
            sort($values, SORT_STRING);
            $fields[$fieldName] = $values;
        }

        // Each group counts at its last position, which decides the order of TSconfig.
        $groupUids = array_values(array_reverse(array_unique(array_reverse($uids))));
        $tsConfig = [];
        foreach ($groupUids as $uid) {
            $group = $groups[$uid];
            $groupTsConfig = $group->get('TSconfig');
            if ($groupTsConfig !== '' && $groupTsConfig !== '0') {
                $tsConfig[] = $groupTsConfig;
            }
            foreach (GeneralUtility::trimExplode(',', $group->get('tsconfig_includes'), true) as $file) {
                $tsConfig[] = 'include: ' . $file;
            }
        }
        return new GrantedPermissions($fields, $workspacePermissions, $tsConfig, $groupUids, $uids[0] ?? 0);
    }

    /**
     * Returns the lists of groups from which the group can be reached through subgroups, read like
     * resolve() reads them. Only for these lists can resolve() contain the group; it skips hidden
     * groups and cycles in addition, so some of the returned lists may not contain it.
     *
     * @param list<string> $usergroupLists stored groups of users (be_users.usergroup)
     * @param array<int, DatabaseRow> $groups all non-deleted groups by uid, hidden ones included
     * @return list<string>
     */
    public function findListsReaching(int $groupUid, array $usergroupLists, array $groups): array
    {
        $parentUids = [];
        foreach ($groups as $group) {
            foreach (GeneralUtility::intExplode(',', $group->get('subgroup'), true) as $subgroupUid) {
                $parentUids[$subgroupUid][] = $group->getUid();
            }
        }
        $reaching = [$groupUid => true];
        $pending = [$groupUid];
        while ($pending !== []) {
            foreach ($parentUids[array_pop($pending)] ?? [] as $parentUid) {
                if (!isset($reaching[$parentUid])) {
                    $reaching[$parentUid] = true;
                    $pending[] = $parentUid;
                }
            }
        }
        return array_values(array_filter(
            $usergroupLists,
            static fn(string $usergroupList): bool => array_intersect_key(
                array_flip(GeneralUtility::intExplode(',', $usergroupList, true)),
                $reaching,
            ) !== [],
        ));
    }

    /**
     * Reads the values of a field the way TYPO3 uses them: page mounts as numbers including the
     * root "0" (filterValidWebMounts(), getWebmounts()), file mounts as numbers of existing records (getFileMountRecords()),
     * category mounts without empty values (getCategoryMountPoints()), all others as strings.
     *
     * @return list<string>
     */
    private function readValues(string $fieldName, string $value): array
    {
        $values = GeneralUtility::trimExplode(',', $value, true);
        return match ($fieldName) {
            'db_mountpoints' => array_map(
                static fn(string $uid): string => (string)(int)$uid,
                array_values(array_filter($values, self::isKeptWebMount(...))),
            ),
            'file_mountpoints' => array_map(strval(...), array_values(array_filter(
                GeneralUtility::intExplode(',', $value, true),
                static fn(int $uid): bool => $uid > 0,
            ))),
            'category_perms' => array_values(array_filter($values, static fn(string $uid): bool => (bool)$uid)),
            default => $values,
        };
    }

    /**
     * BackendUserAuthentication::filterValidWebMounts() drops every entry that is greater than 0 in
     * PHP comparison and no key of the readable pages, i.e. no plain positive number (e.g. "05", "+5",
     * "abc"); all other entries remain and are read as numbers by getWebmounts().
     */
    private static function isKeptWebMount(string $entry): bool
    {
        $isGreaterThanZero = is_numeric($entry) ? (float)$entry > 0 : strcmp($entry, '0') > 0;
        return !$isGreaterThanZero || preg_match('/^[1-9]\d*$/', $entry) === 1;
    }

    /**
     * @param list<int> $groupUids
     * @param array<int, DatabaseRow> $groups
     * @param list<int> $ancestors
     * @return list<DatabaseRow>
     */
    private function resolveGroups(array $groupUids, array $groups, array $ancestors): array
    {
        $resolved = [];
        foreach ($groupUids as $uid) {
            $group = $groups[$uid] ?? null;
            // The HiddenRestriction only accepts hidden = 0.
            if ($group === null || $group->get('hidden') !== '0' || in_array($uid, $ancestors, true)) {
                continue;
            }
            $subgroupUids = GeneralUtility::intExplode(',', $group->get('subgroup'), true);
            if ($subgroupUids !== []) {
                $resolved = [...$resolved, ...$this->resolveGroups($subgroupUids, $groups, [...$ancestors, $uid])];
            }
            $resolved[] = $group;
        }
        return $resolved;
    }
}
