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

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\AuditSeverity;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(PermissionAudit::class)]
#[CoversClass(ExtensionSettings::class)]
final class PermissionAuditWithClassicGroupsDisabledTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'be_groups' => [
                'allowClassicGroups' => '0',
            ],
        ],
    ];

    #[Test]
    public function reportsRemainingClassicGroupsAsErrors(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/AuditData.csv');

        $classicFindings = array_values(array_filter(
            $this->get(PermissionAudit::class)->run(),
            static fn(AuditFinding $finding): bool => $finding->table === 'be_groups' && $finding->uid === 4,
        ));

        self::assertCount(1, $classicFindings);
        self::assertSame(AuditSeverity::Error, $classicFindings[0]->severity);
        self::assertSame('classic-group-disabled', $classicFindings[0]->identifier);
    }
}
