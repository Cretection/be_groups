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

use Cretection\BeGroups\Command\ConsoleText;
use Cretection\BeGroups\Command\SplitCommand;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(SplitCommand::class)]
#[CoversClass(ConsoleText::class)]
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
        self::assertStringContainsString('new ACL_Editors', $display);
        self::assertStringContainsString('existing be_groups:7', $display);
        self::assertStringContainsString('the group becomes a role', $display);
        self::assertStringContainsString('3 group(s) would be split.', $display);
        self::assertSame($groupCount, $this->countGroups());
    }

    #[Test]
    public function splitsTheNamedGroups(): void
    {
        $groupCount = $this->countGroups();
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['uids' => ['2', '6']]));
        // Group 6 does not reuse the page mount block of group 2: it is the first member of both roles.
        self::assertSame($groupCount + 4, $this->countGroups());
        self::assertStringContainsString('be_groups:2 Editors: converted The group is now a role with 3 new and 1 existing building block(s).', $commandTester->getDisplay());
        self::assertStringContainsString('be_groups:6 Direct mounts: converted The group is now a role with 1 new and 0 existing building block(s).', $commandTester->getDisplay());
        self::assertStringContainsString('2 group(s) split.', $commandTester->getDisplay());
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
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::FAILURE, $commandTester->execute(['uids' => ['1', '99']]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('be_groups:1 Mounts only: skipped, Only grants permissions of the kind "db_mount".', $display);
        self::assertStringContainsString('be_groups:99: skipped, no classic group.', $display);
    }

    #[Test]
    public function requiresUidsOrAll(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::INVALID, $commandTester->execute([]));
        self::assertSame(Command::INVALID, $commandTester->execute(['uids' => ['2'], '--all' => true]));
        self::assertSame(Command::INVALID, $commandTester->execute(['uids' => ['2x']]));
    }

    private function countGroups(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('be_groups')->count('uid', 'be_groups', []);
    }
}
