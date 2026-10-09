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

// Checks that every file carrying the version is ready for the release of the given version
// (CODING_GUIDELINES.md, §17). The release workflow runs it before publishing to the TER; run it
// before tagging as well: php Build/Scripts/checkReleaseVersion.php 1.2.3
// Without dependencies, so it runs without "composer install".

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

$version = $argv[1] ?? '';
if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $versionParts) !== 1) {
    fwrite(STDERR, 'Usage: php Build/Scripts/checkReleaseVersion.php <major>.<minor>.<patch>' . chr(10));
    exit(2);
}
$rootDirectory = dirname(__DIR__, 2);
$readFile = static fn(string $file): string => is_file($rootDirectory . '/' . $file) ? (string)file_get_contents($rootDirectory . '/' . $file) : '';
$problems = [];

$composer = json_decode($readFile('composer.json'), true);
$composerVersion = is_array($composer) && is_array($composer['extra']['typo3/cms'] ?? null)
    ? ($composer['extra']['typo3/cms']['version'] ?? null)
    : null;
if ($composerVersion !== $version) {
    $problems[] = sprintf('composer.json: extra.typo3/cms.version is "%s", not "%s".', is_string($composerVersion) ? $composerVersion : '', $version);
}

// The documentation shows the release and the minor version ("version") in its header.
foreach (['Documentation/guides.xml', 'Documentation/Localization.de_DE/guides.xml'] as $file) {
    $content = $readFile($file);
    $document = new DOMDocument();
    $project = $content !== '' && $document->loadXML($content) ? $document->getElementsByTagName('project')->item(0) : null;
    $expected = ['release' => $version, 'version' => $versionParts[1] . '.' . $versionParts[2]];
    foreach ($expected as $attribute => $value) {
        $actual = $project instanceof DOMElement ? $project->getAttribute($attribute) : '';
        if ($actual !== $value) {
            $problems[] = sprintf('%s: the attribute "%s" of <project> is "%s", not "%s".', $file, $attribute, $actual, $value);
        }
    }
}

$date = '\d{4}-\d{2}-\d{2}';
$quotedVersion = preg_quote($version, '/');
$changelogs = [
    // git-cliff writes the heading as "## [1.2.0](link to the comparison) - date"
    'CHANGELOG.md' => '/^## \[' . $quotedVersion . '\](\([^)]*\))? - ' . $date . '$/m',
    'Documentation/Changelog/Index.rst' => '/^' . $quotedVersion . ' \(' . $date . '\)$/m',
    'Documentation/Localization.de_DE/Changelog/Index.rst' => '/^' . $quotedVersion . ' \(' . $date . '\)$/m',
];
foreach ($changelogs as $file => $pattern) {
    if (preg_match($pattern, $readFile($file)) !== 1) {
        $problems[] = sprintf('%s: there is no entry for %s with its release date (YYYY-MM-DD).', $file, $version);
    }
}

if ($problems !== []) {
    fwrite(STDERR, implode(chr(10), $problems) . chr(10));
    exit(1);
}
echo sprintf('All files are ready for the release of %s.', $version) . chr(10);
exit(0);
