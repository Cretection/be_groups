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
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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
        self::assertCount(3, $result->createdBlockUids);
        self::assertSame([7], $result->reusedBlockUids);
        [$aclUid, $mountUid, $tsConfigUid] = $result->createdBlockUids;
        self::assertSame([
            'tx_begroups_kind' => 'role',
            'subgroup' => implode(',', [$aclUid, $mountUid, 7, $tsConfigUid]),
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
    public function appendsTheBuildingBlocksAfterTheSubgroupsAndKeepsTheHiddenState(): void
    {
        $result = $this->get(GroupConverter::class)->split(9);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        [$aclUid, $fileOperationsUid] = $result->createdBlockUids;
        self::assertSame(implode(',', [4, $aclUid, $fileOperationsUid]), $this->getGroup(9, ['subgroup'])['subgroup']);
        self::assertSame('1', $this->getGroup($aclUid, ['hidden'])['hidden']);
        self::assertSame('1', $this->getGroup($fileOperationsUid, ['hidden'])['hidden']);
    }

    #[Test]
    public function splitsGroupsAssignedToUsersDirectly(): void
    {
        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame(
            ['tx_begroups_kind' => 'role', 'subgroup' => (string)$result->createdBlockUids[0], 'db_mountpoints' => ''],
            $this->getGroup(6, ['tx_begroups_kind', 'subgroup', 'db_mountpoints']),
        );
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
