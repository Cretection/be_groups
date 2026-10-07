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

namespace Cretection\BeGroups\Domain\Overview;

/**
 * A backend user as shown in the overview module.
 *
 * @internal
 */
final readonly class UserItem
{
    public function __construct(
        public int $uid,
        public string $username,
        public string $realName,
        public bool $disabled,
    ) {}
}
