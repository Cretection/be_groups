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

namespace Cretection\BeGroups\Command;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Prints data of editors (e.g. group titles) on the console as plain text.
 *
 * @internal
 */
final class ConsoleText
{
    /**
     * The text must neither be read as console formatting nor carry control characters
     * (e.g. ANSI escape sequences) into the terminal.
     */
    public static function escape(string $text): string
    {
        // Invalid UTF-8 makes the first pattern fail; then every byte outside of printable ASCII is replaced.
        $printable = preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}]/u', ' ', $text)
            ?? preg_replace('/[^\x20-\x7E]/', '?', $text);
        return OutputFormatter::escape($printable ?? '');
    }
}
