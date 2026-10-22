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

namespace Cretection\BeGroups\Tests\Functional\Documentation;

use Cretection\BeGroups\DataHandling\GroupKindRules;
use Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The event chooses the kind of every new group without a valid kind, as the manual says
 * (Developer, "Choosing the kind of new groups"): an empty kind or one that is not configured
 * is ignored and logged as user error, and the listener decides as for a group without kind.
 */
#[CoversClass(ModifyKindOfNewGroupEvent::class)]
#[CoversClass(GroupKindRules::class)]
final class KindOfNewGroupsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_kind',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DefaultsOfNewRecords.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKindDataProvider(): array
    {
        return [
            'empty' => [''],
            'not configured' => ['kind_that_does_not_exist'],
        ];
    }

    #[Test]
    #[DataProvider('invalidKindDataProvider')]
    public function listenersChooseTheKindOfNewGroupsWhoseKindIsIgnored(string $kind): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'R_Synchronised', 'tx_begroups_kind' => $kind]]], []);
        $dataHandler->process_datamap();
        $uid = $dataHandler->substNEWwithIDs['NEW1'] ?? null;
        self::assertIsInt($uid);

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('be_groups');
        $storedKind = $queryBuilder->select('tx_begroups_kind')->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        self::assertSame('role', $storedKind);

        // Logged as user error (error 1), and nothing else is logged by the rules (type 4)
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_log');
        $logEntries = $queryBuilder->select('error', 'details')->from('sys_log')
            ->where($queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter(4, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();
        self::assertCount(1, $logEntries);
        self::assertEquals(1, $logEntries[0]['error'] ?? null);
        self::assertSame('{record}: The unknown kind "{kind}" has been ignored.', $logEntries[0]['details'] ?? null);
    }
}
