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
use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\DataHandling\RuleViolationReporter;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
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
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(RuleViolationReporter::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(RelationList::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(GroupKind::class)]
final class GroupKindRulesWithClassicGroupsDisabledTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'be_groups' => [
                'allowClassicGroups' => '0',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/GroupsAndUsers.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function classicGroupsCanNotBeCreatedWhileDisabled(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'Classic', 'tx_begroups_kind' => 'classic']]]);

        self::assertArrayNotHasKey('NEW1', $dataHandler->substNEWwithIDs);
    }

    #[Test]
    public function newGroupsWithoutKindAreRejectedWhileDisabled(): void
    {
        $dataHandler = $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'Imported without kind', 'groupMods' => 'web_layout']]]);

        self::assertArrayNotHasKey('NEW1', $dataHandler->substNEWwithIDs);
    }

    #[Test]
    public function groupsCanNotBeChangedToClassicWhileDisabled(): void
    {
        $this->writeGroup(2, ['tx_begroups_kind' => 'classic']);

        self::assertSame('db_mount', $this->getRecord('be_groups', 2)['tx_begroups_kind']);
    }

    #[Test]
    public function aRejectedChangeToClassicKeepsTheRulesOfTheCurrentKind(): void
    {
        $this->writeGroup(2, ['tx_begroups_kind' => 'classic', 'groupMods' => 'web_layout']);

        $record = $this->getRecord('be_groups', 2);
        self::assertSame('db_mount', $record['tx_begroups_kind']);
        self::assertSame('', $record['groupMods']);
        self::assertSame(
            [
                ['error' => 1, 'details' => 'Changing groups to kind "classic" is disabled. The group keeps its kind.'],
                ['error' => 0, 'details' => 'Fields not belonging to kind "{kind}" have been emptied: {fields}'],
            ],
            $this->getExtensionLogEntries(),
        );
    }

    #[Test]
    public function creatingAClassicGroupIsLoggedAsUserErrorWhileDisabled(): void
    {
        $this->processDatamap(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'Classic', 'tx_begroups_kind' => 'classic']]]);

        self::assertSame(
            [['error' => 1, 'details' => 'Creating groups of kind "classic" is disabled. The group has not been created.']],
            $this->getExtensionLogEntries(),
        );
    }

    #[Test]
    public function otherKindsCanStillBeChangedWhileDisabled(): void
    {
        $this->writeGroup(1, ['tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '1']);

        $record = $this->getRecord('be_groups', 1);
        self::assertSame('db_mount', $record['tx_begroups_kind']);
        self::assertSame('1', $record['db_mountpoints']);
    }

    #[Test]
    public function existingClassicGroupsCanStillBeSavedWhileDisabled(): void
    {
        $this->writeGroup(5, ['title' => 'Classic all-in-one (saved)']);

        $record = $this->getRecord('be_groups', 5);
        self::assertSame('Classic all-in-one (saved)', $record['title']);
        self::assertSame('classic', $record['tx_begroups_kind']);
        self::assertSame('web_layout', $record['groupMods']);
        self::assertSame([], $this->getExtensionLogEntries());
    }

    #[Test]
    public function existingClassicAssignmentsAreKeptWhileDisabled(): void
    {
        $this->writeUser(2, ['realName' => 'Editor', 'usergroup' => '5,6']);

        self::assertSame('5,6', $this->getRecord('be_users', 2)['usergroup']);
    }

    #[Test]
    public function newClassicAssignmentsAreRejectedWhileDisabled(): void
    {
        $this->writeUser(1, ['usergroup' => '6,5']);

        self::assertSame('6', $this->getRecord('be_users', 1)['usergroup']);
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
     * @return list<array{error: int, details: string}> the entries the extension wrote to the system log
     */
    private function getExtensionLogEntries(): array
    {
        $rows = $this->get(ConnectionPool::class)->getConnectionForTable('sys_log')
            ->select(['error', 'details'], 'sys_log', ['type' => 4], [], ['uid' => 'ASC'])
            ->fetchAllAssociative();
        return array_map(
            static fn(array $row): array => ['error' => is_numeric($row['error']) ? (int)$row['error'] : -1, 'details' => is_string($row['details']) ? $row['details'] : ''],
            $rows,
        );
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
