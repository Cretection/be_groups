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

namespace Cretection\BeGroups\Tests\Functional\Documentation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * An extension that follows the chapter Developer of the manual gets what the chapter promises:
 * its permission field is shown for its kind and for classic groups, and its own kind is listed
 * among the building blocks in the kind selector and in the forms of roles and users. The test
 * extension contains the examples of the chapter (ManualTest compares them) and depends on
 * be_groups, as the chapter says.
 */
#[CoversNothing]
final class DeveloperExamplesTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_developer',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DeveloperExamples.csv');
    }

    #[Test]
    public function permissionFieldsAreShownForTheirKindAndForClassicGroups(): void
    {
        $schema = $this->get(TcaSchemaFactory::class)->get('be_groups');

        // Classic groups show every permission field, like the groups of TYPO3 without be_groups.
        self::assertTrue($schema->getSubSchema('acl')->hasField('tx_myextension_permissions'), 'acl');
        self::assertTrue($schema->getSubSchema('classic')->hasField('tx_myextension_permissions'), 'classic');
        self::assertTrue($schema->getSubSchema('my_news')->hasField('tx_myextension_news_permissions'), 'my_news');
        self::assertTrue($schema->getSubSchema('classic')->hasField('tx_myextension_news_permissions'), 'classic');
        self::assertFalse($schema->getSubSchema('role')->hasField('tx_myextension_permissions'), 'role');
    }

    #[Test]
    public function theOwnKindIsOfferedAmongTheBuildingBlocks(): void
    {
        $groups = $this->getItemsByGroup($this->compileForm('be_groups', 0, 'new'), 'tx_begroups_kind');

        self::assertContains('my_news', $groups['buildingBlocks'] ?? [], 'the kind selector');
        self::assertNotContains('my_news', $groups[''] ?? [], 'the kind selector');
    }

    #[Test]
    public function ownBuildingBlocksAreListedBeforeTheGroupsThatCannotBeAddedToARole(): void
    {
        $groups = array_keys($this->getItemsByGroup($this->compileForm('be_groups', 4), 'subgroup'));

        $position = array_search('my_news', $groups, true);
        self::assertIsInt($position, implode(', ', $groups));
        self::assertLessThan(array_search('role', $groups, true), $position, implode(', ', $groups));
        self::assertLessThan(array_search('classic', $groups, true), $position, implode(', ', $groups));
    }

    #[Test]
    public function ownBuildingBlocksAreMarkedForAssignmentThroughARoleInTheUserForm(): void
    {
        $labels = $this->getGroupLabels($this->compileForm('be_users', 1), 'usergroup');

        // The same hint as the building blocks of be_groups, e.g. "Access rights (building blocks, assign through a role)"
        $hint = substr($labels['acl'] ?? '', strlen('Access rights'));
        self::assertStringStartsWith(' (', $hint);
        self::assertSame('News' . $hint, $labels['my_news'] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function compileForm(string $table, int $uid, string $command = 'edit'): array
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/record/edit')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $this->get(FormDataCompiler::class)->compile(
            ['request' => $request, 'tableName' => $table, 'vanillaUid' => $uid, 'command' => $command],
            $this->get(TcaDatabaseRecord::class),
        );
    }

    /**
     * @param array<array-key, mixed> $formData
     * @return array<string, list<string>> the values of the items of a select field by the group they are listed in, in the order of the groups ("" for items before the first group)
     */
    private function getItemsByGroup(array $formData, string $field): array
    {
        $groups = [];
        $group = '';
        foreach ($this->getItems($formData, $field) as $item) {
            self::assertIsArray($item);
            $value = is_scalar($item['value'] ?? null) ? (string)$item['value'] : '';
            if ($value === '--div--') {
                $group = is_scalar($item['group'] ?? null) ? (string)$item['group'] : '';
                $groups[$group] ??= [];
                continue;
            }
            $groups[$group][] = $value;
        }
        return $groups;
    }

    /**
     * @param array<array-key, mixed> $formData
     * @return array<string, string> the labels of the groups of a select field by group
     */
    private function getGroupLabels(array $formData, string $field): array
    {
        $labels = [];
        foreach ($this->getItems($formData, $field) as $item) {
            if (is_array($item) && ($item['value'] ?? null) === '--div--' && is_scalar($item['group'] ?? null) && is_scalar($item['label'] ?? null)) {
                $labels[(string)$item['group']] = (string)$item['label'];
            }
        }
        return $labels;
    }

    /**
     * @param array<array-key, mixed> $formData
     * @return array<array-key, mixed>
     */
    private function getItems(array $formData, string $field): array
    {
        $processedTca = $formData['processedTca'] ?? null;
        self::assertIsArray($processedTca);
        $columns = $processedTca['columns'] ?? null;
        self::assertIsArray($columns);
        $column = $columns[$field] ?? null;
        self::assertIsArray($column);
        $config = $column['config'] ?? null;
        self::assertIsArray($config);
        $items = $config['items'] ?? null;
        self::assertIsArray($items);
        return $items;
    }
}
