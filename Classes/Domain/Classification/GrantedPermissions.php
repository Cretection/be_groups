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

/**
 * What TYPO3 grants a backend user through the groups (without the permissions of the user record).
 *
 * @internal
 */
final readonly class GrantedPermissions
{
    /**
     * @param array<string, list<string>> $fields the merged permission fields, each a sorted set
     * @param list<string> $tsConfig the TSconfig of the groups and their included files, in the order TYPO3 applies them
     * @param list<int> $groupUids the resolved groups, each at its last position like BackendUserAuthentication::$userGroupsUID
     * @param int $firstGroupUid the first resolved group: the owner group of pages the user creates
     */
    public function __construct(
        public array $fields,
        public int $workspacePermissions,
        public array $tsConfig,
        public array $groupUids,
        public int $firstGroupUid,
    ) {}
}
