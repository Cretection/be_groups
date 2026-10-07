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

namespace Cretection\BeGroups\Upgrades;

/**
 * Type and length of a database column.
 *
 * @internal
 */
final readonly class ColumnInfo
{
    public function __construct(
        public bool $isString,
        public bool $isInteger,
        public ?int $maximumLength,
    ) {}

    public function fits(string $value): bool
    {
        return $this->maximumLength === null || mb_strlen($value) <= $this->maximumLength;
    }
}
