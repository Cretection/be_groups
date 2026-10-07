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

use Rector\Config\RectorConfig;
use Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

// Rector runs in CI in dry-run mode only ("composer check:php:rector"): any change it
// proposes is a finding (CODING_GUIDELINES.md, §14).
$rootDirectory = dirname(__DIR__, 2);

return RectorConfig::configure()
    ->withPaths(array_values(array_filter(
        [
            $rootDirectory . '/Classes',
            $rootDirectory . '/Configuration',
            $rootDirectory . '/Tests',
            $rootDirectory . '/ext_localconf.php',
        ],
        file_exists(...),
    )))
    ->withCache($rootDirectory . '/.Build/.cache/rector')
    // PHP 8.2 is the lowest supported PHP version, see composer.json
    ->withPhpSets(php82: true)
    ->withComposerBased(phpunit: true)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3LevelSetList::UP_TO_TYPO3_14,
    ])
    ->withPHPStanConfigs([
        Typo3Option::PHPSTAN_FOR_RECTOR_PATH,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    // Global classes are referenced fully qualified and not imported (CODING_GUIDELINES.md, §3.2)
    ->withImportNames(importNames: true, importDocBlockNames: true, importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // False positives: the TYPO3 testing framework always adds assertions.
        AddDoesNotPerformAssertionToNonAssertingTestRector::class,
    ]);
