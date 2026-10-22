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
 * The user form marks the classic groups as not assignable meanwhile. They stay in the list,
 * because a filtered list would drop their stored assignments on saving.
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final readonly class KindSelection implements FormDataProviderInterface
{
    private const TABLE = 'be_groups';
    private const USER_TABLE = 'be_users';
    private const USER_GROUP_FIELD = 'usergroup';
    private const LABELS = 'be_groups.db:';

    public function __construct(
        private ExtensionSettings $extensionSettings,
    ) {}

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    public function addData(array $result): array
    {
        $tableName = $result['tableName'] ?? '';
        if (!in_array($tableName, [self::TABLE, self::USER_TABLE], true) || $this->extensionSettings->isClassicGroupsAllowed()) {
            return $result;
        }
        return $tableName === self::TABLE ? $this->removeClassicKind($result) : $this->markClassicGroupsAsNotAssignable($result);
    }

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    private function removeClassicKind(array $result): array
    {
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

        $column = self::getColumn($result, GroupKind::FIELD_NAME);
        $config = $column['config'] ?? null;
        $items = is_array($config) ? ($config['items'] ?? null) : null;
        if (!is_array($config) || !is_array($items)) {
            return $result;
        }
        $config['items'] = array_values(array_filter(
            $items,
            static fn(mixed $item): bool => !is_array($item) || ($item['value'] ?? null) !== GroupKind::Classic->value,
        ));
        $column['config'] = $config;
        return self::withColumn($result, GroupKind::FIELD_NAME, $column);
    }

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    private function markClassicGroupsAsNotAssignable(array $result): array
    {
        $column = self::getColumn($result, self::USER_GROUP_FIELD);
        $config = $column['config'] ?? null;
        $itemGroups = is_array($config) ? ($config['itemGroups'] ?? null) : null;
        if (!is_array($config) || !is_array($itemGroups) || !isset($itemGroups[GroupKind::Classic->value])) {
            return $result;
        }
        $itemGroups[GroupKind::Classic->value] = self::LABELS . 'kind.group.notAssignable.classic';
        $config['itemGroups'] = $itemGroups;
        $column['config'] = $config;
        $column['description'] = self::LABELS . 'be_users.usergroup.description.classicDisabled';
        return self::withColumn($result, self::USER_GROUP_FIELD, $column);
    }

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed> the processed TCA of the field, or [] if there is none
     */
    private static function getColumn(array $result, string $fieldName): array
    {
        $processedTca = $result['processedTca'] ?? null;
        $columns = is_array($processedTca) ? ($processedTca['columns'] ?? null) : null;
        $column = is_array($columns) ? ($columns[$fieldName] ?? null) : null;
        return is_array($column) ? $column : [];
    }

    /**
     * @param array<array-key, mixed> $result
     * @param array<array-key, mixed> $column
     * @return array<array-key, mixed>
     */
    private static function withColumn(array $result, string $fieldName, array $column): array
    {
        $processedTca = $result['processedTca'] ?? null;
        $columns = is_array($processedTca) ? ($processedTca['columns'] ?? null) : null;
        if (!is_array($processedTca) || !is_array($columns)) {
            return $result;
        }
        $columns[$fieldName] = $column;
        $processedTca['columns'] = $columns;
        $result['processedTca'] = $processedTca;
        return $result;
    }
}
