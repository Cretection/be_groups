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

namespace Cretection\BeGroups\Tests\Functional\Controller;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Controller\OverviewController;
use Cretection\BeGroups\DataHandling\UidList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Overview\BuildingBlockItem;
use Cretection\BeGroups\Domain\Overview\BuildingBlockSection;
use Cretection\BeGroups\Domain\Overview\GroupItem;
use Cretection\BeGroups\Domain\Overview\KindGroups;
use Cretection\BeGroups\Domain\Overview\Overview;
use Cretection\BeGroups\Domain\Overview\OverviewBuilder;
use Cretection\BeGroups\Domain\Overview\RoleItem;
use Cretection\BeGroups\Domain\Overview\UserItem;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(OverviewController::class)]
#[CoversClass(OverviewBuilder::class)]
#[CoversClass(Overview::class)]
#[CoversClass(RoleItem::class)]
#[CoversClass(BuildingBlockItem::class)]
#[CoversClass(BuildingBlockSection::class)]
#[CoversClass(KindGroups::class)]
#[CoversClass(GroupItem::class)]
#[CoversClass(UserItem::class)]
#[CoversClass(BackendGroupRepository::class)]
#[CoversClass(BackendUserRepository::class)]
#[CoversClass(DatabaseRow::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(UidList::class)]
#[CoversClass(GroupKind::class)]
final class OverviewControllerTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE. The constant is @internal, but the
     * request attribute is required to render a backend module outside of the backend application.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../DataHandling/Fixtures/GroupsAndUsers.csv');
    }

    #[Test]
    public function rendersRolesWithTheirBuildingBlocksAndUsers(): void
    {
        $this->setUpBackendUserWithLanguage(1);

        $response = $this->get(OverviewController::class)->indexAction($this->createModuleRequest());

        self::assertSame(200, $response->getStatusCode());
        $html = (string)$response->getBody();
        self::assertStringContainsString('R editor', $html);
        self::assertStringContainsString('ACL editing', $html);
        self::assertStringContainsString('Classic all-in-one', $html);
    }

    #[Test]
    public function escapesGroupTitles(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['title' => '<script>alert(1)</script>'], ['uid' => 6]);

        $html = (string)$this->get(OverviewController::class)->indexAction($this->createModuleRequest())->getBody();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[Test]
    public function deniesAccessToNonAdministrators(): void
    {
        $this->setUpBackendUserWithLanguage(2);

        $response = $this->get(OverviewController::class)->indexAction($this->createModuleRequest());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', (string)$response->getBody());
    }

    private function setUpBackendUserWithLanguage(int $uid): void
    {
        $backendUser = $this->setUpBackendUser($uid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    private function createModuleRequest(): ServerRequestInterface
    {
        $module = $this->get(ModuleProvider::class)->getModule(OverviewController::MODULE_IDENTIFIER);
        self::assertNotNull($module);
        $serverParams = [
            'HTTP_HOST' => 'example.org',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/typo3/index.php',
            'REQUEST_URI' => '/typo3/module/users/roles',
        ];
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/module/users/roles', $serverParams)
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE)
            ->withAttribute('normalizedParams', new NormalizedParams($serverParams, [], Environment::getPublicPath() . '/typo3/index.php', Environment::getPublicPath() . '/'))
            ->withAttribute('route', new Route('/module/users/roles', ['packageName' => 'cretection/be-groups', '_identifier' => OverviewController::MODULE_IDENTIFIER, 'module' => $module]))
            ->withAttribute('module', $module)
            ->withAttribute('moduleData', ModuleData::createFromModule($module, []));
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $request;
    }
}
