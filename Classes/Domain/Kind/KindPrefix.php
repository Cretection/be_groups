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

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The short prefix that shows the kind of a group wherever its title appears, e.g. "META" for
 * roles. Titles do not need to contain the kind.
 *
 * @internal
 */
final readonly class KindPrefix
{
    private const LABEL_PREFIX = 'LLL:EXT:be_groups/Resources/Private/Language/db.xlf:kind.prefix.';
    private const NO_TITLE_LABEL = 'core.core:labels.no_title';

    public function __construct(
        private KindRegistry $kindRegistry,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @return string the prefix, or "" for classic groups and kinds that are not configured
     */
    public function getPrefix(string $kind): string
    {
        if ($kind === GroupKind::Classic->value || !$this->kindRegistry->isKind($kind)) {
            return '';
        }
        $languageService = $this->getLanguageService();
        // Kinds of other extensions are shown with their label.
        $prefix = GroupKind::tryFrom($kind) !== null ? $languageService->sL(self::LABEL_PREFIX . $kind) : '';
        return $prefix !== '' ? $prefix : $languageService->sL($this->kindRegistry->getDefinitions()[$kind]->label);
    }

    /**
     * The title of a group as the lists of TYPO3 show it, e.g. "META: Editors". A group without title is
     * shown as "[No title]", like every record without title, e.g. "DBM: [No title]".
     */
    public function prefixTitle(string $kind, string $title): string
    {
        if (trim($title) === '') {
            $title = '[' . $this->getLanguageService()->sL(self::NO_TITLE_LABEL) . ']';
        }
        $prefix = $this->getPrefix($kind);
        return $prefix === '' ? $title : $prefix . ': ' . $title;
    }

    private function getLanguageService(): LanguageService
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        return $languageService instanceof LanguageService ? $languageService : $this->languageServiceFactory->create('default');
    }
}
