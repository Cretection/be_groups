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

namespace Cretection\BeGroups\EventListener;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewRecordCreationLinksEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Removes the kind "classic" from the wizard "Create new record" while classic groups are
 * disabled, like KindSelection does in the group form.
 *
 * The wizard lists the record types of the TCA, which cannot depend on the extension
 * configuration. Every group of the wizard is checked, because page TSconfig can move tables
 * to other groups.
 *
 * @internal
 */
final readonly class HideClassicKindInNewRecordWizard
{
    private const TABLE = 'be_groups';

    public function __construct(
        private ExtensionSettings $extensionSettings,
    ) {}

    #[AsEventListener('cretection/be-groups/hide-classic-kind-in-new-record-wizard')]
    public function __invoke(ModifyNewRecordCreationLinksEvent $event): void
    {
        if ($this->extensionSettings->isClassicGroupsAllowed()) {
            return;
        }
        $groupedCreationLinks = $event->groupedCreationLinks;
        foreach ($groupedCreationLinks as $groupName => $group) {
            $items = is_array($group) ? ($group['items'] ?? null) : null;
            $item = is_array($items) ? ($items[self::TABLE] ?? null) : null;
            $types = is_array($item) ? ($item['types'] ?? null) : null;
            if (!is_array($group) || !is_array($items) || !is_array($item) || !is_array($types)) {
                continue;
            }
            unset($types[GroupKind::Classic->value]);
            $item['types'] = $types;
            $items[self::TABLE] = $item;
            $group['items'] = $items;
            $groupedCreationLinks[$groupName] = $group;
        }
        $event->groupedCreationLinks = $groupedCreationLinks;
    }
}
