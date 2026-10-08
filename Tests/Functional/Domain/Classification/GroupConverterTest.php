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

use Cretection\BeGroups\Domain\Classification\ConversionResult;
use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(GroupConverter::class)]
#[CoversClass(ConversionResult::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(BackendGroupRepository::class)]
final class GroupConverterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function changesTheKindOfAGroupWithOneConcern(): void
    {
        $result = $this->get(GroupConverter::class)->changeKind(1);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame(['tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '1'], $this->getGroup(1, ['tx_begroups_kind', 'db_mountpoints']));
    }

    #[Test]
    public function changesGroupsThatOnlyCombineOtherGroupsIntoRoles(): void
    {
        $result = $this->get(GroupConverter::class)->changeKind(3);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame(['tx_begroups_kind' => 'role', 'subgroup' => '1,2'], $this->getGroup(3, ['tx_begroups_kind', 'subgroup']));
    }

    #[Test]
    public function doesNotChangeTheKindOfGroupsThatNeedASplitOrADecision(): void
    {
        $converter = $this->get(GroupConverter::class);

        self::assertSame(ConversionStatus::Skipped, $converter->changeKind(2)->status);
        self::assertSame(ConversionStatus::Skipped, $converter->changeKind(4)->status);
        self::assertSame(ConversionStatus::Skipped, $converter->changeKind(7)->status);
        self::assertSame('classic', $this->getGroup(2, ['tx_begroups_kind'])['tx_begroups_kind']);
    }

    #[Test]
    public function splitsAGroupIntoBuildingBlocksAndARoleWithTheSameUid(): void
    {
        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame('The group is now a role with 5 new building block(s).', $result->message);
        // Group 7 grants the same file operations, but is never shared: see GroupClassifier.
        [$pageGroupUid, $aclUid, $mountUid, $fileOperationsUid, $tsConfigUid] = $result->createdBlockUids;
        self::assertSame(['title' => 'PG_Editors', 'tx_begroups_kind' => 'page_group', 'groupMods' => '', 'db_mountpoints' => ''], $this->getGroup($pageGroupUid, ['title', 'tx_begroups_kind', 'groupMods', 'db_mountpoints']));
        self::assertSame(['title' => 'FO_Editors', 'file_permissions' => 'readFile,writeFile'], $this->getGroup($fileOperationsUid, ['title', 'file_permissions']));
        self::assertSame([
            'tx_begroups_kind' => 'role',
            'subgroup' => implode(',', [$pageGroupUid, $aclUid, $mountUid, $fileOperationsUid, $tsConfigUid]),
            'groupMods' => '',
            'db_mountpoints' => '',
            'file_permissions' => '',
            'TSconfig' => '',
        ], $this->getGroup(2, ['tx_begroups_kind', 'subgroup', 'groupMods', 'db_mountpoints', 'file_permissions', 'TSconfig']));
        self::assertSame(['title' => 'ACL_Editors', 'tx_begroups_kind' => 'acl', 'groupMods' => 'web_layout', 'hidden' => '0'], $this->getGroup($aclUid, ['title', 'tx_begroups_kind', 'groupMods', 'hidden']));
        self::assertSame(['title' => 'DBM_Editors', 'tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '1', 'file_permissions' => ''], $this->getGroup($mountUid, ['title', 'tx_begroups_kind', 'db_mountpoints', 'file_permissions']));
        self::assertSame(['title' => 'TS_Editors', 'tx_begroups_kind' => 'tsconfig', 'TSconfig' => 'options.clearCache.pages = 1'], $this->getGroup($tsConfigUid, ['title', 'tx_begroups_kind', 'TSconfig']));
        self::assertSame('2', $this->getConnectionPool()->getConnectionForTable('be_users')->select(['usergroup'], 'be_users', ['uid' => 2])->fetchOne());
    }

    #[Test]
    public function aNewPageGroupOwnsThePagesTheUsersCreate(): void
    {
        // Administrators may create pages anywhere; their groups are resolved all the same.
        $this->getConnectionPool()->getConnectionForTable('be_users')->update('be_users', ['admin' => 1], ['uid' => 2]);
        $this->setUpBackendUser(2);
        self::assertSame(2, $this->createPageAndGetItsGroup());

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        $pageGroupUid = $result->createdBlockUids[0];
        self::assertSame('page_group', $this->getGroup($pageGroupUid, ['tx_begroups_kind'])['tx_begroups_kind']);
        $this->setUpBackendUser(2);
        self::assertSame($pageGroupUid, $this->createPageAndGetItsGroup());
    }

    #[Test]
    public function aGroupOnACycleGetsAPageGroupAsOwnerOfNewPages(): void
    {
        // Entered through group 3, TYPO3 skips the subgroup 3 of group 2, so group 2 is resolved first.
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['subgroup' => '3'], ['uid' => 2]);
        $connection->update('be_groups', ['subgroup' => '2'], ['uid' => 3]);
        $this->getConnectionPool()->getConnectionForTable('be_users')->update('be_users', ['usergroup' => '3'], ['uid' => 2]);

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame('page_group', $this->getGroup($result->createdBlockUids[0], ['tx_begroups_kind'])['tx_begroups_kind']);
        self::assertSame(implode(',', [3, ...$result->createdBlockUids]), $this->getGroup(2, ['subgroup'])['subgroup']);
    }

    #[Test]
    public function appendsTheBuildingBlocksAfterTheSubgroupsAndKeepsTheGroupHidden(): void
    {
        $result = $this->get(GroupConverter::class)->split(9);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        [$aclUid, $fileOperationsUid] = $result->createdBlockUids;
        self::assertSame(['subgroup' => implode(',', [4, $aclUid, $fileOperationsUid]), 'hidden' => '1'], $this->getGroup(9, ['subgroup', 'hidden']));
        // Only reachable through the hidden role; showing the role again restores its permissions.
        self::assertSame('0', $this->getGroup($aclUid, ['hidden'])['hidden']);
        self::assertSame('0', $this->getGroup($fileOperationsUid, ['hidden'])['hidden']);
    }

    #[Test]
    public function doesNotConvertGroupsWhoseSubgroupsTypo3ReadsDifferently(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['subgroup' => '4,4'], ['uid' => 9]);
        $connection->update('be_groups', ['subgroup' => 'be_groups_1'], ['uid' => 3]);
        $converter = $this->get(GroupConverter::class);

        self::assertSame(ConversionStatus::Skipped, $converter->split(9)->status);
        self::assertSame(ConversionStatus::Skipped, $converter->changeKind(3)->status);
        self::assertSame(['subgroup' => 'be_groups_1', 'tx_begroups_kind' => 'classic'], $this->getGroup(3, ['subgroup', 'tx_begroups_kind']));
    }

    #[Test]
    public function keepsTheRootPageMount(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['db_mountpoints' => '0'], ['uid' => 1]);
        $connection->update('be_groups', ['db_mountpoints' => '0'], ['uid' => 2]);
        $converter = $this->get(GroupConverter::class);

        self::assertSame(ConversionStatus::Converted, $converter->changeKind(1)->status);
        self::assertSame(['tx_begroups_kind' => 'db_mount', 'db_mountpoints' => '0'], $this->getGroup(1, ['tx_begroups_kind', 'db_mountpoints']));
        // The DataHandler cannot store page mount "0" in a new building block.
        self::assertSame(ConversionStatus::Skipped, $converter->split(2)->status);
    }

    #[Test]
    public function splitsGroupsAssignedToUsersDirectly(): void
    {
        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame(
            ['tx_begroups_kind' => 'role', 'subgroup' => implode(',', $result->createdBlockUids), 'db_mountpoints' => ''],
            $this->getGroup(6, ['tx_begroups_kind', 'subgroup', 'db_mountpoints']),
        );
    }

    private function createPageAndGetItsGroup(): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => ['NEW1' => ['pid' => 0, 'title' => 'New page']]], []);
        $dataHandler->process_datamap();
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $groupUid = $queryBuilder->select('perms_groupid')->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($dataHandler->substNEWwithIDs['NEW1'] ?? 0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        return is_numeric($groupUid) ? (int)$groupUid : 0;
    }

    /**
     * @param list<string> $fields
     * @return array<string, string>
     */
    private function getGroup(int $uid, array $fields): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$fields)
            ->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row);
        return array_map(static fn(mixed $value): string => is_scalar($value) ? (string)$value : '', $row);
    }
}
