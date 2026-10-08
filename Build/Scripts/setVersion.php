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

// Sets the version in composer.json (extra.typo3/cms.version) and in the settings of the
// documentation (CODING_GUIDELINES.md, §17), e.g. "1.2.0" for a release and "1.2.1-dev"
// afterwards. "tailor set-version" only accepts versions without a suffix like "-dev",
// which TYPO3 reads as the stability of the extension.
// Usage: php Build/Scripts/setVersion.php 1.2.3[-dev]
// Without dependencies, so it runs without "composer install".

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

$version = $argv[1] ?? '';
if (preg_match('/^(\d+)\.(\d+)\.\d+(-dev)?$/', $version, $versionParts) !== 1) {
    fwrite(STDERR, 'Usage: php Build/Scripts/setVersion.php <major>.<minor>.<patch>[-dev]' . chr(10));
    exit(2);
}
$rootDirectory = dirname(__DIR__, 2);

// The version of the documentation is the minor version, e.g. "1.2".
$documentationReplacements = [
    '/(<project\b[^>]*\srelease=")[^"]*(")/' => '${1}' . $version . '${2}',
    '/(<project\b[^>]*\sversion=")[^"]*(")/' => '${1}' . $versionParts[1] . '.' . $versionParts[2] . '${2}',
];
$replacementsPerFile = [
    'composer.json' => ['/("version":\s*")[^"]*(")/' => '${1}' . $version . '${2}'],
    'Documentation/guides.xml' => $documentationReplacements,
    'Documentation/Localization.de_DE/guides.xml' => $documentationReplacements,
];

// Every pattern has to match exactly once, otherwise no file is changed.
$contents = [];
foreach ($replacementsPerFile as $file => $replacements) {
    $path = $rootDirectory . '/' . $file;
    $content = is_file($path) ? (string)file_get_contents($path) : '';
    foreach ($replacements as $pattern => $replacement) {
        $content = (string)preg_replace($pattern, $replacement, $content, -1, $count);
        if ($count !== 1) {
            fwrite(STDERR, sprintf('%s: %s matched %d times instead of once. Nothing was changed.', $file, $pattern, $count) . chr(10));
            exit(1);
        }
    }
    $contents[$path] = $content;
}
foreach ($contents as $path => $content) {
    file_put_contents($path, $content);
}
echo sprintf('The version is now %s. Update the changelogs as well (CONTRIBUTING.md, "Releases").', $version) . chr(10);
exit(0);
