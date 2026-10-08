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

/**
 * A building block with the groups and users using it.
 *
 * @internal
 */
final readonly class BuildingBlockItem
{
    /**
     * @param list<GroupItem> $roles roles containing this building block
     * @param list<GroupItem> $otherGroups other groups containing this building block (classic groups, unknown kinds)
     * @param list<UserItem> $directUsers users assigned this building block directly
     * @param bool $hasSubgroups whether the building block itself has subgroups (not allowed for building blocks)
     */
    public function __construct(
        public GroupItem $group,
        public array $roles,
        public array $otherGroups,
        public array $directUsers,
        public bool $hasSubgroups,
    ) {}

    public function isUnused(): bool
    {
        return $this->roles === [] && $this->otherGroups === [] && $this->directUsers === [];
    }

    public function hasIssues(): bool
    {
        return $this->directUsers !== [] || $this->hasSubgroups;
    }

    public function getUsageCount(): int
    {
        return count($this->roles) + count($this->otherGroups) + count($this->directUsers);
    }
}
