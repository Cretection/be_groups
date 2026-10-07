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

namespace Cretection\BeGroups\Tests\Functional\Upgrades;

use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Upgrades\ColumnInfo;
use Cretection\BeGroups\Upgrades\ColumnInspector;
use Cretection\BeGroups\Upgrades\KindMigration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(KindMigration::class)]
#[CoversClass(ColumnInspector::class)]
#[CoversClass(ColumnInfo::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(RelationList::class)]
final class KindMigrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_legacy_schema',
    ];

    #[Test]
    public function updateIsNotNecessaryWithoutLegacyGroups(): void
    {
        self::assertFalse($this->get(KindMigration::class)->updateNecessary());
    }

    #[Test]
    public function migratesLegacyGroupsWithoutChangingEffectivePermissions(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyGroups.csv');
        $subject = $this->get(KindMigration::class);
        $subject->setOutput(new BufferedOutput());
        self::assertTrue($subject->updateNecessary());

        self::assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/MigratedGroups.csv');
        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function reportsIneffectiveLegacyMembersAndGroupsThatBecomeClassic(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyGroups.csv');
        $output = new BufferedOutput();
        $subject = $this->get(KindMigration::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $report = $output->fetch();
        self::assertStringContainsString('Role [3] "META broken by old bug": the former form listed the groups 1, 2, 5, 4', $report);
        self::assertStringContainsString('Group [6] "Rights with hidden TSconfig"', $report);
        self::assertStringNotContainsString('Group [11]', $report);
    }

    #[Test]
    public function keepsFilePermissionsOnGroupsWhoseReferencesHaveNoRoomLeft(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyGroups.csv');
        // A list that still fits into the column (2048 characters), but has no room for another uid.
        $uids = [1];
        for ($uid = 100000; strlen(implode(',', [...$uids, $uid])) <= 2040; $uid++) {
            $uids[] = $uid;
        }
        $fullList = implode(',', $uids);
        self::assertLessThanOrEqual(2048, strlen($fullList));
        self::assertGreaterThan(2048, strlen($fullList . ',' . PHP_INT_MAX));
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['subgroup' => $fullList], ['uid' => 8]);
        $subject = $this->get(KindMigration::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $rights = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->select(['tx_begroups_kind', 'file_permissions'], 'be_groups', ['uid' => 1])
            ->fetchAssociative();
        self::assertSame(['tx_begroups_kind' => 'classic', 'file_permissions' => 'readFolder,readFile'], $rights);
        $role = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->select(['subgroup'], 'be_groups', ['uid' => 8])
            ->fetchOne();
        self::assertSame($fullList, $role);
    }
}
