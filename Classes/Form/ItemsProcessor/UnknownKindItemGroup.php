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

namespace Cretection\BeGroups\Form\ItemsProcessor;

use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\ItemsProcessorContext;
use TYPO3\CMS\Core\DataHandling\ItemsProcessorInterface;
use TYPO3\CMS\Core\Schema\Struct\SelectItemCollection;

/**
 * Lists the groups of an unknown kind under a heading of their own in the lists of the role and the
 * user form, which are grouped by the stored kind (foreign_table_item_group). For a kind that is not
 * configured (any more), e.g. the kind of an uninstalled extension, TYPO3 would show the stored value
 * as heading, and groups without a kind would have no heading at all. These groups can neither be
 * added to a role nor be assigned to a user, which the heading of the group says.
 *
 * No item is removed: a select field only keeps the stored values that are part of its items.
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final readonly class UnknownKindItemGroup implements ItemsProcessorInterface
{
    /**
     * The item group of the groups of an unknown kind, in the namespace of this extension, so it is no kind.
     */
    public const GROUP = 'tx_begroups_unknown';

    public function __construct(
        private KindRegistry $kindRegistry,
    ) {}

    public function processItems(SelectItemCollection $items, ItemsProcessorContext $context): SelectItemCollection
    {
        $processedItems = new SelectItemCollection();
        foreach ($items as $item) {
            $kind = $item->getGroup();
            $processedItems->add($item->isDivider() || $kind === null || $this->kindRegistry->isKind($kind) ? $item : $item->withGroup(self::GROUP));
        }
        return $processedItems;
    }
}
