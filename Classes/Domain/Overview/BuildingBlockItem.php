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
 * A building block with the roles and groups using it.
 *
 * @internal
 */
final readonly class BuildingBlockItem
{
    /**
     * @param list<GroupItem> $roles roles containing this building block
     * @param list<GroupItem> $classicGroups classic groups containing this building block
     * @param list<UserItem> $directUsers users assigned this building block directly
     */
    public function __construct(
        public GroupItem $group,
        public array $roles,
        public array $classicGroups,
        public array $directUsers,
    ) {}

    public function isUnused(): bool
    {
        return $this->roles === [] && $this->classicGroups === [] && $this->directUsers === [];
    }

    public function getUsageCount(): int
    {
        return count($this->roles) + count($this->classicGroups) + count($this->directUsers);
    }
}
