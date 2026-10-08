<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-begroups-roles' => [
        'provider' => SvgIconProvider::class,
        // Monochrome like the module icons of the core: only the role uses the accent color.
        'source' => 'EXT:be_groups/Resources/Public/Icons/module-roles.svg',
    ],
];
