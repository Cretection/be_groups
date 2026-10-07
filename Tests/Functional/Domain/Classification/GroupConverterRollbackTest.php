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

use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Another extension changes permissions while groups are saved.
 */
#[CoversClass(GroupConverter::class)]
final class GroupConverterRollbackTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_rewrite',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function rollsBackTheSplitIfABuildingBlockWouldGrantSomethingElse(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $groupCount = $connection->count('uid', 'be_groups', []);

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame($groupCount, $connection->count('uid', 'be_groups', []));
        self::assertSame(
            ['tx_begroups_kind' => 'classic', 'subgroup' => '', 'db_mountpoints' => '1'],
            $connection->select(['tx_begroups_kind', 'subgroup', 'db_mountpoints'], 'be_groups', ['uid' => 2])->fetchAssociative(),
        );
    }

    #[Test]
    public function reportsDatabaseErrorsAndRollsBack(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['title' => 'Database error'], ['uid' => 2]);
        $groupCount = $connection->count('uid', 'be_groups', []);

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertStringStartsWith('Database error, nothing was changed: ', $result->message);
        self::assertSame($groupCount, $connection->count('uid', 'be_groups', []));
        self::assertSame('classic', $connection->select(['tx_begroups_kind'], 'be_groups', ['uid' => 2])->fetchOne());
    }

    #[Test]
    public function rollsBackAKindChangeIfAPermissionWouldChange(): void
    {
        $result = $this->get(GroupConverter::class)->changeKind(1);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The field "db_mountpoints" would change; nothing was changed.', $result->message);
        self::assertSame(
            ['tx_begroups_kind' => 'classic', 'db_mountpoints' => '1'],
            $this->getConnectionPool()->getConnectionForTable('be_groups')
                ->select(['tx_begroups_kind', 'db_mountpoints'], 'be_groups', ['uid' => 1])->fetchAssociative(),
        );
    }
}
