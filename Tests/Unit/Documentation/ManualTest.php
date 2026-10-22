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
    private const CHAPTERS = ['Introduction', 'Installation', 'Configuration', 'Concept', 'Usage', 'Migration', 'Developer', 'Changelog', 'Credits'];
    private const XLIFF_NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';
    private const SITE_PACKAGE_PATH = __DIR__ . '/../../Functional/Fixtures/Extensions/be_groups_test_sitepackage/';
    private const DEVELOPER_EXTENSION_PATH = __DIR__ . '/../../Functional/Fixtures/Extensions/be_groups_test_developer/';

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
     * @return array<string, array{string}>
     */
    public static function manualPathDataProvider(): array
    {
        return [
            'English' => [''],
            'German' => ['Localization.de_DE/'],
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function correctedStatementDataProvider(): array
    {
        return [
            'English: saving a form never removes an assignment' => ['', 'never removes an existing assignment'],
            'German: saving a form never removes an assignment' => ['Localization.de_DE/', 'nie eine bestehende Zuweisung'],
            'English: the check finds rejected imports' => ['', 'Run the consistency check after imports'],
            'German: the check finds rejected imports' => ['Localization.de_DE/', 'Führen Sie nach Importen die Konsistenzprüfung aus'],
            'English: groups have a "disabled" flag' => ['', 'the "disabled" flag'],
            'German: groups have a "disabled" flag' => ['Localization.de_DE/', 'das Feld „Deaktiviert“'],
            'English: only fields of a former kind are information' => ['', 'emptied fields of a former kind as information'],
            'German: only fields of a former kind are information' => ['Localization.de_DE/', 'geleerte Felder eines früheren Typs als Information'],
            'English: the event only decides without a kind' => ['', 'only dispatched for new groups without a kind'],
            'German: the event only decides without a kind' => ['Localization.de_DE/', 'nur für neue Gruppen ohne Typ ausgelöst'],
            'English: the assistants roll back any change of other extensions' => ['', 'because another extension changes data while saving'],
            'German: the assistants roll back any change of other extensions' => ['Localization.de_DE/', 'weil eine andere Extension beim Speichern Daten ändert'],
            'English: every empty group needs a decision' => ['', 'an empty group, a permission field'],
            'German: every empty group needs a decision' => ['Localization.de_DE/', 'eine leere Gruppe, ein Rechtefeld'],
            'English: a failed conversion names all logged errors' => ['', 'the result names the errors the DataHandler logged'],
            'German: a failed conversion names all logged errors' => ['Localization.de_DE/', 'das Ergebnis nennt die vom DataHandler protokollierten Fehler'],
            'English: every classic group is split' => ['', 'A classic group. Split it into building blocks and a role.'],
            'German: every classic group is split' => ['Localization.de_DE/', 'Eine klassische Gruppe. Teilen Sie sie in Bausteine und eine Rolle auf.'],
            'English: only classic groups show fields of no kind' => ['', 'is only shown for classic groups and is not enforced'],
            'German: only classic groups show fields of no kind' => ['Localization.de_DE/', 'wird nur bei klassischen Gruppen angezeigt und nicht durchgesetzt'],
            'English: the form does not show fields of no kind' => ['', 'so its form does not show them'],
            'German: the form does not show fields of no kind' => ['Localization.de_DE/', 'sein Formular zeigt sie also nicht'],
            'English: permission sets combine with the kinds' => ['', 'can be combined with this extension'],
            'German: permission sets combine with the kinds' => ['Localization.de_DE/', 'lassen sich mit dieser Extension kombinieren'],
            'English: R3 only concerns building blocks' => ['', 'R3: Building blocks have no subgroups'],
            'German: R3 only concerns building blocks' => ['Localization.de_DE/', 'R3: Bausteine haben keine Untergruppen'],
            'English: groups of an unknown kind are listed like building blocks' => ['', 'Adding one of them, or a group with an unknown kind'],
            'German: groups of an unknown kind are listed like building blocks' => ['Localization.de_DE/', 'Wird eine davon oder eine Gruppe mit unbekanntem Typ hinzugefügt'],
            'English: lists show unknown kinds by their identifier' => ['', 'otherwise the identifier is shown'],
            'German: lists show unknown kinds by their identifier' => ['Localization.de_DE/', 'sonst wird die Kennung angezeigt'],
            'English: an import only empties relations' => ['', 'it first writes the fields empty and then sets the relations again'],
            'German: an import only empties relations' => ['Localization.de_DE/', 'er die Felder zuerst leer schreibt und die Verknüpfungen dann wieder setzt'],
        ];
    }

    #[Test]
    #[DataProvider('correctedStatementDataProvider')]
    public function theManualDoesNotRepeatACorrectedStatement(string $manual, string $statement): void
    {
        foreach (self::CHAPTERS as $chapter) {
            self::assertSame(0, substr_count($this->readTextOfChapter($manual, $chapter), $statement), $chapter);
        }
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function mainModuleDataProvider(): array
    {
        return [
            'English' => ['', ['Content', 'Media', 'Sites', 'Administration', 'System']],
            'German' => ['Localization.de_DE/', ['Inhalt', 'Medien', 'Websites', 'Verwaltung', 'System']],
        ];
    }

    /**
     * @param list<string> $mainModules
     */
    #[Test]
    #[DataProvider('mainModuleDataProvider')]
    public function menuPathsStartWithAMainModuleOfTypo3V14(string $manual, array $mainModules): void
    {
        foreach (self::CHAPTERS as $chapter) {
            // :guilabel:`System > Settings > …` and *Administration > Log*
            preg_match_all('/(?::guilabel:`|\*)([^`*]+?) > /', $this->readTextOfChapter($manual, $chapter), $paths);
            foreach ($paths[1] as $mainModule) {
                self::assertContains($mainModule, $mainModules, $chapter);
            }
        }
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
    #[DataProvider('manualPathDataProvider')]
    public function theSitePackageOfTheFunctionalTestUsesTheExampleOfTheManual(string $manual): void
    {
        $found = preg_match('/:caption: EXT:my_sitepackage\/ext_localconf\.php\n\n((?: {4}.*\n)+)/', $this->readChapter($manual, 'Usage'), $match);
        self::assertSame(1, $found, 'the example in ext_localconf.php');
        // Every line of the code block, with the indentation of the block removed and the inner one kept
        $example = str_replace('my_sitepackage', 'be_groups_test_sitepackage', (string)preg_replace('/^ {4}/m', '', $match[1]));

        self::assertStringContainsString($example, (string)file_get_contents(self::SITE_PACKAGE_PATH . 'ext_localconf.php'));
    }

    #[Test]
    #[DataProvider('manualPathDataProvider')]
    public function theExtensionOfTheFunctionalTestUsesTheExamplesOfTheDeveloperChapter(string $manual): void
    {
        preg_match_all('/:caption: EXT:my_extension\/(Configuration\/TCA\/Overrides\/[a-z_]+\.php)\n\n((?: {4}.*\n|\n)+)/', $this->readChapter($manual, 'Developer'), $blocks, PREG_SET_ORDER);
        self::assertNotSame([], $blocks, 'the examples of TCA overrides');

        foreach ($blocks as [, $file, $code]) {
            // The code with the indentation of the block and the head of the file removed
            $code = (string)preg_replace('/^ {4}/m', '', $code);
            $code = (string)preg_replace('/\A(?:<\?php|declare\(strict_types=1\);|use [^;]+;|defined\(\'TYPO3\'\) or die\(\);|\s)+/', '', $code);
            $code = str_replace('my_extension', 'be_groups_test_developer', rtrim($code));
            $content = file_get_contents(self::DEVELOPER_EXTENSION_PATH . $file);
            self::assertIsString($content, $file);
            self::assertStringContainsString($code, $content, $file);
        }
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
