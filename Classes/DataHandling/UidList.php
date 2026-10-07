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

namespace Cretection\BeGroups\DataHandling;

/**
 * Normalizes relation values as they arrive in a DataHandler datamap.
 *
 * Forms and API calls deliver relation lists either as comma-separated string
 * ("3,7") or as array; table-prefixed values ("be_groups_3") are accepted as well.
 *
 * @internal
 */
final readonly class UidList
{
    private const TABLE_PREFIX = 'be_groups_';

    /**
     * @param list<int> $uids
     */
    private function __construct(
        public array $uids,
    ) {}

    public static function fromValue(mixed $value): self
    {
        if (is_int($value)) {
            $value = (string)$value;
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return new self([]);
        }

        $uids = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $item = (string)$item;
            }
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if (str_starts_with($item, self::TABLE_PREFIX)) {
                $item = substr($item, strlen(self::TABLE_PREFIX));
            }
            if (ctype_digit($item) && (int)$item > 0) {
                $uids[] = (int)$item;
            }
        }
        return new self(array_values(array_unique($uids)));
    }

    /**
     * @return list<int>
     */
    public function withoutUidsOf(self $other): array
    {
        return array_values(array_diff($this->uids, $other->uids));
    }

    /**
     * @param list<int> $uidsToRemove
     */
    public function without(array $uidsToRemove): self
    {
        return new self(array_values(array_diff($this->uids, $uidsToRemove)));
    }

    public function toString(): string
    {
        return implode(',', $this->uids);
    }
}
