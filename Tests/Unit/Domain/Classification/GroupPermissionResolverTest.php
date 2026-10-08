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

namespace Cretection\BeGroups\Tests\Unit\Domain\Classification;

use Cretection\BeGroups\Domain\Classification\GrantedPermissions;
use Cretection\BeGroups\Domain\Classification\GroupPermissionResolver;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Random\Engine\Mt19937;
use Random\Randomizer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(GroupPermissionResolver::class)]
#[CoversClass(GrantedPermissions::class)]
#[CoversClass(DatabaseRow::class)]
final class GroupPermissionResolverTest extends UnitTestCase
{
    #[Test]
    public function findsTheListsThatReachAGroupThroughSubgroups(): void
    {
        $groups = [
            1 => $this->group(1, '2'),
            2 => $this->group(2, '3'),
            3 => $this->group(3, ''),
            4 => $this->group(4, ''),
            5 => $this->group(5, '3', hidden: true),
        ];
        $lists = ['1', '4', '3', '4,2', '', '03', 'be_groups_3', '5', '6,4'];

        self::assertSame(
            ['1', '3', '4,2', '03', '5'],
            (new GroupPermissionResolver())->findListsReaching(3, $lists, $groups),
        );
    }

    /**
     * The conversions only verify the lists this method returns, so it must return every list whose
     * resolution contains the group, whatever the groups and lists look like.
     */
    #[Test]
    public function findsEveryListWhoseResolutionContainsTheGroup(): void
    {
        $randomizer = new Randomizer(new Mt19937(20261008));
        $subject = new GroupPermissionResolver();
        for ($round = 0; $round < 20; $round++) {
            $groups = [];
            for ($uid = 1; $uid <= 30; $uid++) {
                $groups[$uid] = $this->group($uid, $this->randomList($randomizer), $randomizer->getInt(1, 5) === 1);
            }
            $lists = [];
            for ($index = 0; $index < 100; $index++) {
                $lists[] = $this->randomList($randomizer);
            }
            $lists = array_values(array_unique($lists));

            for ($groupUid = 1; $groupUid <= 30; $groupUid++) {
                $found = $subject->findListsReaching($groupUid, $lists, $groups);
                foreach ($lists as $list) {
                    if (in_array($groupUid, $subject->resolve($list, $groups)->groupUids, true)) {
                        self::assertContains($list, $found, sprintf('Round %d, group %d', $round, $groupUid));
                    }
                }
            }
        }
    }

    /**
     * A list of groups as it may be stored: canonical, with duplicates, with notations that TYPO3
     * reads as another number or as 0, and with groups that do not exist.
     */
    private function randomList(Randomizer $randomizer): string
    {
        $entries = [];
        for ($count = $randomizer->getInt(0, 3); $count > 0; $count--) {
            $uid = $randomizer->getInt(1, 32);
            $entries[] = match ($randomizer->getInt(1, 10)) {
                1 => '0' . $uid,
                2 => ' ' . $uid,
                3 => 'be_groups_' . $uid,
                4 => $uid . ',' . $uid,
                default => (string)$uid,
            };
        }
        return implode(',', $entries);
    }

    private function group(int $uid, string $subgroups, bool $hidden = false): DatabaseRow
    {
        return DatabaseRow::fromArray(['uid' => $uid, 'hidden' => $hidden ? 1 : 0, 'subgroup' => $subgroups, 'groupMods' => 'module_' . $uid]);
    }
}
