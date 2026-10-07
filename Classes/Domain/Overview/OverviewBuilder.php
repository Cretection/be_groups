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

use Cretection\BeGroups\DataHandling\UidList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;

/**
 * Builds the permission structure (roles, building blocks, classic groups) from plain records.
 *
 * @internal
 */
final readonly class OverviewBuilder
{
    public const SORT_TITLE = 'title';
    public const SORT_USAGE = 'usage';
    public const SORTINGS = [self::SORT_TITLE, self::SORT_USAGE];

    private const FALLBACK_ICON = 'status-user-group-backend';

    /**
     * @param list<DatabaseRow> $groupRows fields: uid, title, tx_begroups_kind, subgroup, hidden
     * @param list<DatabaseRow> $userRows fields: uid, username, realName, usergroup, disable
     * @param string $kindFilter only building blocks of this kind are listed ('' = all)
     */
    public function build(array $groupRows, array $userRows, string $sorting, string $kindFilter): Overview
    {
        $groups = [];
        $members = [];
        foreach ($groupRows as $row) {
            $kind = $row->get(GroupKind::FIELD_NAME);
            $groups[$row->getUid()] = new GroupItem(
                $row->getUid(),
                $row->get('title'),
                $kind,
                $row->get('hidden') === '1',
                GroupKind::tryFrom($kind)?->iconIdentifier() ?? self::FALLBACK_ICON,
            );
            $members[$row->getUid()] = UidList::fromValue($row->get('subgroup'))->uids;
        }

        $usersByGroup = [];
        foreach ($userRows as $row) {
            $user = new UserItem($row->getUid(), $row->get('username'), $row->get('realName'), $row->get('disable') === '1');
            foreach (UidList::fromValue($row->get('usergroup'))->uids as $groupUid) {
                $usersByGroup[$groupUid][] = $user;
            }
        }

        $roles = [];
        $classicGroups = [];
        /** @var array<int, list<GroupItem>> $rolesByBuildingBlock */
        $rolesByBuildingBlock = [];
        /** @var array<int, list<GroupItem>> $classicGroupsByBuildingBlock */
        $classicGroupsByBuildingBlock = [];
        foreach ($groups as $uid => $group) {
            if ($group->kind === GroupKind::Role->value) {
                $roles[] = $this->buildRole($group, $members[$uid], $groups, $usersByGroup[$uid] ?? []);
                foreach ($members[$uid] as $memberUid) {
                    $rolesByBuildingBlock[$memberUid][] = $group;
                }
            } elseif (!GroupKind::isBuildingBlock($group->kind)) {
                $classicGroups[] = $group;
                foreach ($members[$uid] as $memberUid) {
                    $classicGroupsByBuildingBlock[$memberUid][] = $group;
                }
            }
        }

        $buildingBlocksByKind = [];
        foreach ($groups as $uid => $group) {
            if (GroupKind::isBuildingBlock($group->kind)) {
                $buildingBlocksByKind[$group->kind][] = new BuildingBlockItem(
                    $group,
                    $this->sortGroups($rolesByBuildingBlock[$uid] ?? []),
                    $this->sortGroups($classicGroupsByBuildingBlock[$uid] ?? []),
                    $this->sortUsers($usersByGroup[$uid] ?? []),
                );
            }
        }

        $sections = [];
        foreach ($this->sortKinds(array_keys($buildingBlocksByKind)) as $kind) {
            if ($kindFilter === '' || $kindFilter === $kind) {
                $sections[] = new BuildingBlockSection($kind, $this->getIcon($kind), $this->sortBuildingBlocks($buildingBlocksByKind[$kind], $sorting));
            }
        }

        return new Overview(
            $this->sortRoles($roles, $sorting),
            $sections,
            $this->sortGroups($classicGroups),
            $this->sortKinds(array_keys($buildingBlocksByKind)),
        );
    }

    /**
     * @param list<int> $memberUids
     * @param array<int, GroupItem> $groups
     * @param list<UserItem> $users
     */
    private function buildRole(GroupItem $role, array $memberUids, array $groups, array $users): RoleItem
    {
        $buildingBlocksByKind = [];
        $invalidMembers = [];
        $missingMemberUids = [];
        foreach ($memberUids as $memberUid) {
            $member = $groups[$memberUid] ?? null;
            if ($member === null) {
                $missingMemberUids[] = $memberUid;
            } elseif (GroupKind::isBuildingBlock($member->kind)) {
                $buildingBlocksByKind[$member->kind][] = $member;
            } else {
                $invalidMembers[] = $member;
            }
        }

        $buildingBlocks = [];
        foreach ($this->sortKinds(array_keys($buildingBlocksByKind)) as $kind) {
            $buildingBlocks[] = new KindGroups($kind, $this->getIcon($kind), $this->sortGroups($buildingBlocksByKind[$kind]));
        }
        return new RoleItem($role, $buildingBlocks, $invalidMembers, $missingMemberUids, $this->sortUsers($users));
    }

    /**
     * Built-in kinds in their defined order, followed by kinds of other extensions alphabetically.
     *
     * @param list<string> $kinds
     * @return list<string>
     */
    private function sortKinds(array $kinds): array
    {
        $order = array_flip(array_map(static fn(GroupKind $kind): string => $kind->value, GroupKind::cases()));
        usort($kinds, static fn(string $a, string $b): int => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);
        return $kinds;
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

    private function getIcon(string $kind): string
    {
        return GroupKind::tryFrom($kind)?->iconIdentifier() ?? self::FALLBACK_ICON;
    }
}
