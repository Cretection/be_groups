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
use Cretection\BeGroups\Domain\Repository\SystemLogRepository;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Another extension changes data while groups are saved (see RewritePermissions):
 * every conversion must be rolled back.
 */
#[CoversClass(GroupConverter::class)]
#[CoversClass(SystemLogRepository::class)]
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
    public function rollsBackIfABuildingBlockWouldGrantSomethingElse(): void
    {
        $groupCount = $this->countGroups();

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame($groupCount, $this->countGroups());
        self::assertSame(['tx_begroups_kind' => 'classic', 'subgroup' => '', 'db_mountpoints' => '1'], $this->getGroup(2, ['tx_begroups_kind', 'subgroup', 'db_mountpoints']));
    }

    #[Test]
    public function reportsDatabaseErrorsAndRollsBack(): void
    {
        $this->updateGroup(2, ['title' => 'Database error']);
        $groupCount = $this->countGroups();

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertStringStartsWith('Database error, nothing was changed: ', $result->message);
        self::assertSame($groupCount, $this->countGroups());
        self::assertSame('classic', $this->getGroup(2, ['tx_begroups_kind'])['tx_begroups_kind']);
    }

    #[Test]
    public function namesTheErrorsTheDataHandlerLogged(): void
    {
        $this->updateGroup(2, ['title' => 'Unknown column']);
        $groupCount = $this->countGroups();

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Failed, $result->status);
        if ($this->getConnectionPool()->getConnectionForTable('be_groups')->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            // PostgreSQL aborts the transaction, so the DataHandler cannot log the error.
            self::assertStringStartsWith('Database error, nothing was changed: ', $result->message);
        } else {
            self::assertStringContainsString(' Logged errors: ', $result->message);
            self::assertStringContainsString('column_that_does_not_exist', $result->message);
        }
        self::assertSame($groupCount, $this->countGroups());
    }

    #[Test]
    public function rollsBackIfUsersWouldGetAnotherGroup(): void
    {
        $this->updateGroup(6, ['description' => 'rewrite:add-subgroup']);
        $groupCount = $this->countGroups();

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The permissions (groupMods) of users with the groups "6" would change; nothing was changed.', $result->message);
        self::assertSame($groupCount, $this->countGroups());
    }

    #[Test]
    public function rollsBackIfAHiddenGroupWouldGrantMoreOnceItIsShownAgain(): void
    {
        $this->updateGroup(9, ['description' => 'rewrite:add-subgroup']);

        $result = $this->get(GroupConverter::class)->split(9);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The group memberships of the group itself would change; nothing was changed.', $result->message);
        self::assertSame('classic', $this->getGroup(9, ['tx_begroups_kind'])['tx_begroups_kind']);
    }

    #[Test]
    public function rollsBackIfAnotherGroupWouldChange(): void
    {
        $this->updateGroup(6, ['description' => 'rewrite:widen-other-group']);

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The group 12 would change while saving; nothing was changed.', $result->message);
        self::assertSame('web_list', $this->getGroup(12, ['groupMods'])['groupMods']);
    }

    #[Test]
    public function rollsBackIfAUserWouldChange(): void
    {
        $this->updateGroup(6, ['description' => 'rewrite:add-group-to-user']);

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The user 2 would change while saving; nothing was changed.', $result->message);
    }

    #[Test]
    public function rollsBackIfTheGroupWouldBeShown(): void
    {
        $this->updateGroup(9, ['description' => 'rewrite:show-group']);

        $result = $this->get(GroupConverter::class)->split(9);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The field "hidden" of the group would change; nothing was changed.', $result->message);
        self::assertSame('1', $this->getGroup(9, ['hidden'])['hidden']);
    }

    #[Test]
    public function rollsBackIfAnotherGroupWouldOwnTheNewPagesOfTheUsers(): void
    {
        // Moves the new page group behind the page mount block
        $this->updateGroup(6, ['description' => 'rewrite:move-first-subgroup-last']);

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The owner group of new pages of users with the groups "6" would change; nothing was changed.', $result->message);
    }

    #[Test]
    public function rollsBackAKindChangeIfAPermissionWouldChange(): void
    {
        $this->updateGroup(1, ['description' => 'rewrite:page-mount']);

        $result = $this->get(GroupConverter::class)->changeKind(1);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame('The field "db_mountpoints" would change; nothing was changed.', $result->message);
        self::assertSame(['tx_begroups_kind' => 'classic', 'db_mountpoints' => '1'], $this->getGroup(1, ['tx_begroups_kind', 'db_mountpoints']));
    }

    /**
     * @param array<string, string> $values
     */
    private function updateGroup(int $uid, array $values): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', $values, ['uid' => $uid]);
    }

    private function countGroups(): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')->from('be_groups')->executeQuery()->fetchOne();
        return is_numeric($count) ? (int)$count : 0;
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
