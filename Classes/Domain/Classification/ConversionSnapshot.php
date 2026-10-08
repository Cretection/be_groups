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

/**
 * All groups and users as stored before or after a conversion.
 *
 * @internal
 */
final readonly class ConversionSnapshot
{
    /**
     * @param array<int, DatabaseRow> $groups all groups by uid, including deleted ones
     * @param array<int, DatabaseRow> $users all users by uid, including deleted ones
     */
    public function __construct(
        public array $groups,
        public array $users,
    ) {}

    /**
     * @return array<int, DatabaseRow> the groups that are not deleted, by uid
     */
    public function getActiveGroups(): array
    {
        return array_filter($this->groups, static fn(DatabaseRow $group): bool => $group->get('deleted') !== '1');
    }

    /**
     * @return list<string> every distinct list of groups of the users that are not deleted
     */
    public function getUsergroupLists(): array
    {
        $lists = [];
        foreach ($this->users as $user) {
            if ($user->get('deleted') !== '1') {
                $lists[] = $user->get('usergroup');
            }
        }
        return array_values(array_unique($lists));
    }
}
