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
 * A role with its building blocks (grouped by kind) and its users.
 *
 * @internal
 */
final readonly class RoleItem
{
    /**
     * @param list<KindGroups> $buildingBlocks
     * @param list<GroupItem> $invalidMembers subgroups that are no building blocks
     * @param list<int> $missingMemberUids subgroups that do not exist (anymore)
     * @param list<UserItem> $users
     */
    public function __construct(
        public GroupItem $group,
        public array $buildingBlocks,
        public array $invalidMembers,
        public array $missingMemberUids,
        public array $users,
    ) {}

    public function hasIssues(): bool
    {
        return $this->invalidMembers !== [] || $this->missingMemberUids !== [];
    }
}
