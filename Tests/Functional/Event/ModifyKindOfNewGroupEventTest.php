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

namespace Cretection\BeGroups\Tests\Functional\Event;

use Cretection\BeGroups\DataHandling\GroupKindRules;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ModifyKindOfNewGroupEvent::class)]
#[CoversClass(GroupKindRules::class)]
final class ModifyKindOfNewGroupEventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_kind',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../DataHandling/Fixtures/GroupsAndUsers.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function listenersChooseTheKindOfNewGroupsWithoutKind(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => [
            'NEW1' => ['pid' => 0, 'title' => 'DBM_Synchronised', 'db_mountpoints' => '1', 'groupMods' => 'web_layout'],
        ]]);

        $record = $this->getRecord($dataHandler->substNEWwithIDs['NEW1'] ?? 0);
        self::assertSame('db_mount', $record['tx_begroups_kind']);
        self::assertSame('1', $record['db_mountpoints']);
        // The rules apply to the chosen kind.
        self::assertSame('', $record['groupMods']);
    }

    #[Test]
    public function rolesAndTheirNewBuildingBlocksAreEvaluatedWithTheChosenKinds(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => [
            'NEW1' => ['pid' => 0, 'title' => 'R_Synchronised', 'subgroup' => 'NEW2'],
            'NEW2' => ['pid' => 0, 'title' => 'DBM_Synchronised', 'db_mountpoints' => '1'],
        ]]);

        $roleUid = $dataHandler->substNEWwithIDs['NEW1'] ?? 0;
        $blockUid = $dataHandler->substNEWwithIDs['NEW2'] ?? 0;
        self::assertSame('role', $this->getRecord($roleUid)['tx_begroups_kind']);
        self::assertSame((string)$blockUid, $this->getRecord($roleUid)['subgroup']);
        self::assertSame('db_mount', $this->getRecord($blockUid)['tx_begroups_kind']);
    }

    #[Test]
    public function kindsThatAreNotConfiguredAreIgnored(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'X_Synchronised']]]);

        self::assertSame('classic', $this->getRecord($dataHandler->substNEWwithIDs['NEW1'] ?? 0)['tx_begroups_kind']);
    }

    #[Test]
    public function anExplicitKindIsNeverReplaced(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'R_Explicit', 'tx_begroups_kind' => 'acl']]]);

        self::assertSame('acl', $this->getRecord($dataHandler->substNEWwithIDs['NEW1'] ?? 0)['tx_begroups_kind']);
    }

    /**
     * @param array<string, array<int|string, array<string, int|string>>> $datamap
     */
    private function processDatamap(array $datamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        return $dataHandler;
    }

    /**
     * @return array<string, string>
     */
    private function getRecord(int $uid): array
    {
        $record = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->select(['*'], 'be_groups', ['uid' => $uid])
            ->fetchAssociative();
        self::assertIsArray($record);
        return DatabaseRow::fromArray($record)->values;
    }
}
