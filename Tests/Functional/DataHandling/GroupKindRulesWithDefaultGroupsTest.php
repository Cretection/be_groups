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
use Cretection\BeGroups\DataHandling\RelationsEmptiedByImport;
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

/**
 * A site gives new users a role through the TCA default of be_users.usergroup. EXT:impexp writes this
 * default instead of an empty value into the relation fields in the first run of an "Update records"
 * import (Import::getReferenceDefaultValue()).
 */
#[CoversClass(GroupKindRules::class)]
#[CoversClass(RelationsEmptiedByImport::class)]
#[CoversClass(RelationList::class)]
#[CoversClass(RuleViolationReporter::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(GroupKind::class)]
final class GroupKindRulesWithDefaultGroupsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_usergroup_default',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/GroupsAndUsers.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function storedRelationsThatBreakTheRulesSurviveAnUpdateImportThatWritesTheDefault(): void
    {
        // "mixed" has the role 6 and the building block 1, which is assigned directly
        $this->processDatamap(['be_users' => [4 => ['username' => 'mixed', 'usergroup' => '6']]], true);
        $this->processDatamap(['be_users' => [4 => ['usergroup' => 'be_groups_6,be_groups_1']]], true);

        self::assertSame('6,1', $this->getUsergroup(4));
        $logEntries = $this->get(ConnectionPool::class)->getConnectionForTable('sys_log')
            ->count('uid', 'sys_log', ['type' => 4]);
        self::assertSame(0, $logEntries);
    }

    #[Test]
    public function relationsAnImportReplacedByTheDefaultAreCheckedWhenTheyAreSetOutsideOfIt(): void
    {
        $this->processDatamap(['be_users' => [4 => ['usergroup' => '6']]], true);
        $this->processDatamap(['be_users' => [4 => ['usergroup' => '6,1']]]);

        self::assertSame('6', $this->getUsergroup(4));
    }

    /**
     * @param array<string, array<int|string, array<string, int|string>>> $datamap
     */
    private function processDatamap(array $datamap, bool $isImporting = false): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->isImporting = $isImporting;
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        return $dataHandler;
    }

    private function getUsergroup(int $uid): string
    {
        $usergroup = $this->get(ConnectionPool::class)->getConnectionForTable('be_users')
            ->select(['usergroup'], 'be_users', ['uid' => $uid])
            ->fetchOne();
        self::assertIsString($usergroup);
        return $usergroup;
    }
}
