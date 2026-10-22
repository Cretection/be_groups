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
use Symfony\Component\Console\Helper\Helper;

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
     * Escapes the text like escape() and breaks it into lines of at most $width columns of the terminal,
     * at spaces where possible, e.g. for a cell of a table. The table helper of the console cannot wrap
     * escaped text itself: its wrapping removes the escaping, so the text would be read as formatting again.
     *
     * The width is measured like the table helper measures its cells: East Asian wide characters take
     * two columns, combining characters none.
     */
    public static function wrap(string $text, int $width): string
    {
        $width = max(1, $width);
        $lines = [];
        $line = '';
        foreach (explode(' ', self::toPrintable($text)) as $word) {
            if ($line !== '' && Helper::width($line) + 1 + Helper::width($word) > $width) {
                $lines[] = $line;
                $line = '';
            }
            $line .= ($line === '' ? '' : ' ') . $word;
            while (Helper::width($line) > $width) {
                [$head, $rest] = self::cut($line, $width);
                if ($rest === '') {
                    // A single character that is wider than the line
                    break;
                }
                $lines[] = $head;
                $line = $rest;
            }
        }
        $lines[] = $line;
        return implode("\n", array_map(OutputFormatter::escape(...), $lines));
    }

    /**
     * Cuts the text after as many characters (grapheme clusters) as fit into $width columns, but after
     * one character at least.
     *
     * @return array{string, string} the beginning and the rest of the text
     */
    private static function cut(string $text, int $width): array
    {
        preg_match_all('/\X/u', $text, $matches);
        $head = '';
        foreach ($matches[0] as $position => $character) {
            if ($head !== '' && Helper::width($head . $character) > $width) {
                return [$head, implode('', array_slice($matches[0], $position))];
            }
            $head .= $character;
        }
        return [$head, ''];
    }

    private static function toPrintable(string $text): string
    {
        // Invalid UTF-8 makes the first pattern fail; then every byte outside of printable ASCII is replaced.
        return preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}\p{Cf}\p{Zl}\p{Zp}]/u', ' ', $text)
            ?? preg_replace('/[^\x20-\x7E]/', '?', $text)
            ?? '';
    }
}
