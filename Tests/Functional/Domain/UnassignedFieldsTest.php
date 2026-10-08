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
 * Fields of other extensions that only the form of classic groups shows belong to no kind, so the
 * rules cannot manage them. Converting a group would hide them, and other kinds must not carry them.
 */
#[CoversClass(KindFieldResolver::class)]
#[CoversClass(GroupClassifier::class)]
#[CoversClass(PermissionAudit::class)]
final class UnassignedFieldsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_fields',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Classification/Fixtures/ClassicGroups.csv');
    }

    #[Test]
    public function fieldsThatOnlyTheClassicFormShowsAreUnassigned(): void
    {
        self::assertSame(['tx_test_permission'], $this->get(KindFieldResolver::class)->getUnassignedFieldNames());
    }

    #[Test]
    public function groupsWithValuesInUnassignedFieldsAreNotConvertedAutomatically(): void
    {
        $this->setPermissionOfOtherExtension(1);

        $classification = $this->get(GroupClassifier::class)->classify(1);

        self::assertSame(ClassificationAction::Manual, $classification?->action);
        self::assertStringContainsString('tx_test_permission', $classification->reason);
    }

    #[Test]
    public function theAuditReportsValuesInUnassignedFieldsOfOtherKinds(): void
    {
        $this->setPermissionOfOtherExtension(12);

        $findings = array_values(array_filter(
            $this->get(PermissionAudit::class)->run(),
            static fn(AuditFinding $finding): bool => $finding->identifier === 'unassigned-fields',
        ));

        self::assertCount(1, $findings);
        self::assertSame(12, $findings[0]->uid);
        self::assertStringContainsString('tx_test_permission', $findings[0]->message);
    }

    private function setPermissionOfOtherExtension(int $uid): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->update('be_groups', ['tx_test_permission' => 'granted'], ['uid' => $uid]);
    }
}
