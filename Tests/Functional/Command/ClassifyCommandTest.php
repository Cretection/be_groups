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
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use Cretection\BeGroups\Utility\ConsoleTextUtility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ClassifyCommand::class)]
#[CoversClass(ConsoleTextUtility::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(GroupConverter::class)]
final class ClassifyCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_rewrite',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Domain/Classification/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function showsTheProposalsWithoutChangingAnythingInADryRun(): void
    {
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--dry-run' => true]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('kind: db_mount', $display);
        self::assertStringContainsString('split: page_group, acl,', $display);
        self::assertStringContainsString('3 group(s) would change their kind.', $display);
        self::assertStringContainsString('3 group(s) can be split', $display);
        self::assertSame(['classic', 'classic', 'classic'], $this->getKinds([1, 3, 5]));
    }

    #[Test]
    public function changesTheKindOfAllGroupsThatServeOnePurpose(): void
    {
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertSame(['db_mount', 'classic', 'role', 'classic', 'page_group', 'classic'], $this->getKinds([1, 2, 3, 4, 5, 6]));
        self::assertStringContainsString('be_groups:1 Mounts only: converted', $commandTester->getDisplay());
        self::assertStringContainsString('[OK] 3 group(s) changed their kind.', $commandTester->getDisplay());
    }

    #[Test]
    public function saysSoIfNoKindCanBeChanged(): void
    {
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));
        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('[OK] There is no group whose kind can be changed.', $commandTester->getDisplay());
    }

    #[Test]
    public function countsTheGroupsWhoseKindCouldNotBeChanged(): void
    {
        // The test extension changes the page mounts of the group while saving.
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['description' => 'rewrite:page-mount'], ['uid' => 1]);
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::FAILURE, $commandTester->execute([]));
        self::assertStringContainsString('be_groups:1 Mounts only: failed', $commandTester->getDisplay());
        self::assertStringContainsString('[ERROR] 2 group(s) changed their kind, 1 could not be changed, see above.', $commandTester->getDisplay());
        self::assertSame(['classic', 'role', 'page_group'], $this->getKinds([1, 3, 5]));
    }

    #[Test]
    public function namesTheGroupWhoseConversionAnErrorStopped(): void
    {
        // The test extension throws an exception while the kind of the group changes.
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['description' => 'rewrite:throw'], ['uid' => 1]);
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        try {
            $commandTester->execute([]);
            self::fail('The error of the other extension is not reported.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Another extension failed while saving.', $exception->getMessage());
        }
        self::assertStringContainsString(
            'be_groups:1 Mounts only: failed An error stopped the conversion; nothing was changed. 2 further group(s) were not processed.',
            $commandTester->getDisplay(),
        );
        self::assertSame(['classic', 'classic', 'classic'], $this->getKinds([1, 3, 5]));
    }

    #[Test]
    public function marksDisabledGroupsInTheResult(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['hidden' => 1], ['uid' => 1]);
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        $commandTester->execute([]);

        self::assertStringContainsString('be_groups:1 Mounts only (disabled): converted', $commandTester->getDisplay());
    }

    #[Test]
    public function keepsTheProposalsReadableInATerminal(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['subgroup' => implode(',', array_fill(0, 30, '1,2'))], ['uid' => 3]);
        $connection->update('be_groups', ['title' => 'Editors of the press releases <fg=red>and</> news'], ['uid' => 2]);
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        $commandTester->execute(['--dry-run' => true]);

        $display = $commandTester->getDisplay();
        foreach (explode("\n", $display) as $line) {
            self::assertLessThanOrEqual(120, mb_strlen($line), $line);
        }
        // Wrapped cells stay plain text.
        self::assertStringContainsString('<fg=red>and</>', $display);
        self::assertStringContainsString('entries that TYPO3 and the backend form read', $display);
    }

    #[Test]
    public function reportsWhenThereIsNoClassicGroup(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['tx_begroups_kind' => 'acl'], ['tx_begroups_kind' => 'classic']);
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute([]));
        self::assertStringContainsString('There are no classic groups.', $commandTester->getDisplay());
    }

    /**
     * @param list<int> $uids
     * @return list<string>
     */
    private function getKinds(array $uids): array
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $kinds = [];
        foreach ($uids as $uid) {
            $kind = $connection->select(['tx_begroups_kind'], 'be_groups', ['uid' => $uid])->fetchOne();
            self::assertIsString($kind);
            $kinds[] = $kind;
        }
        return $kinds;
    }
}
