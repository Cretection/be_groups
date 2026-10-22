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

namespace Cretection\BeGroups\Tests\Functional\Domain\Classification;

use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Classification\Classification;
use Cretection\BeGroups\Domain\Classification\ClassificationAction;
use Cretection\BeGroups\Domain\Classification\Concern;
use Cretection\BeGroups\Domain\Classification\ConversionSnapshot;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Domain\Repository\PageOwnerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(GroupClassifier::class)]
#[CoversClass(Classification::class)]
#[CoversClass(Concern::class)]
#[CoversClass(ConversionSnapshot::class)]
#[CoversClass(PageOwnerRepository::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(RelationList::class)]
final class GroupClassifierTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
    }

    #[Test]
    public function proposesHowEveryClassicGroupFitsIntoTheRoleModel(): void
    {
        self::assertSame([
            1 => 'change-kind db_mount',
            2 => 'split page_group, acl, db_mount, file_operations, tsconfig',
            3 => 'change-kind role',
            4 => 'manual',
            5 => 'change-kind page_group',
            6 => 'split page_group, db_mount',
            8 => 'manual',
            9 => 'split acl, file_operations',
        ], $this->summarize($this->get(GroupClassifier::class)->classifyAll()));
    }

    #[Test]
    public function explainsWhyAGroupCanNotBeConvertedAutomatically(): void
    {
        $classifier = $this->get(GroupClassifier::class);

        self::assertSame(
            'Grants no permissions and is assigned to no user directly. Choose a kind; delete it only if no group, workspace or TSconfig condition refers to it.',
            $classifier->classify(4)?->reason,
        );
        // Without EXT:workspaces, no form shows the field and there is no kind it could be assigned to.
        self::assertSame(
            'The group allows editing in the live workspace (workspace_perms), but EXT:workspaces is not installed: no form shows the field, and it grants nothing until EXT:workspaces is installed again. '
            . 'Install EXT:workspaces to convert the group with a building block of the kind "workspace", or clear the field if you no longer use workspaces (see "Installations without EXT:workspaces" in the manual).',
            $classifier->classify(8)?->reason,
        );
        self::assertSame(
            'Is assigned to 1 user(s) directly, so it stays their role. A new page group becomes the owner group of the pages its users create.',
            $classifier->classify(6)?->reason,
        );
        // Its active subgroup stays the owner group of new pages.
        self::assertSame('Grants permissions of several kinds: acl, file_operations.', $classifier->classify(9)?->reason);
    }

    #[Test]
    public function proposesARoleForAGroupThatGrantsNothingButIsAssignedToUsers(): void
    {
        // Workspaces, stages and TYPO3 itself (owner group of new pages) may refer to the group by its uid.
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', ['uid' => 4, 'pid' => 0, 'username' => 'reviewer', 'usergroup' => '4,2']);

        $classification = $this->get(GroupClassifier::class)->classify(4);

        self::assertNotNull($classification);
        self::assertSame('change-kind role', $this->summarize([$classification])[4]);
        self::assertSame(
            'Grants no permissions, but is assigned to 1 user(s) directly. As a role it keeps them and every reference to its uid, e.g. as workspace member.',
            $classification->reason,
        );
    }

    #[Test]
    public function keepsTheValuesOfEveryConcern(): void
    {
        $concerns = $this->get(GroupClassifier::class)->classify(2)->concerns ?? [];

        self::assertSame([
            ['page_group', []],
            ['acl', ['groupMods' => 'web_layout']],
            ['db_mount', ['db_mountpoints' => '1']],
            ['file_operations', ['file_permissions' => 'readFile,writeFile']],
            ['tsconfig', ['TSconfig' => 'options.clearCache.pages = 1']],
        ], array_map(static fn(Concern $concern): array => [$concern->kind, $concern->values], $concerns));
    }

    #[Test]
    public function leavesGroupsWhoseSubgroupsTypo3ReadsDifferentlyToAnAdministrator(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['subgroup' => '1,2,1'], ['uid' => 3]);
        $connection->update('be_groups', ['subgroup' => 'be_groups_4'], ['uid' => 9]);
        $classifier = $this->get(GroupClassifier::class);
        $duplicates = $classifier->classify(3);
        $ignoredEntry = $classifier->classify(9);

        self::assertNotNull($duplicates);
        self::assertNotNull($ignoredEntry);
        self::assertSame(ClassificationAction::Manual, $duplicates->action);
        self::assertStringContainsString('duplicates or entries that TYPO3 and the backend form read differently', $duplicates->reason);
        self::assertSame(ClassificationAction::Manual, $ignoredEntry->action);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function subgroupsTypo3AndTheBackendFormReadAlikeDataProvider(): array
    {
        // begroups:audit does not report these lists either (unclean-group-list).
        return [
            'spaces' => [3, ' 1, 2 ', 'change-kind role'],
            'empty entry' => [3, '1,,2', 'change-kind role'],
            'no group' => [3, '0,1,2', 'change-kind role'],
            'only no group' => [1, '0', 'change-kind db_mount'],
            'only empty entries' => [1, ', ,', 'change-kind db_mount'],
            'spaces in a group to split' => [9, ' 4', 'split acl, file_operations'],
        ];
    }

    #[Test]
    #[DataProvider('subgroupsTypo3AndTheBackendFormReadAlikeDataProvider')]
    public function convertsGroupsWhoseSubgroupsTypo3AndTheBackendFormReadAlike(int $uid, string $subgroups, string $expected): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['subgroup' => $subgroups], ['uid' => $uid]);

        $classification = $this->get(GroupClassifier::class)->classify($uid);

        self::assertNotNull($classification);
        self::assertSame($expected, $this->summarize([$classification])[$uid]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function relationsTheDataHandlerCanNotCopyDataProvider(): array
    {
        // TYPO3 and the DataHandler read these entries differently, see RelationHandler::readList().
        return [
            'page mount with leading zero' => ['db_mountpoints', '05'],
            'page mount with sign' => ['db_mountpoints', '+5'],
            'page mount with table name' => ['db_mountpoints', 'pages_1'],
            'page mount with space' => ['db_mountpoints', '1, 3'],
            'empty page mount entry' => ['db_mountpoints', '1,'],
            'file mount with leading zero' => ['file_mountpoints', '05'],
            'empty category entry' => ['category_perms', '5,'],
        ];
    }

    #[Test]
    #[DataProvider('relationsTheDataHandlerCanNotCopyDataProvider')]
    public function leavesGroupsWhoseRelationsTheDataHandlerCanNotCopyToAnAdministrator(string $fieldName, string $value): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', [$fieldName => $value], ['uid' => 2]);

        $classification = $this->get(GroupClassifier::class)->classify(2);

        self::assertNotNull($classification);
        self::assertSame(ClassificationAction::Manual, $classification->action);
        self::assertStringStartsWith(sprintf('The field "%s" contains entries ("%s") that the DataHandler cannot copy into a building block, ', $fieldName, $value), $classification->reason);
    }

    #[Test]
    public function explainsWhatToDoWithGroupsThatMountTheRoot(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['db_mountpoints' => '0'], ['uid' => 2]);

        $classification = $this->get(GroupClassifier::class)->classify(2);

        self::assertSame(ClassificationAction::Manual, $classification?->action);
        self::assertSame(
            'Mounts the root of the page tree (page mount "0"), which the DataHandler cannot copy into a building block. Keep the group classic, or replace the root by the pages on the top level of the page tree.',
            $classification->reason,
        );
    }

    #[Test]
    public function splitsGroupsWithNegativePageMounts(): void
    {
        // The DataHandler keeps negative page mounts, and so does TYPO3.
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['db_mountpoints' => '1,-3'], ['uid' => 2]);

        self::assertSame(ClassificationAction::Split, $this->get(GroupClassifier::class)->classify(2)?->action);
    }

    #[Test]
    public function treatsTheRootPageMountAsPermission(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['db_mountpoints' => '0'], ['uid' => 1]);

        $classification = $this->get(GroupClassifier::class)->classify(1);

        self::assertNotNull($classification);
        self::assertSame(ClassificationAction::ChangeKind, $classification->action);
        self::assertSame('db_mount', $classification->targetKind);
    }

    #[Test]
    public function ignoresGroupsThatAreNotClassicOrDeleted(): void
    {
        $classifier = $this->get(GroupClassifier::class);

        self::assertNull($classifier->classify(7));
        self::assertNull($classifier->classify(10));
        self::assertNull($classifier->classify(11));
    }

    #[Test]
    public function classifiesAGroupInASnapshotLikeInTheDatabase(): void
    {
        // A deleted user with a group must not count as direct user, a disabled one must.
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', ['uid' => 4, 'pid' => 0, 'username' => 'deleted', 'usergroup' => '1', 'deleted' => 1]);
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', ['uid' => 5, 'pid' => 0, 'username' => 'disabled', 'usergroup' => '5', 'disable' => 1]);
        $groups = [];
        foreach ($this->get(BackendGroupRepository::class)->findAllIncludingDeleted() as $group) {
            $groups[$group->getUid()] = $group;
        }
        $snapshot = new ConversionSnapshot($groups, $this->get(BackendUserRepository::class)->findAllRowsIncludingDeleted());
        $classifier = $this->get(GroupClassifier::class);

        for ($uid = 1; $uid <= 12; $uid++) {
            self::assertEquals($classifier->classify($uid), $classifier->classifyInSnapshot($uid, $snapshot), 'Group ' . $uid);
        }
        self::assertSame('change-kind db_mount', $this->summarize([$classifier->classifyInSnapshot(1, $snapshot) ?? self::fail('Group 1')])[1]);
        self::assertSame('manual', $this->summarize([$classifier->classifyInSnapshot(5, $snapshot) ?? self::fail('Group 5')])[5]);
    }

    /**
     * @param list<Classification> $classifications
     * @return array<int, string>
     */
    private function summarize(array $classifications): array
    {
        $summary = [];
        foreach ($classifications as $classification) {
            $summary[$classification->uid] = trim(sprintf(
                '%s %s',
                $classification->action->value,
                $classification->action->value === 'split'
                    ? implode(', ', array_map(static fn(Concern $concern): string => $concern->kind, $classification->concerns))
                    : $classification->targetKind,
            ));
        }
        return $summary;
    }
}
