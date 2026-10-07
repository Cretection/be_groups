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

namespace Cretection\BeGroups\Tests\Functional\Event;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\AuditSeverity;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(AfterAuditFindingsCollectedEvent::class)]
#[CoversClass(PermissionAudit::class)]
#[CoversClass(AuditFinding::class)]
final class AfterAuditFindingsCollectedEventTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_audit',
    ];

    #[Test]
    public function listenersAddFindings(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Domain/Audit/Fixtures/CleanData.csv');

        self::assertEquals(
            [new AuditFinding(AuditSeverity::Warning, 'custom-check', 'be_groups', 2, 'DBM ok', 'Found by another extension.')],
            $this->get(PermissionAudit::class)->run(),
        );
    }

    #[Test]
    public function findingsOfListenersAreSortedWithAllOthers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Domain/Audit/Fixtures/AuditData.csv');

        $identifiers = array_map(static fn(AuditFinding $finding): string => $finding->identifier, $this->get(PermissionAudit::class)->run());

        self::assertSame(['user-building-block', 'custom-check', 'classic-group'], array_slice($identifiers, 8, 3));
    }
}
