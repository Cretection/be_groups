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

use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * The kinds that may be used, as configured in the items of be_groups.tx_begroups_kind.
 *
 * Only configured kinds are valid. A stored value that is no item (e.g. a numeric kind of
 * the former extension, or the kind of an uninstalled extension) is neither a role nor a
 * building block, so it can never slip into the role model.
 *
 * @internal
 */
final readonly class KindRegistry
{
    private const TABLE = 'be_groups';
    private const FALLBACK_ICON = 'status-user-group-backend';

    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    public function isKind(string $kind): bool
    {
        return isset($this->getDefinitions()[$kind]);
    }

    public function isBuildingBlock(string $kind): bool
    {
        return $this->isKind($kind) && $kind !== GroupKind::Role->value && $kind !== GroupKind::Classic->value;
    }

    /**
     * @return array<string, KindDefinition> all configured kinds in the order of the items, indexed by value
     */
    public function getDefinitions(): array
    {
        if (!$this->tcaSchemaFactory->has(self::TABLE)) {
            return [];
        }
        $schema = $this->tcaSchemaFactory->get(self::TABLE);
        if (!$schema->hasField(GroupKind::FIELD_NAME)) {
            return [];
        }
        $items = $schema->getField(GroupKind::FIELD_NAME)->getConfiguration()['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $definitions = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = $item['value'] ?? null;
            if (!is_string($value) || $value === '' || $value === '--div--') {
                continue;
            }
            $label = $item['label'] ?? '';
            $icon = $item['icon'] ?? '';
            $definitions[$value] = new KindDefinition(
                $value,
                is_string($label) ? $label : '',
                is_string($icon) && $icon !== '' ? $icon : self::FALLBACK_ICON,
            );
        }
        return $definitions;
    }

    /**
     * The default kind configured in TCA, "classic" if none (or an invalid one) is configured.
     */
    public function getDefaultKind(): string
    {
        $default = $this->tcaSchemaFactory->has(self::TABLE) && $this->tcaSchemaFactory->get(self::TABLE)->hasField(GroupKind::FIELD_NAME)
            ? $this->tcaSchemaFactory->get(self::TABLE)->getField(GroupKind::FIELD_NAME)->getDefaultValue()
            : null;
        return is_string($default) && $this->isKind($default) ? $default : GroupKind::Classic->value;
    }

    public function getIconIdentifier(string $kind): string
    {
        $definitions = $this->getDefinitions();
        return isset($definitions[$kind]) ? $definitions[$kind]->iconIdentifier : self::FALLBACK_ICON;
    }
}
