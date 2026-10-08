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

// Downloads a file once and checks its SHA-256 checksum before every use, e.g. the PHAR of a tool
// that must not become a dependency: php Build/Scripts/downloadVerifiedFile.php <url> <file> <sha256>
// A file with another checksum is removed, so that it is never executed.

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

[, $url, $file, $checksum] = $argv + [null, '', '', ''];
if (!str_starts_with($url, 'https://') || $file === '' || preg_match('/^[0-9a-f]{64}$/', $checksum) !== 1) {
    fwrite(STDERR, 'Usage: php Build/Scripts/downloadVerifiedFile.php <https URL> <file> <sha256>' . chr(10));
    exit(2);
}

if (!is_file($file)) {
    $content = file_get_contents($url);
    if ($content === false || file_put_contents($file . '.download', $content) === false || !rename($file . '.download', $file)) {
        fwrite(STDERR, sprintf('Unable to download "%s" to "%s".', $url, $file) . chr(10));
        exit(1);
    }
}

$actualChecksum = hash_file('sha256', $file);
if ($actualChecksum !== $checksum) {
    unlink($file);
    fwrite(STDERR, sprintf('"%s" has the SHA-256 checksum "%s", not "%s". The file was removed.', $file, (string)$actualChecksum, $checksum) . chr(10));
    exit(1);
}
exit(0);
