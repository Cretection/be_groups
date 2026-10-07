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

/**
 * Before the database analyzer has run, the kind column still has the integer type of the
 * former extension. Writing string kinds into it would store 0, so the wizard must refuse.
 */
#[CoversClass(KindMigration::class)]
#[CoversClass(ColumnInspector::class)]
#[CoversClass(ColumnInfo::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(RelationList::class)]
final class KindMigrationWithLegacyIntegerColumnTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_legacy_integer_kind',
    ];

    #[Test]
    public function refusesToMigrateIntoTheLegacyIntegerColumn(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $connection->insert('be_groups', ['uid' => 1, 'pid' => 0, 'title' => 'META', 'tx_begroups_kind' => 3]);
        $output = new BufferedOutput();
        $subject = $this->get(KindMigration::class);
        $subject->setOutput($output);

        self::assertTrue($subject->updateNecessary());
        self::assertFalse($subject->executeUpdate());

        self::assertEquals(3, $connection->select(['tx_begroups_kind'], 'be_groups', ['uid' => 1])->fetchOne());
        self::assertStringContainsString('Analyze Database Structure', $output->fetch());
    }
}
