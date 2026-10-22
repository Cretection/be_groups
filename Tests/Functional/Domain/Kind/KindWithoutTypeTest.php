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

namespace Cretection\BeGroups\Tests\Functional\Domain\Kind;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * An item of the kind selector without a type of its own has no form, so the rules cannot
 * manage the permissions of its groups. It is no kind: its groups are treated like groups of
 * an unknown kind everywhere.
 */
#[CoversClass(KindRegistry::class)]
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(PermissionAudit::class)]
final class KindWithoutTypeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/Fixtures/Extensions/be_groups_test_kind_without_type',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/KindWithoutType.csv');
    }

    #[Test]
    public function anItemWithoutATypeOfItsOwnIsNoKind(): void
    {
        $kindRegistry = $this->get(KindRegistry::class);

        self::assertFalse($kindRegistry->isKind('my_orphan'));
        self::assertFalse($kindRegistry->isBuildingBlock('my_orphan'));
        self::assertArrayNotHasKey('my_orphan', $kindRegistry->getDefinitions());
        self::assertFalse($this->get(KindFieldResolver::class)->isRestrictedKind('my_orphan'));
        self::assertTrue($kindRegistry->isBuildingBlock('acl'));
    }

    #[Test]
    public function theAuditReportsGroupsOfAnItemWithoutATypeAsUnknownKind(): void
    {
        $findings = array_map(
            static fn(AuditFinding $finding): string => sprintf('%s %s:%d %s', $finding->severity->value, $finding->table, $finding->uid, $finding->identifier),
            $this->get(PermissionAudit::class)->run(),
        );

        self::assertSame(['error be_groups:1 unknown-kind', 'error be_groups:2 role-invalid-member'], $findings);
    }

    #[Test]
    public function groupsOfAnItemWithoutATypeCannotBeAddedToRoles(): void
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['be_groups' => ['NEW1' => ['pid' => 0, 'title' => 'New role', 'tx_begroups_kind' => 'role', 'subgroup' => '1,3']]], []);
        $dataHandler->process_datamap();

        $uid = $dataHandler->substNEWwithIDs['NEW1'] ?? null;
        self::assertIsInt($uid);
        $subgroup = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->select(['subgroup'], 'be_groups', ['uid' => $uid])
            ->fetchOne();
        self::assertSame('3', $subgroup);
    }
}
