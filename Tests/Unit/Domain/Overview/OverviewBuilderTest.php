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

namespace Cretection\BeGroups\Tests\Unit\Domain\Overview;

use Cretection\BeGroups\DataHandling\UidList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Overview\BuildingBlockItem;
use Cretection\BeGroups\Domain\Overview\BuildingBlockSection;
use Cretection\BeGroups\Domain\Overview\GroupItem;
use Cretection\BeGroups\Domain\Overview\KindGroups;
use Cretection\BeGroups\Domain\Overview\Overview;
use Cretection\BeGroups\Domain\Overview\OverviewBuilder;
use Cretection\BeGroups\Domain\Overview\RoleItem;
use Cretection\BeGroups\Domain\Overview\UserItem;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(OverviewBuilder::class)]
#[CoversClass(Overview::class)]
#[CoversClass(RoleItem::class)]
#[CoversClass(BuildingBlockItem::class)]
#[CoversClass(BuildingBlockSection::class)]
#[CoversClass(KindGroups::class)]
#[CoversClass(GroupItem::class)]
#[CoversClass(UserItem::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(UidList::class)]
#[CoversClass(GroupKind::class)]
final class OverviewBuilderTest extends UnitTestCase
{
    private OverviewBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new OverviewBuilder();
    }

    #[Test]
    public function roleListsItsBuildingBlocksGroupedByKindInDefinedOrder(): void
    {
        $overview = $this->build();

        $editor = $this->findRole($overview, 'R editor');
        self::assertSame(['acl', 'db_mount', 'language'], array_map(static fn(KindGroups $group): string => $group->kind, $editor->buildingBlocks));
        self::assertSame(['ACL a', 'ACL b'], array_map(static fn(GroupItem $group): string => $group->title, $editor->buildingBlocks[0]->groups));
    }

    #[Test]
    public function roleReportsMembersThatAreNoBuildingBlocksAndMissingMembers(): void
    {
        $overview = $this->build();

        $broken = $this->findRole($overview, 'R broken');
        self::assertSame(['Classic'], array_map(static fn(GroupItem $group): string => $group->title, $broken->invalidMembers));
        self::assertSame([99], $broken->missingMemberUids);
        self::assertTrue($broken->hasIssues());
    }

    #[Test]
    public function roleListsItsUsers(): void
    {
        $overview = $this->build();

        self::assertSame(['alice', 'bob'], array_map(static fn(UserItem $user): string => $user->username, $this->findRole($overview, 'R editor')->users));
    }

    #[Test]
    public function buildingBlockShowsRolesClassicGroupsAndDirectUsers(): void
    {
        $overview = $this->build();

        $aclA = $this->findBuildingBlock($overview, 'ACL a');
        self::assertSame(['R broken', 'R editor'], array_map(static fn(GroupItem $group): string => $group->title, $aclA->roles));
        self::assertSame(['Classic'], array_map(static fn(GroupItem $group): string => $group->title, $aclA->classicGroups));
        self::assertSame(['carol'], array_map(static fn(UserItem $user): string => $user->username, $aclA->directUsers));
        self::assertFalse($aclA->isUnused());
        self::assertTrue($this->findBuildingBlock($overview, 'TS unused')->isUnused());
    }

    #[Test]
    public function issueCountContainsBrokenRolesAndDirectlyAssignedBuildingBlocks(): void
    {
        self::assertSame(2, $this->build()->getIssueCount());
    }

    #[Test]
    public function rolesAreSortedByTitleOrByNumberOfUsers(): void
    {
        $titles = static fn(Overview $overview): array => array_map(static fn(RoleItem $role): string => $role->group->title, $overview->roles);

        self::assertSame(['R broken', 'R editor'], $titles($this->build(OverviewBuilder::SORT_TITLE)));
        self::assertSame(['R editor', 'R broken'], $titles($this->build(OverviewBuilder::SORT_USAGE)));
    }

    #[Test]
    public function kindFilterOnlyListsBuildingBlocksOfThatKind(): void
    {
        $overview = $this->build(OverviewBuilder::SORT_TITLE, 'tsconfig');

        self::assertSame(['tsconfig'], array_map(static fn(BuildingBlockSection $section): string => $section->kind, $overview->buildingBlockSections));
        self::assertSame(['acl', 'db_mount', 'language', 'tsconfig'], $overview->availableKinds);
        self::assertCount(2, $overview->roles);
    }

    #[Test]
    public function classicGroupsAreListedSeparately(): void
    {
        self::assertSame(['Classic'], array_map(static fn(GroupItem $group): string => $group->title, $this->build()->classicGroups));
    }

    private function build(string $sorting = OverviewBuilder::SORT_TITLE, string $kindFilter = ''): Overview
    {
        $groups = [
            $this->group(1, 'ACL b', 'acl'),
            $this->group(2, 'ACL a', 'acl'),
            $this->group(3, 'DBM site', 'db_mount'),
            $this->group(4, 'L de', 'language'),
            $this->group(5, 'TS unused', 'tsconfig'),
            $this->group(6, 'Classic', 'classic', '2'),
            $this->group(7, 'R editor', 'role', '4,3,2,1'),
            $this->group(8, 'R broken', 'role', '2,6,99'),
        ];
        $users = [
            DatabaseRow::fromArray(['uid' => 1, 'username' => 'bob', 'realName' => '', 'usergroup' => '7', 'disable' => 0]),
            DatabaseRow::fromArray(['uid' => 2, 'username' => 'alice', 'realName' => 'Alice', 'usergroup' => '7', 'disable' => 1]),
            DatabaseRow::fromArray(['uid' => 3, 'username' => 'carol', 'realName' => '', 'usergroup' => '8,2', 'disable' => 0]),
        ];
        return $this->subject->build($groups, $users, $sorting, $kindFilter);
    }

    private function group(int $uid, string $title, string $kind, string $subgroup = ''): DatabaseRow
    {
        return DatabaseRow::fromArray(['uid' => $uid, 'title' => $title, 'tx_begroups_kind' => $kind, 'subgroup' => $subgroup, 'hidden' => 0]);
    }

    private function findRole(Overview $overview, string $title): RoleItem
    {
        foreach ($overview->roles as $role) {
            if ($role->group->title === $title) {
                return $role;
            }
        }
        self::fail('Role "' . $title . '" not found.');
    }

    private function findBuildingBlock(Overview $overview, string $title): BuildingBlockItem
    {
        foreach ($overview->buildingBlockSections as $section) {
            foreach ($section->buildingBlocks as $buildingBlock) {
                if ($buildingBlock->group->title === $title) {
                    return $buildingBlock;
                }
            }
        }
        self::fail('Building block "' . $title . '" not found.');
    }
}
