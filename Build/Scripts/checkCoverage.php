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

// Checks the line coverage of "Classes/" in the merged clover report against the minimum of
// CODING_GUIDELINES.md, §13. Run "-s coverageMerge" first: Build/Scripts/runTests.sh -s coverageCheck
// The report also covers "Configuration/" and "ext_localconf.php": PHPUnit only treats issues in
// its source as issues of the extension, but TYPO3 runs these files before the coverage starts.

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

const MINIMUM_LINE_COVERAGE = 90.0;

$rootDirectory = dirname(__DIR__, 2);
$reportFile = $rootDirectory . '/.Build/logs/clover.xml';
$document = new DOMDocument();
if (!is_file($reportFile) || !$document->load($reportFile)) {
    fwrite(STDERR, 'No clover report in ".Build/logs/clover.xml", run "-s coverageMerge" first.' . chr(10));
    exit(2);
}

$classesDirectory = $rootDirectory . '/Classes/';
$statements = 0;
$coveredStatements = 0;
foreach ($document->getElementsByTagName('file') as $file) {
    if (!str_starts_with($file->getAttribute('name'), $classesDirectory)) {
        continue;
    }
    $metrics = $file->getElementsByTagName('metrics')->item(0);
    if ($metrics instanceof DOMElement) {
        $statements += (int)$metrics->getAttribute('statements');
        $coveredStatements += (int)$metrics->getAttribute('coveredstatements');
    }
}
if ($statements === 0) {
    fwrite(STDERR, 'The clover report contains no lines of "Classes/".' . chr(10));
    exit(2);
}

$lineCoverage = 100 * $coveredStatements / $statements;
$summary = sprintf(
    'Line coverage of Classes/: %.2f %% (%d/%d lines), minimum %.0f %%',
    floor($lineCoverage * 100) / 100,
    $coveredStatements,
    $statements,
    MINIMUM_LINE_COVERAGE
);
if ($lineCoverage < MINIMUM_LINE_COVERAGE) {
    fwrite(STDERR, $summary . ': too low.' . chr(10));
    exit(1);
}
echo $summary . '.' . chr(10);
exit(0);
