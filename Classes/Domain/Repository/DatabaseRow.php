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

namespace Cretection\BeGroups\Domain\Repository;

/**
 * A database row with all values normalized to strings, as the database drivers
 * return them differently (int vs. string, null).
 *
 * @internal
 */
final readonly class DatabaseRow
{
    /**
     * @param array<string, string> $values
     */
    private function __construct(
        public array $values,
    ) {}

    /**
     * @param array<mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $values = [];
        foreach ($row as $field => $value) {
            if (is_string($field)) {
                $values[$field] = is_scalar($value) ? (string)$value : '';
            }
        }
        return new self($values);
    }

    public function get(string $field): string
    {
        return $this->values[$field] ?? '';
    }

    public function getUid(): int
    {
        return (int)$this->get('uid');
    }

    /**
     * @param array<string, string> $values
     */
    public function with(array $values): self
    {
        return new self(array_replace($this->values, $values));
    }
}
