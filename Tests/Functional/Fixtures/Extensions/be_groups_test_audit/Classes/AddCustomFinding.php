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

namespace Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_audit\Classes;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\AuditSeverity;
use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

final readonly class AddCustomFinding
{
    #[AsEventListener('cretection/be-groups-test-audit/add-custom-finding')]
    public function __invoke(AfterAuditFindingsCollectedEvent $event): void
    {
        $event->addFinding(new AuditFinding(AuditSeverity::Warning, 'custom-check', 'be_groups', 2, 'DBM ok', 'Found by another extension.'));
    }
}
