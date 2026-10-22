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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewRecordCreationLinksEvent;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
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
     * The types of groups the wizard "Create new record" links to: the record types of the TCA
     * the core offers (NewRecordController::getRecordTypesForDirectCreation()), as changed by the
     * listeners of ModifyNewRecordCreationLinksEvent.
     *
     * @return list<string>
     */
    protected function getTypesOfferedByTheNewRecordWizard(): array
    {
        $links = [];
        foreach ($this->get(TcaSchemaFactory::class)->get('be_groups')->getSubSchemata() as $subSchema) {
            self::assertInstanceOf(TcaSchema::class, $subSchema);
            $creationOptions = $subSchema->getRawConfiguration()['creationOptions'] ?? [];
            if (!is_array($creationOptions) || (bool)($creationOptions['enableDirectRecordTypeCreation'] ?? true)) {
                $type = explode('.', $subSchema->getName(), 2)[1] ?? '';
                $links[$type] = ['url' => '/typo3/record/edit?defVals[be_groups][tx_begroups_kind]=' . $type, 'icon' => '', 'label' => $type];
            }
        }
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/record/new')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $event = new ModifyNewRecordCreationLinksEvent(
            ['backendaccess' => ['title' => 'Backend Access', 'icon' => '', 'items' => ['be_groups' => ['label' => 'Backend usergroup', 'icon' => '', 'types' => $links]]]],
            [],
            0,
            $request,
        );
        $this->get(EventDispatcherInterface::class)->dispatch($event);

        $group = $event->groupedCreationLinks['backendaccess'] ?? null;
        self::assertIsArray($group);
        $items = $group['items'] ?? null;
        self::assertIsArray($items);
        $item = $items['be_groups'] ?? null;
        self::assertIsArray($item);
        $types = $item['types'] ?? null;
        self::assertIsArray($types);
        return array_map(strval(...), array_keys($types));
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
