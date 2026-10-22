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

namespace Cretection\BeGroups\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The option in "Settings > Extension Configuration". The core takes the text of the label
 * before the first colon as title and the rest as description, and lists options without a
 * subcategory of its own under "Other".
 */
#[CoversNothing]
final class ExtensionConfigurationTest extends FunctionalTestCase
{
    private const LABEL = 'LLL:EXT:be_groups/Resources/Private/Language/db.xlf:extConf.allowClassicGroups';

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function languageDataProvider(): array
    {
        return [
            'English' => ['en', 'Allow classic groups', '"Classic"'],
            'German' => ['de', 'Klassische Gruppen erlauben', '„Klassisch“'],
        ];
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function optionHasATitleAndADescriptionInSentences(string $language, string $expectedTitle, string $expectedKind): void
    {
        $label = $this->get(LanguageServiceFactory::class)->create($language)->sL(self::LABEL);

        [$title, $description] = explode(':', $label, 2) + ['', ''];
        self::assertSame($expectedTitle, $title);
        self::assertMatchesRegularExpression('/^ \p{Lu}/u', $description);
        self::assertStringContainsString($expectedKind, $description);
    }

    #[Test]
    public function optionIsListedUnderEnableFeatures(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../ext_conf_template.txt');

        self::assertIsString($template);
        self::assertStringStartsWith('# cat=basic/enable/10; type=boolean; label=' . self::LABEL . "\n", $template);
    }
}
