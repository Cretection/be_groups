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

use Cretection\BeGroups\Command\SplitCommand;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use Cretection\BeGroups\Utility\ConsoleTextUtility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(SplitCommand::class)]
#[CoversClass(ConsoleTextUtility::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(GroupConverter::class)]
final class SplitCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Domain/Classification/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function showsThePlanWithoutChangingAnythingInADryRun(): void
    {
        $groupCount = $this->countGroups();
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--all' => true, '--dry-run' => true]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('new ACL: Editors', $display);
        self::assertStringContainsString('new FO: Editors', $display);
        self::assertMatchesRegularExpression('/new PG: Editors +no permissions, owner group of new pages/', $display);
        self::assertStringContainsString('the group becomes a role', $display);
        self::assertStringContainsString('3 group(s) would be split.', $display);
        self::assertSame($groupCount, $this->countGroups());
    }

    #[Test]
    public function explainsThePageGroupInTheHelp(): void
    {
        self::assertStringContainsString(
            'a new page group without permissions becomes its first member and takes over this task',
            $this->get(SplitCommand::class)->getHelp(),
        );
    }

    #[Test]
    public function keepsThePlanReadableInATerminal(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', [
            'title' => 'Editors of the press releases <fg=red>and</> news',
            'tables_select' => 'pages,tt_content',
            'tables_modify' => 'pages,tt_content',
            'pagetypes_select' => '1,254',
            'non_exclude_fields' => 'pages:title',
            'explicit_allowdeny' => 'tt_content:CType:text',
            'mfa_providers' => 'totp',
        ], ['uid' => 2]);
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        $commandTester->execute(['--all' => true, '--dry-run' => true]);

        $display = $commandTester->getDisplay();
        foreach (explode("\n", $display) as $line) {
            self::assertLessThanOrEqual(120, mb_strlen($line), $line);
        }
        // Wrapped cells stay plain text.
        self::assertStringContainsString('<fg=red>and</>', $display);
        self::assertStringContainsString('explicit_allowdeny', $display);
    }

    #[Test]
    public function splitsTheNamedGroups(): void
    {
        $groupCount = $this->countGroups();
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['uids' => ['2', '6']]));
        // Both groups have no subgroups, so each gets a page group as owner of new pages.
        self::assertSame($groupCount + 7, $this->countGroups());
        self::assertStringContainsString('be_groups:2 Editors: converted The group is now a role with 5 new building block(s).', $commandTester->getDisplay());
        self::assertStringContainsString('be_groups:6 Direct mounts: converted The group is now a role with 2 new building block(s).', $commandTester->getDisplay());
        self::assertStringContainsString('2 group(s) split.', $commandTester->getDisplay());
    }

    #[Test]
    public function marksDisabledGroupsInTheResult(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['uids' => ['9']]));
        self::assertStringContainsString('be_groups:9 Hidden editors (disabled): converted', $commandTester->getDisplay());
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['hidden' => 1], ['uid' => 4]);
        $commandTester->execute(['uids' => ['4']]);
        self::assertStringContainsString('be_groups:4 Empty (disabled): skipped. Grants no permissions and', $commandTester->getDisplay());
    }

    #[Test]
    public function splitsAllGroupsThatNeedIt(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--all' => true]));
        self::assertStringContainsString('3 group(s) split.', $commandTester->getDisplay());
    }

    #[Test]
    public function failsForGroupsThatCanNotBeSplit(): void
    {
        $groupCount = $this->countGroups();
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::FAILURE, $commandTester->execute(['uids' => ['1', '10', '11', '99']]));
        $display = $commandTester->getDisplay();
        // The audit advises to convert every classic group; this one changes its kind instead.
        self::assertStringContainsString('be_groups:1 Mounts only: skipped. Only grants permissions of the kind "db_mount". begroups:classify changes its kind to "db_mount".', $display);
        self::assertStringContainsString('be_groups:10 Role: skipped. It is a group of the kind "role", not a classic group.', $display);
        self::assertStringContainsString('be_groups:11 Deleted: skipped. The group is deleted.', $display);
        self::assertStringContainsString('be_groups:99: skipped. The group does not exist.', $display);
        self::assertStringContainsString('[ERROR] None of the named groups can be split.', $display);
        self::assertStringNotContainsString('[OK]', $display);
        self::assertSame($groupCount, $this->countGroups());
    }

    #[Test]
    public function failsInADryRunIfANamedGroupCanNotBeSplit(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::FAILURE, $commandTester->execute(['uids' => ['2', '99'], '--dry-run' => true]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('1 group(s) would be split. Nothing was changed (--dry-run).', $display);
        self::assertStringContainsString('[ERROR] 1 of the named groups cannot be split, see above.', $display);
    }

    #[Test]
    public function countsTheGroupsThatWereSplitAndThoseThatWereNot(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::FAILURE, $commandTester->execute(['uids' => ['6', '99']]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('be_groups:6 Direct mounts: converted', $display);
        self::assertStringContainsString('[ERROR] 1 group(s) split, 1 not split, see above.', $display);
    }

    #[Test]
    public function splitsAGroupNamedTwiceOnce(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['uids' => ['2', '2']]));
        $display = $commandTester->getDisplay();
        self::assertSame(1, substr_count($display, 'be_groups:2 Editors: converted'));
        self::assertStringNotContainsString('skipped', $display);
        self::assertStringContainsString('1 group(s) split.', $display);
    }

    #[Test]
    public function requiresUidsOrAll(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::INVALID, $commandTester->execute([]));
        self::assertStringContainsString('Name the groups to split by uid, or use --all.', $commandTester->getDisplay());
        self::assertSame(Command::INVALID, $commandTester->execute(['uids' => ['2'], '--all' => true]));
        self::assertStringContainsString('Name the groups to split by uid or use --all, not both.', $commandTester->getDisplay());
        self::assertSame(Command::INVALID, $commandTester->execute(['uids' => ['2', '2x']]));
        self::assertStringContainsString('"2x" is no uid.', $commandTester->getDisplay());
        self::assertSame(Command::INVALID, $commandTester->execute(['uids' => ['0']]));
        self::assertStringContainsString('"0" is no uid.', $commandTester->getDisplay());
    }

    private function countGroups(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('be_groups')->count('uid', 'be_groups', []);
    }
}
