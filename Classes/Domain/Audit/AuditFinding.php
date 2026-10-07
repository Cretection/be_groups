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

namespace Cretection\BeGroups\Domain\Audit;

/**
 * One finding of the consistency check ("vendor/bin/typo3 begroups:audit").
 *
 * Public API: extensions can add their own findings with AfterAuditFindingsCollectedEvent.
 */
final readonly class AuditFinding
{
    /**
     * @param string $identifier a stable machine-readable identifier, e.g. "role-invalid-member"
     * @param string $table the table of the affected record ("be_groups" or "be_users")
     * @param int $uid the uid of the affected record
     * @param string $title the title or username of the affected record
     * @param string $message an English explanation for administrators
     */
    public function __construct(
        public AuditSeverity $severity,
        public string $identifier,
        public string $table,
        public int $uid,
        public string $title,
        public string $message,
    ) {}
}
