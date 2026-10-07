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

namespace Cretection\BeGroups\Form\FormDataProvider;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * Offers the kind "classic" in the group form only while classic groups are allowed.
 *
 * Decided at runtime instead of in TCA, so changing the extension configuration takes
 * effect immediately. A new group that would start as "classic" starts as "role" instead,
 * and the "classic" item is removed unless the group already is a classic group.
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final readonly class KindSelection implements FormDataProviderInterface
{
    private const TABLE = 'be_groups';

    public function __construct(
        private ExtensionSettings $extensionSettings,
    ) {}

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    public function addData(array $result): array
    {
        if (($result['tableName'] ?? '') !== self::TABLE || $this->extensionSettings->isClassicGroupsAllowed()) {
            return $result;
        }
        $databaseRow = $result['databaseRow'] ?? null;
        if (!is_array($databaseRow)) {
            return $result;
        }

        $kind = $databaseRow[GroupKind::FIELD_NAME] ?? '';
        if (($result['command'] ?? '') === 'new' && $kind === GroupKind::Classic->value) {
            $databaseRow[GroupKind::FIELD_NAME] = GroupKind::Role->value;
            $result['databaseRow'] = $databaseRow;
            $kind = GroupKind::Role->value;
        }
        if ($kind === GroupKind::Classic->value) {
            return $result;
        }

        $processedTca = $result['processedTca'] ?? null;
        $columns = is_array($processedTca) ? ($processedTca['columns'] ?? null) : null;
        $column = is_array($columns) ? ($columns[GroupKind::FIELD_NAME] ?? null) : null;
        $config = is_array($column) ? ($column['config'] ?? null) : null;
        $items = is_array($config) ? ($config['items'] ?? null) : null;
        if (!is_array($processedTca) || !is_array($columns) || !is_array($column) || !is_array($config) || !is_array($items)) {
            return $result;
        }
        $config['items'] = array_values(array_filter(
            $items,
            static fn(mixed $item): bool => !is_array($item) || ($item['value'] ?? null) !== GroupKind::Classic->value,
        ));
        $column['config'] = $config;
        $columns[GroupKind::FIELD_NAME] = $column;
        $processedTca['columns'] = $columns;
        $result['processedTca'] = $processedTca;
        return $result;
    }
}
