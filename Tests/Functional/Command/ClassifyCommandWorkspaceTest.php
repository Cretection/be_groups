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

namespace Cretection\BeGroups\Tests\Functional\Command;

use Cretection\BeGroups\Command\ClassifyCommand;
use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Workspaces refer to groups by their uid: as owners, members and persons responsible for a stage.
 * A group that grants nothing itself may exist for that alone.
 */
#[CoversClass(ClassifyCommand::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(GroupConverter::class)]
final class ClassifyCommandWorkspaceTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['workspaces'];

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Domain/Classification/Fixtures/ClassicGroups.csv');
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', ['uid' => 4, 'pid' => 0, 'username' => 'reviewer', 'usergroup' => '4,2', 'workspace_perms' => 0]);
        $this->getConnectionPool()->getConnectionForTable('sys_workspace')->insert('sys_workspace', ['uid' => 1, 'pid' => 0, 'title' => 'Draft', 'members' => 'be_groups_4']);
        $this->getConnectionPool()->getConnectionForTable('sys_workspace_stage')->insert('sys_workspace_stage', ['uid' => 1, 'pid' => 0, 'parentid' => 1, 'title' => 'Approval', 'responsible_persons' => 'be_groups_4']);
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function keepsAGroupThatGrantsNothingButMakesItsUsersMembersOfAWorkspace(): void
    {
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringNotContainsString('delete', $commandTester->getDisplay());
        self::assertStringContainsString('be_groups:4 Empty: converted The kind is now "role".', $commandTester->getDisplay());
        self::assertSame('role', $this->getConnectionPool()->getConnectionForTable('be_groups')->select(['tx_begroups_kind'], 'be_groups', ['uid' => 4])->fetchOne());
        self::assertSame('be_groups_4', $this->getConnectionPool()->getConnectionForTable('sys_workspace')->select(['members'], 'sys_workspace', ['uid' => 1])->fetchOne());
        self::assertSame('be_groups_4', $this->getConnectionPool()->getConnectionForTable('sys_workspace_stage')->select(['responsible_persons'], 'sys_workspace_stage', ['uid' => 1])->fetchOne());
        self::assertSame([], array_filter(
            $this->get(PermissionAudit::class)->run(),
            static fn(AuditFinding $finding): bool => $finding->table === 'be_groups' && $finding->uid === 4,
        ));
        // Without access to the live workspace, TYPO3 opens the first workspace the user is a member of.
        self::assertSame(1, $this->setUpBackendUser(4)->workspace);
    }
}
