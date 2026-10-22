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

namespace Cretection\BeGroups\Tests\Functional\Domain;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Cretection\BeGroups\Domain\Classification\ClassificationAction;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * An extension that does not know kinds and is loaded after this one adds its permission field
 * to every type, e.g. b13/permission-sets. Roles and page groups have no permission fields of
 * their own, so such a field belongs to no kind: it is reported, not granted by every kind.
 */
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(PermissionAudit::class)]
#[CoversClass(GroupClassifier::class)]
final class FieldsOfAllTypesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_all_types',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Classification/Fixtures/ClassicGroups.csv');
    }

    #[Test]
    public function aFieldThatTheFormsOfAllKindsShowBelongsToNoKind(): void
    {
        $resolver = $this->get(KindFieldResolver::class);

        self::assertSame(['subgroup'], $resolver->getPermissionFieldNames('role'));
        self::assertSame([], $resolver->getPermissionFieldNames('page_group'));
        self::assertSame(['db_mountpoints'], $resolver->getPermissionFieldNames('db_mount'));
        self::assertNotContains('tx_test_all_types', $resolver->getManagedFieldNames());
        self::assertSame(['tx_test_all_types'], $resolver->getUnassignedFieldNames());
    }

    #[Test]
    public function theAuditReportsValuesInItOnRolesAndBuildingBlocks(): void
    {
        $this->setPermissionOfAllTypes(10);
        $this->setPermissionOfAllTypes(12);

        $findings = array_values(array_filter(
            $this->get(PermissionAudit::class)->run(),
            static fn(AuditFinding $finding): bool => $finding->identifier === 'unassigned-fields',
        ));

        self::assertSame([10, 12], array_map(static fn(AuditFinding $finding): int => $finding->uid, $findings));
        self::assertStringContainsString('tx_test_all_types', $findings[0]->message);
    }

    #[Test]
    public function groupsWithValuesInItAreNotConvertedAutomatically(): void
    {
        $this->setPermissionOfAllTypes(1);

        $classification = $this->get(GroupClassifier::class)->classify(1);

        self::assertSame(ClassificationAction::Manual, $classification?->action);
        self::assertStringContainsString('tx_test_all_types', $classification->reason);
    }

    private function setPermissionOfAllTypes(int $uid): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->update('be_groups', ['tx_test_all_types' => 'granted'], ['uid' => $uid]);
    }
}
