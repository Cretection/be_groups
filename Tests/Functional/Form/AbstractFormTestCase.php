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

namespace Cretection\BeGroups\Tests\Functional\Form;

use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Compiles backend forms of be_groups and be_users the way the record editor does.
 */
abstract class AbstractFormTestCase extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../DataHandling/Fixtures/GroupsAndUsers.csv');
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function compileForm(string $table, int $uid, string $command = 'edit'): array
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
     * @return array<string, string> the labels of the items by value
     */
    protected function getItemLabels(array $formData, string $field): array
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
        $labels = [];
        foreach ($items as $item) {
            if (is_array($item) && is_scalar($item['value'] ?? null) && is_scalar($item['label'] ?? null)) {
                $labels[(string)$item['value']] = (string)$item['label'];
            }
        }
        return $labels;
    }

    /**
     * @param array<array-key, mixed> $formData
     * @return list<string>
     */
    protected function getItemValues(array $formData, string $field): array
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
        $values = [];
        foreach ($items as $item) {
            if (is_array($item) && is_scalar($item['value'] ?? null)) {
                $values[] = (string)$item['value'];
            }
        }
        return $values;
    }

    /**
     * @param array<array-key, mixed> $formData
     * @return list<string>
     */
    protected function getRowValues(array $formData, string $field): array
    {
        $databaseRow = $formData['databaseRow'] ?? null;
        self::assertIsArray($databaseRow);
        $value = $databaseRow[$field] ?? null;
        self::assertIsArray($value);
        $values = [];
        foreach ($value as $entry) {
            self::assertIsScalar($entry);
            $values[] = (string)$entry;
        }
        return $values;
    }
}
