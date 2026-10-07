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

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

// Same approach as the TYPO3 Core ("Build/php-cs-fixer/header-comment.php"):
// the license header is enforced for code files only. Configuration files and
// "ext_localconf.php" do not carry a header (CODING_GUIDELINES.md, §3.1).
$headerComment = <<<COMMENT
    This file is part of the TYPO3 CMS extension "be_groups".

    It is free software; you can redistribute it and/or modify it under
    the terms of the GNU General Public License, either version 2
    of the License, or any later version.

    For the full copyright and license information, please read the
    LICENSE.txt file that was distributed with this source code.
    COMMENT;

$rootDirectory = dirname(__DIR__, 2);
$directories = array_values(array_filter(
    [
        $rootDirectory . '/Build',
        $rootDirectory . '/Classes',
        $rootDirectory . '/Tests',
    ],
    is_dir(...),
));

$finder = Finder::create()
    ->files()
    ->name('*.php')
    ->in($directories)
    // Configuration files below "Tests" (fixture extensions) are configuration as well
    ->exclude('Configuration')
    ->notName('ext_localconf.php')
    ->notName('ext_emconf.php');

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/../../.Build/.cache/php-cs-fixer-header.cache')
    ->setRiskyAllowed(false)
    ->setRules([
        'no_extra_blank_lines' => true,
        'header_comment' => [
            'header' => $headerComment,
            'comment_type' => 'comment',
            'separate' => 'both',
            'location' => 'after_declare_strict',
        ],
    ])
    ->setFinder($finder);
