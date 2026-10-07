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

namespace Cretection\BeGroups\Event;

/**
 * Dispatched when a new group is saved without a kind, e.g. by an import or a synchronisation
 * tool. Listeners can choose the kind from the values of the record, for example from a prefix
 * of the title. A kind that is not configured is ignored and the default kind is kept. All rules
 * apply to the chosen kind, including whether classic groups may be created.
 *
 * Public API.
 */
final class ModifyKindOfNewGroupEvent
{
    /**
     * @param array<array-key, mixed> $record the incoming values of the new group
     * @param string $kind the default kind (TCA default, overridden by TCAdefaults in user and page TSconfig)
     */
    public function __construct(
        private readonly array $record,
        private string $kind,
    ) {}

    /**
     * @return array<array-key, mixed> the incoming values of the new group, as passed to the DataHandler
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }
}
