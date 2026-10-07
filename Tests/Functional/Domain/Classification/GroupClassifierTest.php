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
use Cretection\BeGroups\Domain\Classification\Concern;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Domain\Repository\PageOwnerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(GroupClassifier::class)]
#[CoversClass(Classification::class)]
#[CoversClass(Concern::class)]
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
            2 => 'split acl, db_mount, file_operations, tsconfig',
            3 => 'change-kind role',
            4 => 'manual',
            5 => 'change-kind page_group',
            6 => 'split db_mount',
            8 => 'manual',
            9 => 'split acl, file_operations',
        ], $this->summarize($this->get(GroupClassifier::class)->classifyAll()));
    }

    #[Test]
    public function explainsWhyAGroupCanNotBeConvertedAutomatically(): void
    {
        $classifier = $this->get(GroupClassifier::class);

        self::assertSame('Grants no permissions. Choose a kind or delete the group.', $classifier->classify(4)?->reason);
        self::assertStringContainsString('workspace_perms belong to no kind', (string)$classifier->classify(8)?->reason);
        self::assertSame('Is assigned to 1 user(s) directly, so it stays their role.', $classifier->classify(6)?->reason);
    }

    #[Test]
    public function keepsTheValuesOfEveryConcern(): void
    {
        $concerns = $this->get(GroupClassifier::class)->classify(2)->concerns ?? [];

        self::assertSame([
            ['acl', ['groupMods' => 'web_layout']],
            ['db_mount', ['db_mountpoints' => '1']],
            ['file_operations', ['file_permissions' => 'readFile,writeFile']],
            ['tsconfig', ['TSconfig' => 'options.clearCache.pages = 1']],
        ], array_map(static fn(Concern $concern): array => [$concern->kind, $concern->values], $concerns));
    }

    #[Test]
    public function reusesBuildingBlocksThatGrantExactlyTheSameExceptForTsConfig(): void
    {
        $classifier = $this->get(GroupClassifier::class);

        self::assertSame([null, null, 7, null], array_map(
            static fn(Concern $concern): ?int => $concern->reusableBlockUid,
            $classifier->classify(2)->concerns ?? [],
        ));
        // Group 9 is hidden and grants other file operations.
        self::assertSame([null, null], array_map(
            static fn(Concern $concern): ?int => $concern->reusableBlockUid,
            $classifier->classify(9)->concerns ?? [],
        ));
    }

    #[Test]
    public function neverSharesTheFirstBuildingBlockOfAGroupWithoutSubgroups(): void
    {
        // Group 6 grants the same page mount as group 1, which is a building block now and no first member.
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['tx_begroups_kind' => 'db_mount'], ['uid' => 1]);
        $connection->update('be_groups', ['subgroup' => '2,1'], ['uid' => 3]);

        $classifier = $this->get(GroupClassifier::class);
        $classification = $classifier->classify(6);

        self::assertNotNull($classification);
        self::assertSame('db_mount', $classification->concerns[0]->kind);
        self::assertNull($classification->concerns[0]->reusableBlockUid);
        // Not being the first building block, the page mount of group 2 reuses group 1.
        self::assertSame(1, $classifier->classify(2)?->concerns[1]->reusableBlockUid);
    }

    #[Test]
    public function neverReusesBuildingBlocksThatAreTheFirstMemberOfAGroup(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['subgroup' => '7,12'], ['uid' => 10]);

        $classification = $this->get(GroupClassifier::class)->classify(2);

        self::assertNotNull($classification);
        self::assertSame('file_operations', $classification->concerns[2]->kind);
        self::assertNull($classification->concerns[2]->reusableBlockUid);
    }

    #[Test]
    public function ignoresGroupsThatAreNotClassicOrDeleted(): void
    {
        $classifier = $this->get(GroupClassifier::class);

        self::assertNull($classifier->classify(7));
        self::assertNull($classifier->classify(10));
        self::assertNull($classifier->classify(11));
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
