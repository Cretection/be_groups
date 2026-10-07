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

namespace Cretection\BeGroups\Domain\Classification;

/**
 * What the assistants propose for a classic group.
 *
 * @internal
 */
enum ClassificationAction: string
{
    /**
     * The group already serves one purpose: only its kind changes ("begroups:classify").
     */
    case ChangeKind = 'change-kind';

    /**
     * The permissions move into building blocks and the group becomes a role ("begroups:split").
     */
    case Split = 'split';

    /**
     * No automatic conversion is possible; an administrator decides.
     */
    case Manual = 'manual';
}
