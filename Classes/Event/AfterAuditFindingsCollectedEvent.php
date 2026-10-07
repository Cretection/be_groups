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

namespace Cretection\BeGroups\Event;

use Cretection\BeGroups\Domain\Audit\AuditFinding;

/**
 * Dispatched after the consistency check has collected its findings.
 * Listeners can add findings of their own checks, e.g. for permission fields of their extension.
 *
 * Public API.
 */
final class AfterAuditFindingsCollectedEvent
{
    /**
     * @param list<AuditFinding> $findings
     */
    public function __construct(
        private array $findings,
    ) {}

    /**
     * @return list<AuditFinding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    public function addFinding(AuditFinding $finding): void
    {
        $this->findings[] = $finding;
    }
}
