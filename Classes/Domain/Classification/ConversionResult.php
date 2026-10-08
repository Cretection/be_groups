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
 * The outcome of converting one classic group.
 *
 * @internal
 */
final readonly class ConversionResult
{
    /**
     * @param string $message an English explanation for administrators
     * @param list<int> $createdBlockUids
     */
    public function __construct(
        public int $uid,
        public ConversionStatus $status,
        public string $message,
        public array $createdBlockUids = [],
    ) {}
}
