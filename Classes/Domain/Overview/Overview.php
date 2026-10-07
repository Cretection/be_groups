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
 * The complete permission structure shown by the overview module.
 *
 * @internal
 */
final readonly class Overview
{
    /**
     * @param list<RoleItem> $roles
     * @param list<BuildingBlockSection> $buildingBlockSections
     * @param list<GroupItem> $classicGroups
     * @param list<string> $availableKinds kinds of all existing building blocks, for filtering
     */
    public function __construct(
        public array $roles,
        public array $buildingBlockSections,
        public array $classicGroups,
        public array $availableKinds,
    ) {}

    public function getIssueCount(): int
    {
        $issues = 0;
        foreach ($this->roles as $role) {
            $issues += $role->hasIssues() ? 1 : 0;
        }
        foreach ($this->buildingBlockSections as $section) {
            foreach ($section->buildingBlocks as $buildingBlock) {
                $issues += $buildingBlock->directUsers !== [] ? 1 : 0;
            }
        }
        return $issues;
    }
}
