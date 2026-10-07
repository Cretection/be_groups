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
            'warning be_users:4 user-permissions',
            'warning be_users:5 user-ignores-group-mounts',
            'warning be_users:7 user-permissions',
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
        self::assertStringContainsString(': 1.', $messages['be_groups:6'][0]);
        self::assertStringEndsWith(': TSconfig. Saving the group removes them.', $messages['be_groups:7'][0]);
        self::assertStringContainsString('group 4 "Classic"', $messages['be_groups:8'][0]);
        self::assertStringContainsString('group 99, which does not exist', $messages['be_groups:8'][1]);
        self::assertStringContainsString('group 3 "Role ok"', $messages['be_groups:8'][2]);
        self::assertStringEndsWith(': groupMods. Saving the group removes them.', $messages['be_groups:9'][0]);
        self::assertStringContainsString('building block 1 "ACL ok"', $messages['be_users:3'][0]);
        self::assertStringContainsString(': file_permissions.', $messages['be_users:4'][0]);
        self::assertStringContainsString(': allowed_languages.', $messages['be_users:7'][0]);
    }

    #[Test]
    public function marksDisabledRecordsAndSkipsDeletedRecordsAndAdministrators(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $titles = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $titles[$finding->table . ':' . $finding->uid] = $finding->title;
        }

        self::assertSame('ACL hidden (disabled)', $titles['be_groups:11']);
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
