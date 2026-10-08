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

namespace Cretection\BeGroups\Backend;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * The title of backend groups wherever TYPO3 shows it (lists, relation fields, record forms),
 * with the prefix of the kind, e.g. "META: Editors" (label_userFunc of be_groups).
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final readonly class GroupRecordTitle
{
    public function __construct(
        private KindPrefix $kindPrefix,
    ) {}

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function render(array &$parameters): void
    {
        $row = is_array($parameters['row'] ?? null) ? $parameters['row'] : [];
        $parameters['title'] = $this->kindPrefix->prefixTitle(self::getValue($row, GroupKind::FIELD_NAME), self::getValue($row, 'title'));
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function getValue(array $row, string $fieldName): string
    {
        $value = $row[$fieldName] ?? '';
        // The record form passes the values of select fields as list.
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_scalar($value) ? (string)$value : '';
    }
}
