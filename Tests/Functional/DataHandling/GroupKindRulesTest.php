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

namespace Cretection\BeGroups\Tests\Functional\DataHandling;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\DataHandling\GroupKindRules;
use Cretection\BeGroups\DataHandling\RuleViolationReporter;
use Cretection\BeGroups\DataHandling\UidList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(GroupKindRules::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(RuleViolationReporter::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(UidList::class)]
#[CoversClass(GroupKind::class)]
final class GroupKindRulesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/GroupsAndUsers.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function newBuildingBlockOnlyKeepsTheFieldsOfItsKind(): void
    {
        $uid = $this->createGroup([
            'title' => 'ACL with foreign values',
            'tx_begroups_kind' => 'acl',
            'groupMods' => 'web_layout',
            'db_mountpoints' => '1',
            'TSconfig' => 'options.clearCache.all = 1',
        ]);

        $group = $this->getRecord('be_groups', $uid);
        self::assertSame('web_layout', $group['groupMods']);
        self::assertSame('', $group['db_mountpoints']);
        self::assertSame('', $group['TSconfig']);
    }

    #[Test]
    public function newBuildingBlockDoesNotReceiveTheDefaultFilePermissions(): void
    {
        $uid = $this->createGroup(['title' => 'DBM site B', 'tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '1']);

        $group = $this->getRecord('be_groups', $uid);
        self::assertSame('', $group['file_permissions']);
        self::assertSame('1', $group['db_mountpoints']);
    }

    #[Test]
    public function fileOperationsBlockKeepsItsFilePermissions(): void
    {
        $uid = $this->createGroup(['title' => 'FO write', 'tx_begroups_kind' => 'file_operations', 'file_permissions' => 'readFile,writeFile']);

        self::assertSame('readFile,writeFile', $this->getRecord('be_groups', $uid)['file_permissions']);
    }

    #[Test]
    public function classicGroupKeepsAllFields(): void
    {
        $uid = $this->createGroup(['title' => 'Classic', 'tx_begroups_kind' => 'classic', 'groupMods' => 'web_layout', 'db_mountpoints' => '1']);

        $group = $this->getRecord('be_groups', $uid);
        self::assertSame('web_layout', $group['groupMods']);
        self::assertSame('1', $group['db_mountpoints']);
    }

    #[Test]
    public function roleOnlyAcceptsBuildingBlocksAsSubgroups(): void
    {
        $this->writeGroup(6, ['subgroup' => '1,2,3,4,5,7,6']);

        self::assertSame('1,2,3,4', $this->getRecord('be_groups', 6)['subgroup']);
    }

    #[Test]
    public function roleCanNotCarryOwnPermissions(): void
    {
        $this->writeGroup(6, ['groupMods' => 'web_list']);

        self::assertSame('', $this->getRecord('be_groups', 6)['groupMods']);
    }

    #[Test]
    public function renamingRoleWithoutSubgroupFieldKeepsItsComposition(): void
    {
        $this->writeGroup(6, ['title' => 'R editor (renamed)']);

        self::assertSame('1,2', $this->getRecord('be_groups', 6)['subgroup']);
    }

    #[Test]
    public function changingTheKindEmptiesTheFieldsOfThePreviousKind(): void
    {
        $this->writeGroup(1, ['tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '1']);

        $group = $this->getRecord('be_groups', 1);
        self::assertSame('db_mount', $group['tx_begroups_kind']);
        self::assertSame('', $group['groupMods']);
        self::assertSame('1', $group['db_mountpoints']);
    }

    #[Test]
    public function unknownKindIsIgnored(): void
    {
        $this->writeGroup(2, ['tx_begroups_kind' => '<script>alert(1)</script>']);

        self::assertSame('db_mount', $this->getRecord('be_groups', 2)['tx_begroups_kind']);
        $logData = $this->get(ConnectionPool::class)->getConnectionForTable('sys_log')
            ->select(['log_data'], 'sys_log', ['type' => 4])
            ->fetchOne();
        self::assertIsString($logData);
        self::assertStringNotContainsString('<script>', $logData);
    }

    #[Test]
    public function valuesWrittenAroundTheFormAreRemovedOnNextSave(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['groupMods' => 'tools_toolsmaintenance'], ['uid' => 2]);

        $this->writeGroup(2, ['title' => 'DBM site A (saved)']);

        self::assertSame('', $this->getRecord('be_groups', 2)['groupMods']);
    }

    #[Test]
    public function usersOnlyGetRolesAndClassicGroups(): void
    {
        $this->writeUser(2, ['usergroup' => '6,1,5']);

        self::assertSame('6,5', $this->getRecord('be_users', 2)['usergroup']);
    }

    #[Test]
    public function correctionsAreWrittenToTheSystemLog(): void
    {
        $this->writeUser(2, ['usergroup' => '5,1']);

        $logEntries = $this->get(ConnectionPool::class)->getConnectionForTable('sys_log')
            ->select(['details', 'tablename', 'recuid', 'userid'], 'sys_log', ['type' => 4])
            ->fetchAllAssociative();
        self::assertCount(1, $logEntries);
        self::assertSame('be_users', $logEntries[0]['tablename']);
        self::assertEquals(2, $logEntries[0]['recuid']);
        self::assertEquals(1, $logEntries[0]['userid']);
    }

    /**
     * @param array<string, string> $fields
     */
    private function createGroup(array $fields): int
    {
        $dataHandler = $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0] + $fields]]);
        $uid = $dataHandler->substNEWwithIDs['NEW1'] ?? null;
        self::assertIsInt($uid);
        return $uid;
    }

    /**
     * @param array<string, string> $fields
     */
    private function writeGroup(int $uid, array $fields): void
    {
        $this->processDatamap(['be_groups' => [$uid => $fields]]);
    }

    /**
     * @param array<string, string> $fields
     */
    private function writeUser(int $uid, array $fields): void
    {
        $this->processDatamap(['be_users' => [$uid => $fields]]);
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
     * @return array<string, mixed>
     */
    private function getRecord(string $table, int $uid): array
    {
        $record = $this->get(ConnectionPool::class)->getConnectionForTable($table)
            ->select(['*'], $table, ['uid' => $uid])
            ->fetchAssociative();
        self::assertIsArray($record);
        return $record;
    }
}
