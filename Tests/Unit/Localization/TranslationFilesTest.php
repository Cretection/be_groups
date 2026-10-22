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

namespace Cretection\BeGroups\Tests\Unit\Localization;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The translations (e.g. "de.db.xlf") are uploaded to Crowdin as existing translations, which
 * are matched by their English source: it has to be the source of the English file.
 */
#[CoversNothing]
final class TranslationFilesTest extends UnitTestCase
{
    private const LANGUAGE_DIRECTORY = __DIR__ . '/../../../Resources/Private/Language';

    /**
     * @return array<string, array{string, string}>
     */
    public static function translationFileDataProvider(): array
    {
        $files = [];
        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::LANGUAGE_DIRECTORY, \FilesystemIterator::SKIP_DOTS));
        foreach ($directory as $file) {
            if (!$file instanceof \SplFileInfo || preg_match('/^[a-z]{2}(?:_[A-Z]{2})?\.(.+\.xlf)$/', $file->getFilename(), $matches) !== 1) {
                continue;
            }
            $translationFile = $file->getPathname();
            $files[substr($translationFile, strlen(self::LANGUAGE_DIRECTORY) + 1)] = [$translationFile, $file->getPath() . '/' . $matches[1]];
        }
        ksort($files);
        return $files;
    }

    #[Test]
    #[DataProvider('translationFileDataProvider')]
    public function translationHasTheUnitsAndSourcesOfTheEnglishFile(string $translationFile, string $englishFile): void
    {
        self::assertFileExists($englishFile);
        self::assertSame(self::getSources($englishFile), self::getSources($translationFile));
    }

    /**
     * @return array<string, string> the sources by the id of their unit
     */
    private static function getSources(string $file): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($file), $file);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = $xpath->query('//xliff:trans-unit');
        self::assertInstanceOf(\DOMNodeList::class, $units);
        $sources = [];
        foreach ($units as $unit) {
            self::assertInstanceOf(\DOMElement::class, $unit);
            $source = $xpath->query('xliff:source', $unit);
            self::assertInstanceOf(\DOMNodeList::class, $source);
            $sourceElement = $source->item(0);
            self::assertInstanceOf(\DOMElement::class, $sourceElement, $unit->getAttribute('id'));
            $sources[$unit->getAttribute('id')] = $sourceElement->textContent;
        }
        ksort($sources);
        return $sources;
    }
}
