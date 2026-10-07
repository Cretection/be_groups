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
use Cretection\BeGroups\Command\ConsoleText;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(ClassifyCommand::class)]
#[CoversClass(ConsoleText::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(GroupConverter::class)]
final class ClassifyCommandTest extends FunctionalTestCase
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
    public function showsTheProposalsWithoutChangingAnythingInADryRun(): void
    {
        $commandTester = new CommandTester($this->get(ClassifyCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--dry-run' => true]));
        $display = $commandTester->getDisplay();
        self::assertStringContainsString('kind: db_mount', $display);
        self::assertStringContainsString('split: acl, db_mount, file_operations, tsconfig', $display);
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
