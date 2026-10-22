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

namespace Cretection\BeGroups\Tests\Unit\Utility;

use Cretection\BeGroups\Utility\ConsoleTextUtility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ConsoleTextUtility::class)]
final class ConsoleTextUtilityTest extends UnitTestCase
{
    /**
     * @return array<string, array{string, int, string}>
     */
    public static function wrapDataProvider(): array
    {
        return [
            'short text' => ['Editors', 10, 'Editors'],
            'at spaces' => ['split: page_group, acl, db_mount', 18, "split: page_group,\nacl, db_mount"],
            'line of exactly the width' => ['abcd efgh', 4, "abcd\nefgh"],
            'word longer than a line' => ['ab abcdefghij', 4, "ab\nabcd\nefgh\nij"],
            'characters, not bytes' => ['Größe Maße', 10, 'Größe Maße'],
            // The table of the console sizes its columns by the width on the terminal.
            'wide characters' => ['日本語のグループ', 6, "日本語\nのグル\nープ"],
            'wide characters between words' => ['Gruppe 日本語 Redaktion', 10, "Gruppe\n日本語\nRedaktion"],
            'wide character wider than a line' => ['日本', 1, "日\n本"],
            'combining characters stay with their letter' => ["e\u{0301}e\u{0301}e\u{0301}", 2, "e\u{0301}e\u{0301}\ne\u{0301}"],
            'console formatting per line' => ['<info>a</info> <b>', 14, "\\<info\\>a\\</info\\>\n\\<b\\>"],
            'trailing backslash per line' => ['a\\ b', 2, "a\0\nb"],
            'control characters' => ["a\nb\e[2J\u{202E}c", 20, 'a b [2J c'],
        ];
    }

    #[Test]
    #[DataProvider('wrapDataProvider')]
    public function wrapsTextIntoEscapedLines(string $text, int $width, string $expected): void
    {
        self::assertSame($expected, ConsoleTextUtility::wrap($text, $width));
    }
}
