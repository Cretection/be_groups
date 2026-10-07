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

use Cretection\BeGroups\DataHandling\UidList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Upgrades\KindMigration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(KindMigration::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(UidList::class)]
#[CoversClass(GroupKind::class)]
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
    public function migratesLegacyGroupsWithoutLosingPermissions(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyGroups.csv');
        $subject = $this->get(KindMigration::class);
        self::assertTrue($subject->updateNecessary());

        self::assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/MigratedGroups.csv');
        self::assertFalse($subject->updateNecessary());
    }
}
