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

namespace Cretection\BeGroups\Utility;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Prints data of editors (e.g. group titles) on the console as plain text.
 *
 * @internal
 */
final class ConsoleTextUtility
{
    /**
     * The text must neither be read as console formatting nor carry control characters (e.g. ANSI
     * escape sequences) into the terminal, nor invisible formatting characters such as bidirectional
     * overrides, which could display it in a different order than it is.
     */
    public static function escape(string $text): string
    {
        return OutputFormatter::escape(self::toPrintable($text));
    }

    /**
     * Escapes the text like escape() and breaks it into lines of at most $width characters, at spaces
     * where possible, e.g. for a cell of a table. The table helper of the console cannot wrap escaped
     * text itself: its wrapping removes the escaping, so the text would be read as formatting again.
     */
    public static function wrap(string $text, int $width): string
    {
        $width = max(1, $width);
        $lines = [];
        $line = '';
        foreach (explode(' ', self::toPrintable($text)) as $word) {
            if ($line !== '' && mb_strlen($line) + 1 + mb_strlen($word) > $width) {
                $lines[] = $line;
                $line = '';
            }
            $line .= ($line === '' ? '' : ' ') . $word;
            while (mb_strlen($line) > $width) {
                $lines[] = mb_substr($line, 0, $width);
                $line = mb_substr($line, $width);
            }
        }
        $lines[] = $line;
        return implode("\n", array_map(OutputFormatter::escape(...), $lines));
    }

    private static function toPrintable(string $text): string
    {
        // Invalid UTF-8 makes the first pattern fail; then every byte outside of printable ASCII is replaced.
        return preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}\p{Cf}\p{Zl}\p{Zp}]/u', ' ', $text)
            ?? preg_replace('/[^\x20-\x7E]/', '?', $text)
            ?? '';
    }
}
