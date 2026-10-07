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

use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\Field\RelationalFieldTypeInterface;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Determines which permission fields a group of a certain kind may carry.
 *
 * TYPO3 evaluates every permission field of every group of a user, regardless of what
 * the backend form shows. A group may therefore only carry permissions in the fields
 * its form displays ("what is not visible must not apply").
 *
 * Only permission fields are managed: the permission fields of the core and every field
 * that is shown in the form of a role or building block. Other data on be_groups, such as
 * identifiers of synchronisation tools, is never touched.
 *
 * @internal
 */
final readonly class KindFieldResolver
{
    public const TABLE = 'be_groups';

    /**
     * The permission fields of be_groups in TYPO3 14 (and of EXT:dashboard).
     */
    private const CORE_PERMISSION_FIELDS = [
        'subgroup', 'db_mountpoints', 'file_mountpoints', 'file_permissions', 'workspace_perms',
        'pagetypes_select', 'tables_modify', 'tables_select', 'non_exclude_fields', 'explicit_allowdeny',
        'allowed_languages', 'custom_options', 'groupMods', 'mfa_providers', 'TSconfig', 'tsconfig_includes',
        'category_perms', 'availableWidgets',
    ];

    /**
     * Fields that never grant permissions.
     */
    private const NEUTRAL_FIELDS = ['title', 'description', 'hidden', GroupKind::FIELD_NAME];

    /**
     * Field types that can safely be reset through the DataHandler.
     */
    private const CLEARABLE_STRING_TYPES = ['select', 'group', 'category', 'text', 'input', 'passthrough', 'link', 'email', 'color', 'slug'];
    private const CLEARABLE_INTEGER_TYPES = ['check', 'number', 'radio'];

    /**
     * Field types that store comma-separated lists.
     */
    private const LIST_TYPES = ['select', 'group', 'category'];

    /**
     * List fields whose order matters: it decides the precedence of TSconfig.
     */
    private const ORDERED_FIELDS = ['subgroup', 'tsconfig_includes'];

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private KindRegistry $kindRegistry,
    ) {}

    /**
     * Whether the rules for building blocks and roles apply to the kind:
     * it is configured, has its own form and is not "classic".
     */
    public function isRestrictedKind(string $kind): bool
    {
        return $kind !== GroupKind::Classic->value
            && $this->kindRegistry->isKind($kind)
            && $this->getSchema()->hasSubSchema($kind);
    }

    /**
     * Permission fields a group of the given kind must not carry, mapped to the value that empties them.
     *
     * @return array<string, string|int>
     */
    public function getForeignFieldsWithEmptyValue(string $kind): array
    {
        if (!$this->isRestrictedKind($kind)) {
            return [];
        }
        $schema = $this->getSchema();
        $allowedFields = $this->getAllowedFieldNames($schema->getSubSchema($kind));

        $foreignFields = [];
        foreach ($this->getManagedFields() as $field) {
            if (isset($allowedFields[$field->getName()])) {
                continue;
            }
            $emptyValue = $this->getEmptyValue($field);
            if ($emptyValue !== null) {
                $foreignFields[$field->getName()] = $emptyValue;
            }
        }
        return $foreignFields;
    }

    /**
     * The permission fields a group of the given kind may carry, in the order of the schema.
     *
     * @return list<string>
     */
    public function getPermissionFieldNames(string $kind): array
    {
        if (!$this->isRestrictedKind($kind)) {
            return [];
        }
        $allowedFields = $this->getAllowedFieldNames($this->getSchema()->getSubSchema($kind));
        return array_values(array_filter(
            $this->getManagedFieldNames(),
            static fn(string $fieldName): bool => isset($allowedFields[$fieldName]),
        ));
    }

    /**
     * All permission fields the rules manage, in the order of the schema.
     *
     * @return list<string>
     */
    public function getManagedFieldNames(): array
    {
        return array_map(static fn(FieldTypeInterface $field): string => $field->getName(), $this->getManagedFields());
    }

    /**
     * Whether two stored values of a permission field grant the same.
     *
     * Lists of relation and select fields are compared as sets, as TYPO3 merges them;
     * all other values must be identical, including lists whose order decides the
     * precedence of TSconfig.
     */
    public function isSameValue(string $fieldName, string $value, string $otherValue): bool
    {
        if ($this->isEmptyValue($fieldName, $value) || $this->isEmptyValue($fieldName, $otherValue)) {
            return $this->isEmptyValue($fieldName, $value) && $this->isEmptyValue($fieldName, $otherValue);
        }
        $schema = $this->getSchema();
        if (in_array($fieldName, self::ORDERED_FIELDS, true)
            || !$schema->hasField($fieldName)
            || !in_array($schema->getField($fieldName)->getType(), self::LIST_TYPES, true)
        ) {
            return $value === $otherValue;
        }
        $toSet = static function (string $list): array {
            $entries = array_unique(array_map(trim(...), explode(',', $list)));
            sort($entries);
            return $entries;
        };
        return $toSet($value) === $toSet($otherValue);
    }

    /**
     * Whether a stored or incoming value of a field (of be_groups by default) grants nothing.
     *
     * "0" is a real value in static selects (allowed_languages "0" is the default language),
     * but means "no relation" in relation fields and "nothing" in checkboxes and numbers.
     */
    public function isEmptyValue(string $fieldName, mixed $value, string $table = self::TABLE): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }
        if ($value !== '0' && $value !== 0) {
            return false;
        }
        if (!$this->tcaSchemaFactory->has($table)) {
            return false;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasField($fieldName)) {
            return false;
        }
        $field = $schema->getField($fieldName);
        return $field instanceof RelationalFieldTypeInterface
            || in_array($field->getType(), self::CLEARABLE_INTEGER_TYPES, true);
    }

    /**
     * @return list<FieldTypeInterface>
     */
    private function getManagedFields(): array
    {
        $schema = $this->getSchema();
        $managedFieldNames = array_fill_keys(self::CORE_PERMISSION_FIELDS, true);
        foreach ($this->kindRegistry->getDefinitions() as $definition) {
            if ($this->isRestrictedKind($definition->value)) {
                $managedFieldNames += $this->getAllowedFieldNames($schema->getSubSchema($definition->value));
            }
        }

        $fields = [];
        foreach ($schema->getFields() as $field) {
            if (isset($managedFieldNames[$field->getName()]) && !in_array($field->getName(), self::NEUTRAL_FIELDS, true)) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    /**
     * @return array<string, true>
     */
    private function getAllowedFieldNames(TcaSchema $subSchema): array
    {
        $allowedFields = [];
        foreach ($subSchema->getFields() as $field) {
            $allowedFields[$field->getName()] = true;
            // A form element may persist a companion field, e.g. "tables_modify" also writes "tables_select".
            $companionField = $field->getConfiguration()['selectFieldName'] ?? null;
            if (is_string($companionField) && $companionField !== '') {
                $allowedFields[$companionField] = true;
            }
        }
        return $allowedFields;
    }

    private function getEmptyValue(FieldTypeInterface $field): string|int|null
    {
        $type = $field->getType();
        if (in_array($type, self::CLEARABLE_INTEGER_TYPES, true)) {
            return 0;
        }
        if (in_array($type, self::CLEARABLE_STRING_TYPES, true)) {
            return '';
        }
        return null;
    }

    private function getSchema(): TcaSchema
    {
        return $this->tcaSchemaFactory->get(self::TABLE);
    }
}
