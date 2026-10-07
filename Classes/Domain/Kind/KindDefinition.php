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

namespace Cretection\BeGroups\Domain\Kind;

/**
 * A configured kind: its stored value, its label reference and its icon.
 *
 * @internal
 */
final readonly class KindDefinition
{
    public function __construct(
        public string $value,
        public string $label,
        public string $iconIdentifier,
    ) {}
}
