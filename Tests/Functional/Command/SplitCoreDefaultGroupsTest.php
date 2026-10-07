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
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * "setup:begroups:default" of the core creates the groups "Editor" and "Advanced Editor" by SQL,
 * without a kind. Splitting them afterwards turns them into roles with shared building blocks.
 */
#[CoversClass(SplitCommand::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(GroupConverter::class)]
final class SplitCoreDefaultGroupsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Domain/Classification/Fixtures/AdminUser.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        // Like SetupService::createBackendUserGroups() and applyPermissionPreset() of EXT:install
        $connection = $this->getConnectionPool()->getConnectionForTable('be_groups');
        $connection->insert('be_groups', ['uid' => 1, 'title' => 'Editor', 'description' => 'Editors have access to basic content element and modules in the backend.']);
        $connection->update('be_groups', [
            'db_mountpoints' => '1',
            'file_mountpoints' => '1',
            'groupMods' => 'web_layout,records,media_management',
            'tables_select' => 'pages,tt_content,sys_file',
            'tables_modify' => 'pages,tt_content,sys_file',
        ], ['uid' => 1]);
        $connection->insert('be_groups', ['uid' => 2, 'title' => 'Advanced Editor', 'description' => 'Advanced Editors have access to all content elements and non administrative modules in the backend.']);
        $connection->update('be_groups', [
            'db_mountpoints' => '1',
            'file_mountpoints' => '1',
            'groupMods' => 'web_layout,records,media_management,web_list,site_redirects',
            'tables_select' => 'pages,tt_content,sys_file,sys_redirect',
            'tables_modify' => 'pages,tt_content,sys_file,sys_redirect',
        ], ['uid' => 2]);
    }

    #[Test]
    public function turnsTheDefaultGroupsIntoRolesWithSharedBuildingBlocks(): void
    {
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--all' => true]), $commandTester->getDisplay());

        $groups = [];
        foreach ($this->getConnectionPool()->getConnectionForTable('be_groups')->select(['uid', 'title', 'tx_begroups_kind', 'subgroup'], 'be_groups', [], [], ['uid' => 'ASC'])->fetchAllAssociative() as $row) {
            $group = DatabaseRow::fromArray($row);
            $groups[] = sprintf('%d %s %s [%s]', $group->getUid(), $group->get('title'), $group->get('tx_begroups_kind'), $group->get('subgroup'));
        }
        self::assertSame([
            '1 Editor role [3,4,5]',
            '2 Advanced Editor role [6,4,5]',
            '3 ACL_Editor acl []',
            '4 DBM_Editor db_mount []',
            '5 FM_Editor file_mount []',
            '6 ACL_Advanced Editor acl []',
        ], $groups);
    }
}
