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

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * With EXT:workspaces, the access to the live workspace on a user record (be_users.workspace_perms)
 * is added to that of the roles, and new users do not have it.
 */
#[CoversClass(PermissionAudit::class)]
final class PermissionAuditWithWorkspacesTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['workspaces'];

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    #[Test]
    public function reportsAccessToTheLiveWorkspaceOnUserRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/WorkspaceData.csv');

        $findings = $this->get(PermissionAudit::class)->run();

        self::assertSame(
            ['warning be_users:2 user-permissions'],
            array_map(static fn(AuditFinding $finding): string => sprintf('%s %s:%d %s', $finding->severity->value, $finding->table, $finding->uid, $finding->identifier), $findings),
        );
        self::assertStringEndsWith(': workspace_perms. Keep user records free of permissions.', $findings[0]->message);
    }

    #[Test]
    public function reportsAccessToTheLiveWorkspaceOfOtherKindsAsInEffect(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/WorkspaceData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['workspace_perms' => 1], ['uid' => 2]);

        $messages = [];
        foreach ($this->get(PermissionAudit::class)->run() as $finding) {
            $messages[$finding->table . ':' . $finding->uid][] = $finding->identifier . ': ' . $finding->message;
        }

        self::assertSame(
            ['foreign-permissions: Permissions that the kind "db_mount" does not show are in effect: workspace_perms. Saving the group removes them.'],
            $messages['be_groups:2'],
        );
    }
}
