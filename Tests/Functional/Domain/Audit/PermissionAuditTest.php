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

namespace Cretection\BeGroups\Tests\Functional\Domain\Audit;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(PermissionAudit::class)]
#[CoversClass(AuditFinding::class)]
#[CoversClass(AfterAuditFindingsCollectedEvent::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(RelationList::class)]
final class PermissionAuditTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    #[Test]
    public function reportsEveryViolationOfTheRoleModel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        self::assertSame([
            'error be_groups:5 unknown-kind',
            'error be_groups:6 building-block-with-subgroups',
            'error be_groups:7 foreign-permissions',
            'error be_groups:8 role-invalid-member',
            'error be_groups:8 role-missing-member',
            'error be_groups:8 role-invalid-member',
            'error be_groups:9 foreign-permissions',
            'error be_groups:11 foreign-permissions',
            'error be_users:3 user-building-block',
            'warning be_groups:4 classic-group',
            'warning be_groups:12 unclean-group-list',
            'warning be_groups:13 unclean-group-list',
            'warning be_users:4 user-permissions',
            'warning be_users:5 user-ignores-group-mounts',
            'warning be_users:7 user-permissions',
            'warning be_users:8 unclean-group-list',
        ], array_map(self::summarize(...), $this->get(PermissionAudit::class)->run()));
    }

    #[Test]
    public function namesTheAffectedFieldsAndMembers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $messages = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $messages[$finding->table . ':' . $finding->uid][] = $finding->message;
        }

        self::assertStringContainsString('"3"', $messages['be_groups:5'][0]);
        self::assertStringEndsWith(': group 1 "ACL: ACL ok".', $messages['be_groups:6'][0]);
        self::assertStringEndsWith(': TSconfig. Saving the group removes them.', $messages['be_groups:7'][0]);
        self::assertStringContainsString('the classic group 4 "Classic";', $messages['be_groups:8'][0]);
        self::assertStringContainsString('group 99, which does not exist or is deleted', $messages['be_groups:8'][1]);
        self::assertStringContainsString('the role 3 "META: Role ok";', $messages['be_groups:8'][2]);
        self::assertStringEndsWith(': groupMods. Saving the group removes them.', $messages['be_groups:9'][0]);
        self::assertStringContainsString('building block 1 "ACL: ACL ok"', $messages['be_users:3'][0]);
        self::assertStringContainsString(': file_permissions.', $messages['be_users:4'][0]);
        self::assertStringContainsString(': allowed_languages.', $messages['be_users:7'][0]);
    }

    #[Test]
    public function namesTheUnknownKindOfMembers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['subgroup' => '1,5'], ['uid' => 3]);

        $messages = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $messages[$finding->table . ':' . $finding->uid][] = $finding->message;
        }

        self::assertSame(
            ['The role contains group 5 "Unknown kind" of the unknown kind "3"; only building blocks belong in a role.'],
            $messages['be_groups:3'],
        );
    }

    #[Test]
    public function saysThatAGroupHasNoKind(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['tx_begroups_kind' => ''], ['uid' => 5]);
        $connection->update('be_groups', ['subgroup' => '1,5'], ['uid' => 3]);

        $findings = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $findings[$finding->table . ':' . $finding->uid][] = $finding;
        }

        self::assertSame('unknown-kind', $findings['be_groups:5'][0]->identifier);
        self::assertSame(
            'The group has no kind (e.g. written by SQL). It is neither a role nor a building block; assign a kind.',
            $findings['be_groups:5'][0]->message,
        );
        self::assertSame(
            'The role contains group 5 "Unknown kind" without a kind; only building blocks belong in a role.',
            $findings['be_groups:3'][0]->message,
        );
    }

    #[Test]
    public function readsListsOfGroupsLikeTypo3(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $findings = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $findings[$finding->table . ':' . $finding->uid][] = $finding->identifier;
        }

        // TYPO3 reads "be_groups_4" (a classic group) and "be_groups_1" (a building block) as 0, which matches no group.
        self::assertSame(['unclean-group-list'], $findings['be_groups:13']);
        self::assertSame(['unclean-group-list'], $findings['be_users:8']);
    }

    #[Test]
    public function doesNotReportListsThatTypo3AndTheFormReadAlike(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $findings = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $findings[$finding->table . ':' . $finding->uid][] = $finding->identifier;
        }

        // TYPO3, the backend form and the DataHandler ignore spaces, empty entries and "0" (no group) alike.
        self::assertArrayNotHasKey('be_groups:14', $findings);
        self::assertArrayNotHasKey('be_groups:15', $findings);
        self::assertArrayNotHasKey('be_groups:16', $findings);
        self::assertArrayNotHasKey('be_users:9', $findings);
    }

    #[Test]
    public function reportsDuplicatesAndLeadingZerosBetweenSpaces(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['subgroup' => '1, 1'], ['uid' => 15]);
        $connection->update('be_groups', ['subgroup' => '0, 01'], ['uid' => 16]);

        $findings = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $findings[$finding->table . ':' . $finding->uid][] = $finding->identifier;
        }

        self::assertSame(['unclean-group-list'], $findings['be_groups:15']);
        self::assertSame(['unclean-group-list'], $findings['be_groups:16']);
    }

    #[Test]
    public function marksDisabledRecordsAndSkipsDeletedRecordsAndAdministrators(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $titles = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $titles[$finding->table . ':' . $finding->uid] = $finding->title;
        }

        // Groups are named as the backend shows them: with the prefix of their kind.
        self::assertSame('ACL: ACL hidden (disabled)', $titles['be_groups:11']);
        self::assertSame('META: Role with duplicates', $titles['be_groups:12']);
        self::assertSame('Unknown kind', $titles['be_groups:5']);
        self::assertSame('Classic', $titles['be_groups:4']);
        self::assertArrayNotHasKey('be_groups:10', $titles);
        self::assertArrayNotHasKey('be_users:1', $titles);
        self::assertArrayNotHasKey('be_users:6', $titles);
    }

    #[Test]
    public function findsNothingWhenEverythingFollowsTheRoleModel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CleanData.csv');

        self::assertSame([], $this->get(PermissionAudit::class)->run());
    }

    private static function summarize(AuditFinding $finding): string
    {
        return sprintf('%s %s:%d %s', $finding->severity->value, $finding->table, $finding->uid, $finding->identifier);
    }
}
