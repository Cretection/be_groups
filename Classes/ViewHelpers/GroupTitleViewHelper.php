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

use Cretection\BeGroups\Domain\Kind\GroupKindLookup;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The title of a backend group with the prefix of its kind, e.g. "META: Editors", for templates
 * that only know uid and title (the core module "Users").
 *
 * Usage: {begroups:groupTitle(uid: group.uid, title: group.title)}
 *
 * @internal
 */
final class GroupTitleViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly GroupKindLookup $groupKindLookup,
        private readonly KindPrefix $kindPrefix,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('uid', 'mixed', 'The uid of the group', true);
        $this->registerArgument('title', 'mixed', 'The title of the group', true);
    }

    public function render(): string
    {
        $uid = $this->arguments['uid'];
        $title = $this->arguments['title'];
        return $this->kindPrefix->prefixTitle(
            $this->groupKindLookup->getKind(is_numeric($uid) ? (int)$uid : 0),
            is_scalar($title) ? (string)$title : '',
        );
    }
}
