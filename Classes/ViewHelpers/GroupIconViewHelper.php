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

namespace Cretection\BeGroups\ViewHelpers;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\GroupKindLookup;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The icon of the kind of a backend group, for templates that only know uid and visibility of
 * the group (the core module "Users": its view helper beuser:spriteIconForRecord does not know
 * the kind and shows the default icon for every group).
 *
 * Usage: <begroups:groupIcon uid="{group.uid}" hidden="{group.hidden}" />
 *
 * @internal
 */
final class GroupIconViewHelper extends AbstractViewHelper
{
    private const TABLE = 'be_groups';

    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function __construct(
        private readonly GroupKindLookup $groupKindLookup,
        private readonly IconFactory $iconFactory,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('uid', 'mixed', 'The uid of the group', true);
        $this->registerArgument('hidden', 'bool', 'Whether the group is hidden', false, false);
    }

    public function render(): string
    {
        $uid = $this->arguments['uid'];
        $uid = is_numeric($uid) ? (int)$uid : 0;
        $row = [
            'uid' => $uid,
            'hidden' => $this->arguments['hidden'] === true,
            GroupKind::FIELD_NAME => $this->groupKindLookup->getKind($uid),
        ];
        return $this->iconFactory->getIconForRecord(self::TABLE, $row, IconSize::SMALL)->render();
    }
}
