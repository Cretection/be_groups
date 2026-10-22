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

namespace Cretection\BeGroups\Tests\Unit\Documentation;

use Cretection\BeGroups\Domain\Kind\GroupKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The manual names what the backend shows: the kinds by the labels of their select. Both
 * languages are compared with the labels of the extension.
 */
#[CoversNothing]
final class ManualTest extends UnitTestCase
{
    private const MANUAL_PATH = __DIR__ . '/../../../Documentation/';
    private const LABEL_PATH = __DIR__ . '/../../../Resources/Private/Language/';
    private const CHAPTERS = ['Introduction', 'Concept', 'Usage'];
    private const XLIFF_NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';

    /**
     * @return array<string, array{string, string}>
     */
    public static function manualDataProvider(): array
    {
        return [
            'English' => ['', 'db.xlf'],
            'German' => ['Localization.de_DE/', 'de.db.xlf'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function mentionDataProvider(): array
    {
        return [
            'English: kind "…"' => ['', 'db.xlf', '/\bkind\s+"([^"]+)"/'],
            'German: Typ „…“' => ['Localization.de_DE/', 'de.db.xlf', '/\bTyp\s+„([^“]+)“/u'],
        ];
    }

    #[Test]
    #[DataProvider('manualDataProvider')]
    public function theTableOfTheKindsNamesEveryKindWithItsLabel(string $manual, string $labelFile): void
    {
        $labels = $this->readLabels($labelFile);
        preg_match_all('/^\s*\*\s+-\s+``([a-z_]+)``\s*\n\s+-\s+(.+?)\s*$/m', $this->readChapter($manual, 'Concept'), $rows, PREG_SET_ORDER);
        $names = [];
        foreach ($rows as $row) {
            $names[$row[1]] = $row[2];
        }

        foreach (GroupKind::cases() as $kind) {
            self::assertSame($labels['kind.' . $kind->value] ?? null, $names[$kind->value] ?? null, $kind->value);
        }
    }

    #[Test]
    #[DataProvider('manualDataProvider')]
    public function theTableOfThePrefixesNamesEveryKindWithItsLabel(string $manual, string $labelFile): void
    {
        $labels = $this->readLabels($labelFile);
        preg_match_all('/^\s*\*\s+-\s+``([A-Z]+)``\s*\n\s+-\s+(.+?)\s*$/m', $this->readChapter($manual, 'Usage'), $rows, PREG_SET_ORDER);
        $names = [];
        foreach ($rows as $row) {
            $names[$row[1]] = $row[2];
        }

        foreach (GroupKind::cases() as $kind) {
            $prefix = $labels['kind.prefix.' . $kind->value] ?? null;
            if ($prefix !== null) {
                self::assertSame($labels['kind.' . $kind->value] ?? null, $names[$prefix] ?? null, $kind->value);
            }
        }
    }

    #[Test]
    #[DataProvider('mentionDataProvider')]
    public function kindsMentionedInTheTextAreNamedWithTheirLabel(string $manual, string $labelFile, string $mentionPattern): void
    {
        $labels = $this->readLabels($labelFile);
        $kindLabels = array_map(static fn(GroupKind $kind): ?string => $labels['kind.' . $kind->value] ?? null, GroupKind::cases());

        foreach (self::CHAPTERS as $chapter) {
            preg_match_all($mentionPattern, $this->readChapter($manual, $chapter), $mentions);
            foreach ($mentions[1] as $mention) {
                self::assertContains((string)preg_replace('/\s+/', ' ', $mention), $kindLabels, $chapter);
            }
        }
    }

    private function readChapter(string $manual, string $chapter): string
    {
        $content = file_get_contents(self::MANUAL_PATH . $manual . $chapter . '/Index.rst');
        self::assertIsString($content);
        return $content;
    }

    /**
     * @return array<string, string> the labels shown in the backend (the source in English, the target in German), by id
     */
    private function readLabels(string $labelFile): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::LABEL_PATH . $labelFile));
        $element = str_starts_with($labelFile, 'de.') ? 'target' : 'source';
        $labels = [];
        foreach ($document->getElementsByTagNameNS(self::XLIFF_NAMESPACE, 'trans-unit') as $unit) {
            $text = $unit->getElementsByTagNameNS(self::XLIFF_NAMESPACE, $element)->item(0)?->textContent;
            if ($text !== null) {
                $labels[$unit->getAttribute('id')] = $text;
            }
        }
        return $labels;
    }
}
