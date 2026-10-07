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
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Determines which be_groups fields a group kind may use.
 *
 * TYPO3 evaluates every permission field of every group of a user, regardless of
 * what the backend form shows. A building block must therefore only carry values
 * in the fields its form actually displays ("what is not visible, must not apply").
 *
 * @internal
 */
final readonly class KindFieldResolver
{
    public const TABLE = 'be_groups';

    /**
     * Fields that never grant permissions and are therefore allowed for every kind.
     */
    private const NEUTRAL_FIELDS = ['title', 'description', 'hidden', GroupKind::FIELD_NAME];

    /**
     * Field types that can safely be reset to an empty value via the DataHandler.
     */
    private const CLEARABLE_STRING_TYPES = ['select', 'group', 'category', 'text', 'input', 'passthrough', 'link', 'email', 'color', 'slug'];
    private const CLEARABLE_INTEGER_TYPES = ['check', 'number', 'radio'];

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * Whether the kind has its own form (TCA type). Kinds without a form are
     * treated like "classic", because TYPO3 shows them with the default form.
     */
    public function isKnownKind(string $kind): bool
    {
        return $kind !== '' && $this->getSchema()->hasSubSchema($kind);
    }

    /**
     * Fields a group of the given kind must not carry, mapped to the value that empties them.
     * Returns an empty list for "classic" and for kinds without a form.
     *
     * @return array<string, string|int>
     */
    public function getForeignFieldsWithEmptyValue(string $kind): array
    {
        if ($kind === GroupKind::Classic->value || !$this->isKnownKind($kind)) {
            return [];
        }
        $schema = $this->getSchema();
        $allowedFields = $this->getAllowedFieldNames($schema->getSubSchema($kind));

        $foreignFields = [];
        foreach ($this->getFields($schema) as $field) {
            $fieldName = $field->getName();
            if (in_array($fieldName, self::NEUTRAL_FIELDS, true) || isset($allowedFields[$fieldName])) {
                continue;
            }
            $emptyValue = $this->getEmptyValue($field);
            if ($emptyValue !== null) {
                $foreignFields[$fieldName] = $emptyValue;
            }
        }
        return $foreignFields;
    }

    /**
     * Fields of be_groups that cannot be emptied automatically (e.g. file or inline
     * relations added by other extensions). They are reported by the audit instead.
     *
     * @return list<string>
     */
    public function getUnmanageableFieldNames(): array
    {
        $fieldNames = [];
        foreach ($this->getFields($this->getSchema()) as $field) {
            if (!in_array($field->getName(), self::NEUTRAL_FIELDS, true) && $this->getEmptyValue($field) === null) {
                $fieldNames[] = $field->getName();
            }
        }
        return $fieldNames;
    }

    /**
     * @return array<string, true>
     */
    private function getAllowedFieldNames(TcaSchema $subSchema): array
    {
        $allowedFields = [];
        foreach ($this->getFields($subSchema) as $field) {
            $allowedFields[$field->getName()] = true;
            // A form element may persist a companion field, e.g. "tables_modify" also writes "tables_select".
            $companionField = $field->getConfiguration()['selectFieldName'] ?? null;
            if (is_string($companionField) && $companionField !== '') {
                $allowedFields[$companionField] = true;
            }
        }
        return $allowedFields;
    }

    /**
     * @return list<FieldTypeInterface>
     */
    private function getFields(TcaSchema $schema): array
    {
        $fields = [];
        foreach ($schema->getFields() as $field) {
            $fields[] = $field;
        }
        return $fields;
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
