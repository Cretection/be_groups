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
 * A list of be_groups relations as it arrives in a DataHandler datamap or is stored in the database.
 *
 * Every notation the DataHandler accepts is understood: comma-separated strings or arrays,
 * "uid|label" pairs, URL-encoded values, table-prefixed values ("be_groups_3") and NEW
 * placeholders of records created in the same datamap. Entries are either a uid (int) or
 * a placeholder (string); anything else references no record and is dropped.
 *
 * @internal
 */
final readonly class RelationList
{
    private const TABLE_PREFIX = 'be_groups_';
    private const PLACEHOLDER_PATTERN = '/^NEW[A-Za-z0-9._-]+$/';

    /**
     * @param list<int|string> $entries
     */
    private function __construct(
        public array $entries,
    ) {}

    /**
     * Whether a stored list reads the same for TYPO3 and for the DataHandler: only positive numbers
     * without leading zeros or signs, no duplicates. When resolving groups, TYPO3 casts each entry to
     * a number ("be_groups_5" matches no group, "05" is group 5) and applies TSconfig at the last
     * occurrence of a duplicate; the DataHandler understands "be_groups_5" as group 5, drops "05" and
     * keeps the first occurrence.
     */
    public static function isCanonical(string $storedList): bool
    {
        if ($storedList === '') {
            return true;
        }
        $entries = explode(',', $storedList);
        foreach ($entries as $entry) {
            if (preg_match('/^[1-9]\d*$/', $entry) !== 1) {
                return false;
            }
        }
        return count(array_unique($entries)) === count($entries);
    }

    public static function fromValue(mixed $value): self
    {
        if (is_int($value)) {
            $value = [$value];
        } elseif (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return new self([]);
        }

        $entries = [];
        foreach ($value as $item) {
            $entry = self::parseEntry($item);
            if ($entry !== null && !in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }
        return new self($entries);
    }

    /**
     * @param list<int|string> $entries
     */
    public static function fromEntries(array $entries): self
    {
        return self::fromValue($entries);
    }

    /**
     * @return list<int>
     */
    public function getUids(): array
    {
        return array_values(array_filter($this->entries, is_int(...)));
    }

    public function contains(int|string $entry): bool
    {
        return in_array($entry, $this->entries, true);
    }

    /**
     * Entries of this list that are missing in the other list, in the order of this list.
     *
     * @return list<int|string>
     */
    public function getEntriesMissingIn(self $other): array
    {
        return array_values(array_filter($this->entries, static fn(int|string $entry): bool => !$other->contains($entry)));
    }

    /**
     * @param list<int|string> $entriesToRemove
     */
    public function without(array $entriesToRemove): self
    {
        return new self(array_values(array_filter(
            $this->entries,
            static fn(int|string $entry): bool => !in_array($entry, $entriesToRemove, true),
        )));
    }

    /**
     * Inserts the entry directly after $afterEntry, or appends it if $afterEntry is not part of the list.
     */
    public function withEntryAfter(int|string $entry, int|string|null $afterEntry): self
    {
        if ($this->contains($entry)) {
            return $this;
        }
        $entries = $this->entries;
        $position = $afterEntry === null ? false : array_search($afterEntry, $entries, true);
        if ($position === false) {
            $entries[] = $entry;
        } else {
            array_splice($entries, $position + 1, 0, [$entry]);
        }
        return new self($entries);
    }

    public function toString(): string
    {
        return implode(',', $this->entries);
    }

    private static function parseEntry(mixed $item): int|string|null
    {
        if (is_int($item)) {
            return $item > 0 ? $item : null;
        }
        if (!is_string($item)) {
            return null;
        }
        $item = trim(rawurldecode($item));
        $pipePosition = strpos($item, '|');
        if ($pipePosition !== false) {
            $item = substr($item, 0, $pipePosition);
        }
        if (str_starts_with($item, self::TABLE_PREFIX)) {
            $item = substr($item, strlen(self::TABLE_PREFIX));
        }
        if (ctype_digit($item)) {
            return (int)$item > 0 ? (int)$item : null;
        }
        return preg_match(self::PLACEHOLDER_PATTERN, $item) === 1 ? $item : null;
    }
}
