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

use Cretection\BeGroups\Domain\Audit\AuditSeverity;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The manual names what the backend shows: the kinds by the labels of their select, the form as
 * TYPO3 shows it, the menus of a German backend in the German manual, the severities as the
 * consistency check prints them and the configuration TYPO3 14 reads. Both languages are compared
 * with the labels of the extension, and the example of the label override with the site package
 * that the functional test loads.
 */
#[CoversNothing]
final class ManualTest extends UnitTestCase
{
    private const MANUAL_PATH = __DIR__ . '/../../../Documentation/';
    private const LABEL_PATH = __DIR__ . '/../../../Resources/Private/Language/';
    private const CHAPTERS = ['Introduction', 'Concept', 'Usage'];
    private const XLIFF_NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';
    private const SITE_PACKAGE_PATH = __DIR__ . '/../../Functional/Fixtures/Extensions/be_groups_test_sitepackage/';

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

    /**
     * @return array<string, array{string, string}>
     */
    public static function formDescriptionDataProvider(): array
    {
        return [
            'English' => ['', 'first field of a group is its'],
            'German' => ['Localization.de_DE/', 'erste Feld einer Gruppe ist ihr'],
        ];
    }

    #[Test]
    #[DataProvider('formDescriptionDataProvider')]
    public function theKindIsNotCalledTheFirstFieldOfAGroup(string $manual, string $formerText): void
    {
        // The kind follows the title, and a change of the kind saves the group to reload the form
        self::assertSame(0, substr_count($this->readTextOfChapter($manual, 'Usage'), $formerText), $formerText);
    }

    #[Test]
    public function theGermanManualNamesTheMenusOfAGermanBackend(): void
    {
        foreach (self::CHAPTERS as $chapter) {
            $text = $this->readTextOfChapter('Localization.de_DE/', $chapter);

            // A German backend shows "Verwaltung > …" and the core module "Berechtigungen"
            self::assertSame(0, substr_count($text, 'Administration >'), $chapter . ': Administration >');
            self::assertSame(0, substr_count($text, ':guilabel:`Permissions`'), $chapter . ': Permissions');
        }
    }

    #[Test]
    public function theChecksAreListedWithTheSeverityTheConsistencyCheckPrints(): void
    {
        $severities = [];
        foreach (self::manualDataProvider() as $language => [$manual]) {
            preg_match_all('/^\s*\*\s+-\s+``([a-z]+(?:-[a-z]+)+)``\s*\n\s+-\s+(.+?)\s*$/m', $this->readChapter($manual, 'Usage'), $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                $severities[$language][$row[1]] = $row[2];
            }
        }
        $printed = array_map(static fn(AuditSeverity $severity): string => '``' . $severity->value . '``', AuditSeverity::cases());

        self::assertNotSame([], $severities['English'] ?? [], 'the table of the checks');
        self::assertSame($severities['English'], $severities['German'] ?? null, 'both manuals list the same checks with the same severity');
        foreach ($severities['English'] as $identifier => $severity) {
            self::assertContains($severity, $printed, $identifier);
        }
    }

    #[Test]
    public function otherPrefixesAreConfiguredWithTheOptionOfTypo3V14(): void
    {
        foreach (self::manualDataProvider() as $language => [$manual]) {
            $usage = $this->readChapter($manual, 'Usage');

            self::assertGreaterThan(0, substr_count($usage, "['LANG']['resourceOverrides']['EXT:be_groups/Resources/Private/Language/db.xlf'][]"), $language . ': the option of TYPO3 14');
            self::assertSame(0, substr_count($usage, 'locallangXMLOverride'), $language . ': the option of TYPO3 13, which TYPO3 14 ignores');
        }
    }

    #[Test]
    #[DataProvider('manualDataProvider')]
    public function theSitePackageOfTheFunctionalTestUsesTheExampleOfTheManual(string $manual): void
    {
        $found = preg_match('/:caption: EXT:my_sitepackage\/ext_localconf\.php\n\n((?: {4}.*\n)+)/', $this->readChapter($manual, 'Usage'), $match);
        self::assertSame(1, $found, 'the example in ext_localconf.php');
        // Every line of the code block, with the indentation of the block removed and the inner one kept
        $example = str_replace('my_sitepackage', 'be_groups_test_sitepackage', (string)preg_replace('/^ {4}/m', '', $match[1]));

        self::assertStringContainsString($example, (string)file_get_contents(self::SITE_PACKAGE_PATH . 'ext_localconf.php'));
    }

    private function readChapter(string $manual, string $chapter): string
    {
        $content = file_get_contents(self::MANUAL_PATH . $manual . $chapter . '/Index.rst');
        self::assertIsString($content);
        return $content;
    }

    /**
     * @return string the chapter with every sequence of whitespace replaced by one space
     */
    private function readTextOfChapter(string $manual, string $chapter): string
    {
        return (string)preg_replace('/\s+/', ' ', $this->readChapter($manual, $chapter));
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
