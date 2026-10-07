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

use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use TYPO3\CodingStandards\CsFixerConfig;

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

// The rule set of typo3/coding-standards is byte-identical to the one of the
// TYPO3 Core 14.3 and must not be extended here (CODING_GUIDELINES.md, §3.2).
// The license header is checked separately with "header-comment.php", as
// configuration files do not carry a header (CODING_GUIDELINES.md, §3.1).
$config = CsFixerConfig::create();
$config->setParallelConfig(ParallelConfigFactory::detect());
$config->setCacheFile(__DIR__ . '/../../.Build/.cache/php-cs-fixer.cache');

$rootDirectory = dirname(__DIR__, 2);
$directories = array_values(array_filter(
    [
        $rootDirectory . '/Build',
        $rootDirectory . '/Classes',
        $rootDirectory . '/Configuration',
        $rootDirectory . '/Tests',
    ],
    is_dir(...),
));

$finder = $config->getFinder();
if ($finder instanceof Finder) {
    // Root files like "ext_localconf.php" ...
    $finder->in($rootDirectory)->depth('== 0')->name('*.php');
    // ... and everything below the code directories.
    if ($directories !== []) {
        $finder->append(Finder::create()->files()->in($directories)->name('*.php'));
    }
}

return $config;
