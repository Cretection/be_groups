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
 * How serious a finding of the consistency check is.
 *
 * Public API: used by AuditFinding and AfterAuditFindingsCollectedEvent.
 */
enum AuditSeverity: string
{
    /**
     * The role model is violated, permissions may apply that nobody sees.
     */
    case Error = 'error';

    /**
     * Allowed, but outside the role model (e.g. classic groups, permissions on user records).
     */
    case Warning = 'warning';
}
