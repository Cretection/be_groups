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

use Cretection\BeGroups\Command\AuditCommand;
use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(AuditCommand::class)]
#[CoversClass(PermissionAudit::class)]
#[CoversClass(AuditFinding::class)]
final class AuditCommandTest extends FunctionalTestCase
{
    private const FIXTURES = __DIR__ . '/../Domain/Audit/Fixtures/';

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    #[Test]
    public function succeedsWhenEverythingFollowsTheRoleModel(): void
    {
        $this->importCSVDataSet(self::FIXTURES . 'CleanData.csv');
        $commandTester = $this->createCommandTester();

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--fail-on-warnings' => true]));
        self::assertStringContainsString('Everything follows the role model.', $commandTester->getDisplay());
    }

    #[Test]
    public function failsOnErrorsAndListsTheFindings(): void
    {
        $this->importCSVDataSet(self::FIXTURES . 'AuditData.csv');
        $commandTester = $this->createCommandTester();

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('be_groups:5 Legacy META', $display);
        self::assertStringContainsString('unknown-kind', $display);
        self::assertStringContainsString('9 error(s), 4 warning(s).', $display);
    }

    #[Test]
    public function failsOnWarningsOnlyWhenRequested(): void
    {
        $this->importCSVDataSet(self::FIXTURES . 'CleanData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_users')->update('be_users', ['options' => 0], ['uid' => 2]);
        $commandTester = $this->createCommandTester();

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('0 error(s), 1 warning(s).', $commandTester->getDisplay());
        self::assertSame(Command::FAILURE, $commandTester->execute(['--fail-on-warnings' => true]));
    }

    #[Test]
    public function printsTheFindingsAsJson(): void
    {
        $this->importCSVDataSet(self::FIXTURES . 'CleanData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_users')->update('be_users', ['options' => 0], ['uid' => 2]);
        $commandTester = $this->createCommandTester();

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--format' => 'json']));
        self::assertJsonStringEqualsJsonString(
            (string)json_encode([
                'errors' => 0,
                'warnings' => 1,
                'findings' => [[
                    'severity' => 'warning',
                    'identifier' => 'user-ignores-group-mounts',
                    'table' => 'be_users',
                    'uid' => 2,
                    'title' => 'editor',
                    'message' => 'The user does not use all page tree or file mounts of their groups (option "Mount from groups").',
                ]],
            ]),
            $commandTester->getDisplay(),
        );
    }

    #[Test]
    public function printsTitlesAsPlainText(): void
    {
        $this->importCSVDataSet(self::FIXTURES . 'CleanData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->update('be_groups', ['title' => "<fg=red>Red</>\e[2J\u{9B}2J", 'tx_begroups_kind' => 'classic'], ['uid' => 1]);
        $commandTester = $this->createCommandTester();

        $commandTester->execute([], ['decorated' => true]);

        $display = $commandTester->getDisplay();
        self::assertStringContainsString('<fg=red>Red</>', $display);
        self::assertStringNotContainsString("\e[2J", $display);
        self::assertStringNotContainsString("\u{9B}", $display);
    }

    #[Test]
    public function rejectsAnUnknownFormat(): void
    {
        $commandTester = $this->createCommandTester();

        self::assertSame(Command::INVALID, $commandTester->execute(['--format' => 'xml']));
        self::assertStringContainsString('"--format" must be "text" or "json"', $commandTester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester($this->get(AuditCommand::class));
    }
}
