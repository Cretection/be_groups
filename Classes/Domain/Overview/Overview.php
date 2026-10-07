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

use Cretection\BeGroups\Domain\Kind\KindDefinition;

/**
 * The complete permission structure shown by the overview module.
 *
 * @internal
 */
final readonly class Overview
{
    /**
     * @param list<RoleItem> $roles
     * @param list<BuildingBlockSection> $buildingBlockSections building blocks, filtered by kind if requested
     * @param list<GroupItem> $classicGroups
     * @param list<GroupItem> $unknownKindGroups groups whose kind is not configured (e.g. legacy kinds before the upgrade wizard)
     * @param list<KindDefinition> $buildingBlockKinds kinds of all existing building blocks, for filtering
     * @param int $issueCount entries not following the role model, independent of any filter
     */
    public function __construct(
        public array $roles,
        public array $buildingBlockSections,
        public array $classicGroups,
        public array $unknownKindGroups,
        public array $buildingBlockKinds,
        public int $issueCount,
    ) {}
}
