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
 * The help text of the field "Building blocks" of a role says what the manual says about
 * composing a role (Concept, "How TYPO3 combines building blocks"): the first building block
 * owns the new pages, and the kinds "Languages" and "Category mount" restrict. It names the kinds
 * by the labels of the kind select, in both languages.
 */
#[CoversNothing]
final class HelpTextTest extends UnitTestCase
{
    private const LABEL_PATH = __DIR__ . '/../../../Resources/Private/Language/';
    private const XLIFF_NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function labelFileDataProvider(): array
    {
        return [
            'English' => ['db.xlf', '"', '"'],
            'German' => ['de.db.xlf', '„', '“'],
        ];
    }

    #[Test]
    #[DataProvider('labelFileDataProvider')]
    public function theHelpOfTheMembersOfARoleNamesTheKindsThatRestrictAndTheOwnerOfNewPages(string $labelFile, string $openingQuote, string $closingQuote): void
    {
        $labels = $this->readLabels($labelFile);
        $help = $labels['be_groups.subgroup.role.description'] ?? '';

        foreach ([GroupKind::Language, GroupKind::CategoryMount] as $kind) {
            self::assertStringContainsString($openingQuote . ($labels['kind.' . $kind->value] ?? '') . $closingQuote, $help, $kind->value);
        }
        self::assertStringContainsStringIgnoringCase($labels['kind.' . GroupKind::PageGroup->value] ?? '', $help, GroupKind::PageGroup->value);
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
