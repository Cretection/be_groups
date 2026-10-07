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
 * The proposal of the assistants for one classic group.
 *
 * @internal
 */
final readonly class Classification
{
    /**
     * @param list<Concern> $concerns the permissions of the group, by kind
     * @param string|null $targetKind the new kind of the group (ChangeKind and Split), null if an administrator decides
     * @param string $reason an English explanation for administrators
     */
    public function __construct(
        public int $uid,
        public string $title,
        public bool $hidden,
        public ClassificationAction $action,
        public ?string $targetKind,
        public array $concerns,
        public string $reason,
    ) {}
}
