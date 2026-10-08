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

namespace Cretection\BeGroups\Domain\Overview;

use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindDefinition;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;

/**
 * Builds the permission structure (roles, building blocks, classic groups) from plain records.
 *
 * Kinds are never used as array keys, as PHP would turn numeric kinds into integers.
 *
 * @internal
 */
final readonly class OverviewBuilder
{
    public const SORT_TITLE = 'title';
    public const SORT_USAGE = 'usage';
    public const SORTINGS = [self::SORT_TITLE, self::SORT_USAGE];

    private const FALLBACK_ICON = 'actions-users';

    /**
     * @param list<DatabaseRow> $groupRows fields: uid, title, tx_begroups_kind, subgroup, hidden
     * @param list<DatabaseRow> $userRows fields: uid, username, realName, usergroup, disable
     * @param list<KindDefinition> $kindDefinitions the configured kinds with translated labels, in their order
     * @param string $kindFilter only building blocks of this kind are listed ('' = all)
     */
    public function build(array $groupRows, array $userRows, array $kindDefinitions, string $sorting, string $kindFilter): Overview
    {
        $definitions = [];
        foreach ($kindDefinitions as $definition) {
            $definitions['kind:' . $definition->value] = $definition;
        }
        $isBuildingBlock = static fn(string $kind): bool => isset($definitions['kind:' . $kind])
            && $kind !== GroupKind::Role->value
            && $kind !== GroupKind::Classic->value;

        $groups = [];
        $members = [];
        foreach ($groupRows as $row) {
            $kind = $row->get(GroupKind::FIELD_NAME);
            $groups[$row->getUid()] = new GroupItem(
                $row->getUid(),
                $row->get('title'),
                $kind,
                $row->get('hidden') === '1',
                isset($definitions['kind:' . $kind]) ? $definitions['kind:' . $kind]->iconIdentifier : self::FALLBACK_ICON,
            );
            $members[$row->getUid()] = RelationList::fromValue($row->get('subgroup'))->getUids();
        }

        $usersByGroup = [];
        foreach ($userRows as $row) {
            $user = new UserItem($row->getUid(), $row->get('username'), $row->get('realName'), $row->get('disable') === '1');
            foreach (RelationList::fromValue($row->get('usergroup'))->getUids() as $groupUid) {
                $usersByGroup[$groupUid][] = $user;
            }
        }

        $parentRoles = [];
        $parentOthers = [];
        foreach ($groups as $uid => $group) {
            foreach ($members[$uid] as $memberUid) {
                if ($group->kind === GroupKind::Role->value) {
                    $parentRoles[$memberUid][] = $group;
                } else {
                    $parentOthers[$memberUid][] = $group;
                }
            }
        }

        $roles = [];
        $classicGroups = [];
        $unknownKindGroups = [];
        $buildingBlocks = [];
        foreach ($groups as $uid => $group) {
            if ($group->kind === GroupKind::Role->value) {
                $roles[] = $this->buildRole($group, $members[$uid], $groups, $definitions, $isBuildingBlock, $usersByGroup[$uid] ?? []);
            } elseif ($group->kind === GroupKind::Classic->value) {
                $classicGroups[] = $group;
            } elseif ($isBuildingBlock($group->kind)) {
                $buildingBlocks[] = new BuildingBlockItem(
                    $group,
                    $this->sortGroups($parentRoles[$uid] ?? []),
                    $this->sortGroups($parentOthers[$uid] ?? []),
                    $this->sortUsers($usersByGroup[$uid] ?? []),
                    $members[$uid] !== [],
                );
            } else {
                $unknownKindGroups[] = $group;
            }
        }

        $issueCount = count($unknownKindGroups);
        foreach ($roles as $role) {
            $issueCount += $role->hasIssues() ? 1 : 0;
        }
        foreach ($buildingBlocks as $buildingBlock) {
            $issueCount += $buildingBlock->hasIssues() ? 1 : 0;
        }

        $sections = [];
        $buildingBlockKinds = [];
        foreach ($kindDefinitions as $definition) {
            $blocksOfKind = array_values(array_filter(
                $buildingBlocks,
                static fn(BuildingBlockItem $buildingBlock): bool => $buildingBlock->group->kind === $definition->value,
            ));
            if ($blocksOfKind === []) {
                continue;
            }
            $buildingBlockKinds[] = $definition;
            if ($kindFilter === '' || $kindFilter === $definition->value) {
                $sections[] = new BuildingBlockSection(
                    $definition->value,
                    $definition->label,
                    $definition->iconIdentifier,
                    $this->sortBuildingBlocks($blocksOfKind, $sorting),
                );
            }
        }

        return new Overview(
            $this->sortRoles($roles, $sorting),
            $sections,
            $this->sortGroups($classicGroups),
            $this->sortGroups($unknownKindGroups),
            $buildingBlockKinds,
            $issueCount,
        );
    }

    /**
     * @param list<int> $memberUids
     * @param array<int, GroupItem> $groups
     * @param array<string, KindDefinition> $definitions
     * @param \Closure(string): bool $isBuildingBlock
     * @param list<UserItem> $users
     */
    private function buildRole(GroupItem $role, array $memberUids, array $groups, array $definitions, \Closure $isBuildingBlock, array $users): RoleItem
    {
        $buildingBlocks = [];
        $invalidMembers = [];
        $missingMemberUids = [];
        foreach ($memberUids as $memberUid) {
            $member = $groups[$memberUid] ?? null;
            if ($member === null) {
                $missingMemberUids[] = $memberUid;
            } elseif ($isBuildingBlock($member->kind)) {
                $buildingBlocks[] = $member;
            } else {
                $invalidMembers[] = $member;
            }
        }

        $kindGroups = [];
        foreach ($definitions as $definition) {
            $membersOfKind = array_values(array_filter(
                $buildingBlocks,
                static fn(GroupItem $member): bool => $member->kind === $definition->value,
            ));
            if ($membersOfKind !== []) {
                $kindGroups[] = new KindGroups($definition->value, $definition->label, $definition->iconIdentifier, $this->sortGroups($membersOfKind));
            }
        }
        return new RoleItem($role, $kindGroups, $invalidMembers, $missingMemberUids, $this->sortUsers($users));
    }

    /**
     * @param list<RoleItem> $roles
     * @return list<RoleItem>
     */
    private function sortRoles(array $roles, string $sorting): array
    {
        usort($roles, static function (RoleItem $a, RoleItem $b) use ($sorting): int {
            if ($sorting === self::SORT_USAGE && count($a->users) !== count($b->users)) {
                return count($b->users) <=> count($a->users);
            }
            return strnatcasecmp($a->group->title, $b->group->title);
        });
        return $roles;
    }

    /**
     * @param list<BuildingBlockItem> $buildingBlocks
     * @return list<BuildingBlockItem>
     */
    private function sortBuildingBlocks(array $buildingBlocks, string $sorting): array
    {
        usort($buildingBlocks, static function (BuildingBlockItem $a, BuildingBlockItem $b) use ($sorting): int {
            if ($sorting === self::SORT_USAGE && $a->getUsageCount() !== $b->getUsageCount()) {
                return $b->getUsageCount() <=> $a->getUsageCount();
            }
            return strnatcasecmp($a->group->title, $b->group->title);
        });
        return $buildingBlocks;
    }

    /**
     * @param list<GroupItem> $groups
     * @return list<GroupItem>
     */
    private function sortGroups(array $groups): array
    {
        usort($groups, static fn(GroupItem $a, GroupItem $b): int => strnatcasecmp($a->title, $b->title));
        return $groups;
    }

    /**
     * @param list<UserItem> $users
     * @return list<UserItem>
     */
    private function sortUsers(array $users): array
    {
        usort($users, static fn(UserItem $a, UserItem $b): int => strnatcasecmp($a->username, $b->username));
        return $users;
    }
}
