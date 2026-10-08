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

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\BeforeLoadedPageTsConfigEvent;

/**
 * Shows the groups in the core module "Users" with the prefix of their kind, by overriding the
 * templates that print group titles (page TSconfig "templates", see BackendViewFactory).
 *
 * The overrides are copies of the templates of TYPO3 14.3 with only the group titles changed.
 * They are only used while the templates of the core are exactly those copies: if the core
 * changes one of them (e.g. in a security release), the module falls back to the templates of
 * the core and shows the titles without prefix, and a test fails, so the copies are updated.
 *
 * @internal
 */
final readonly class OverrideUserModuleTemplates
{
    private const PACKAGE_KEY = 'beuser';
    private const OVERRIDE_PATH = 'cretection/be-groups:Resources/Private/TemplateOverrides/typo3/cms-beuser';

    /**
     * The templates of EXT:beuser the overrides are based on, with their SHA-256 hash.
     */
    public const BASE_TEMPLATES = [
        'Partials/BackendUser/PaginatedList.fluid.html' => '89db0795690c5615065c9e2baf5db85f3cceb41284be27eb98ef3cf45cb8088a',
        'Partials/BackendUserGroup/PaginatedList.fluid.html' => 'c088f5298299fa5f425d3d00bd9c96f2a98da012e97de725feba275d04822e61',
        'Templates/BackendUserGroup/List.fluid.html' => '34adbae96e73009011273f104f84cdbc6aa4b30966618f7f87bcc24e5ba0aa3b',
        'Templates/BackendUserGroup/Compare.fluid.html' => '91b9cb5ca0511bf492df0eceb527456714c024fc5a41aa20afcf52fcc3361490',
        'Templates/BackendUserGroup/Show.fluid.html' => 'd7a10385b63b7f0eef7052d3fce41e73e9521fa4975fc4b0681a3efbbab4db8a',
        'Partials/Compare/Information.fluid.html' => '7caf98fd5e3e8ff05ac319646da0193c2cc408e07995a0681ea6de2124d09a32',
        'Templates/Permission/ChangeGroupSelector.fluid.html' => '735cf4c37605e789995ef99ceec56c43dba0b4052c6498671f425138d24eff81',
    ];

    public function __construct(
        private PackageManager $packageManager,
    ) {}

    #[AsEventListener('cretection/be-groups/override-user-module-templates')]
    public function __invoke(BeforeLoadedPageTsConfigEvent $event): void
    {
        if ($this->packageManager->isPackageActive(self::PACKAGE_KEY) && $this->findChangedTemplates() === []) {
            $event->addTsConfig('templates.typo3/cms-beuser.1791446400 = ' . self::OVERRIDE_PATH);
        }
    }

    /**
     * @return list<string> the base templates that are missing or differ from the copies
     */
    public function findChangedTemplates(?string $templatePath = null): array
    {
        $templatePath ??= $this->packageManager->getPackage(self::PACKAGE_KEY)->getPackagePath() . 'Resources/Private/';
        $changed = [];
        foreach (self::BASE_TEMPLATES as $file => $hash) {
            $path = $templatePath . $file;
            if (!is_file($path) || hash_file('sha256', $path) !== $hash) {
                $changed[] = $file;
            }
        }
        return $changed;
    }
}
