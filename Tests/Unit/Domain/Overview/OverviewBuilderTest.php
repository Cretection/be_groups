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

use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\KindDefinition;
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
#[CoversClass(KindDefinition::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(RelationList::class)]
final class OverviewBuilderTest extends UnitTestCase
{
    private OverviewBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new OverviewBuilder();
    }

    #[Test]
    public function roleListsItsBuildingBlocksGroupedByKindInConfiguredOrder(): void
    {
        $editor = $this->findRole($this->build(), 'R editor');

        self::assertSame(['acl', 'db_mount', 'language'], array_map(static fn(KindGroups $group): string => $group->kind, $editor->buildingBlocks));
        self::assertSame('Access rights', $editor->buildingBlocks[0]->label);
        self::assertSame(['ACL a', 'ACL b'], array_map(static fn(GroupItem $group): string => $group->title, $editor->buildingBlocks[0]->groups));
    }

    #[Test]
    public function roleReportsMembersThatAreNoBuildingBlocksAndMissingMembers(): void
    {
        $broken = $this->findRole($this->build(), 'R broken');

        self::assertSame(['Classic', 'Unknown kind'], array_map(static fn(GroupItem $group): string => $group->title, $broken->invalidMembers));
        self::assertSame([99], $broken->missingMemberUids);
        self::assertTrue($broken->hasIssues());
    }

    #[Test]
    public function roleListsItsUsers(): void
    {
        self::assertSame(['alice', 'bob'], array_map(static fn(UserItem $user): string => $user->username, $this->findRole($this->build(), 'R editor')->users));
    }

    #[Test]
    public function buildingBlockShowsAllParentGroupsAndDirectUsers(): void
    {
        $overview = $this->build();

        $aclA = $this->findBuildingBlock($overview, 'ACL a');
        self::assertSame(['R broken', 'R editor'], array_map(static fn(GroupItem $group): string => $group->title, $aclA->roles));
        self::assertSame(['Classic', 'Unknown kind'], array_map(static fn(GroupItem $group): string => $group->title, $aclA->otherGroups));
        self::assertSame(['carol'], array_map(static fn(UserItem $user): string => $user->username, $aclA->directUsers));
        self::assertFalse($aclA->isUnused());
        self::assertTrue($this->findBuildingBlock($overview, 'TS unused')->isUnused());
    }

    #[Test]
    public function buildingBlockUsedOnlyByAnotherBuildingBlockIsNotUnusedButFlagged(): void
    {
        $overview = $this->build();

        self::assertFalse($this->findBuildingBlock($overview, 'L de')->isUnused());
        self::assertTrue($this->findBuildingBlock($overview, 'DBM with subgroups')->hasSubgroups);
    }

    #[Test]
    public function groupsWithUnknownOrNumericKindsAreListedSeparately(): void
    {
        $overview = $this->build();

        self::assertSame(['Orphaned workspace', 'Unknown kind'], array_map(static fn(GroupItem $group): string => $group->title, $overview->unknownKindGroups));
        self::assertSame('3', $overview->unknownKindGroups[1]->kind);
    }

    #[Test]
    public function issueCountIsIndependentOfTheKindFilter(): void
    {
        // broken role, direct user of ACL a, building block with subgroups, two unknown kinds
        self::assertSame(5, $this->build()->issueCount);
        self::assertSame(5, $this->build(OverviewBuilder::SORT_TITLE, 'tsconfig')->issueCount);
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
        self::assertSame(['acl', 'db_mount', 'language', 'tsconfig', 'my_kind'], array_map(static fn(KindDefinition $kind): string => $kind->value, $overview->buildingBlockKinds));
        self::assertCount(2, $overview->roles);
    }

    #[Test]
    public function kindsOfOtherExtensionsUseTheirConfiguredLabelAndIcon(): void
    {
        $sections = array_values(array_filter(
            $this->build()->buildingBlockSections,
            static fn(BuildingBlockSection $section): bool => $section->kind === 'my_kind',
        ));
        self::assertCount(1, $sections);
        $myKind = $sections[0];

        self::assertSame('my_kind', $myKind->kind);
        self::assertSame('My kind', $myKind->label);
        self::assertSame('content-news', $myKind->iconIdentifier);
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
            $this->group(8, 'R broken', 'role', '2,6,10,99'),
            $this->group(9, 'DBM with subgroups', 'db_mount', '4'),
            $this->group(10, 'Unknown kind', '3', '2'),
            $this->group(11, 'Orphaned workspace', 'workspace'),
            $this->group(12, 'News categories', 'my_kind'),
        ];
        $users = [
            DatabaseRow::fromArray(['uid' => 1, 'username' => 'bob', 'realName' => '', 'usergroup' => '7', 'disable' => 0]),
            DatabaseRow::fromArray(['uid' => 2, 'username' => 'alice', 'realName' => 'Alice', 'usergroup' => '7', 'disable' => 1]),
            DatabaseRow::fromArray(['uid' => 3, 'username' => 'carol', 'realName' => '', 'usergroup' => '8,2', 'disable' => 0]),
        ];
        $kinds = [
            new KindDefinition('role', 'Role', 'status-user-group-backend'),
            new KindDefinition('acl', 'Access rights', 'actions-shield'),
            new KindDefinition('db_mount', 'Page tree mount', 'actions-pagetree-mount'),
            new KindDefinition('language', 'Languages', 'mimetypes-x-sys_language'),
            new KindDefinition('tsconfig', 'TSconfig', 'mimetypes-text-typoscript'),
            new KindDefinition('my_kind', 'My kind', 'content-news'),
            new KindDefinition('classic', 'Classic', 'actions-key'),
        ];
        return $this->subject->build($groups, $users, $kinds, $sorting, $kindFilter);
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
