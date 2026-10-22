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

namespace Cretection\BeGroups\Tests\Functional\Domain\Audit;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Fields of other extensions that only the form of classic groups shows (see UnassignedFieldsTest).
 */
#[CoversClass(PermissionAudit::class)]
final class PermissionAuditWithUnassignedFieldsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_fields',
    ];

    #[Test]
    public function namesTheUnassignedFieldsWithValues(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CleanData.csv');
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['tx_test_permission' => 'granted'], ['uid' => 1]);

        $findings = array_values(array_filter(
            $this->get(PermissionAudit::class)->run(),
            static fn(AuditFinding $finding): bool => $finding->identifier === 'unassigned-fields',
        ));

        self::assertCount(1, $findings);
        self::assertSame(
            'Fields that belong to no kind have values, which the form of the kind "acl" does not show: tx_test_permission. If they grant permissions, assign these fields to a kind.',
            $findings[0]->message,
        );
    }
}
