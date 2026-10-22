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
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Another extension throws an exception while a group is split (see RewritePermissions).
 */
#[CoversClass(SplitCommand::class)]
#[CoversClass(GroupConverter::class)]
final class SplitCommandAbortTest extends FunctionalTestCase
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
    public function namesTheGroupWhoseSplitAnErrorStoppedAndTheGroupsLeftOut(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['description' => 'rewrite:throw'], ['uid' => 6]);
        $commandTester = new CommandTester($this->get(SplitCommand::class));

        try {
            $commandTester->execute(['uids' => ['6', '2']]);
            self::fail('The error of the other extension is not reported.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Another extension failed while saving.', $exception->getMessage());
        }
        self::assertStringContainsString(
            'be_groups:6 Direct mounts: failed An error stopped the conversion; nothing was changed. 1 further group(s) were not processed.',
            $commandTester->getDisplay(),
        );
        self::assertStringNotContainsString('be_groups:2 Editors: ', $commandTester->getDisplay());
        foreach ([6, 2] as $uid) {
            self::assertSame('classic', $this->getConnectionPool()->getConnectionForTable('be_groups')->select(['tx_begroups_kind'], 'be_groups', ['uid' => $uid])->fetchOne());
        }
    }
}
