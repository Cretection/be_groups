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
use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\GroupKindLookup;
use Cretection\BeGroups\Domain\Kind\KindDefinition;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
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
use Cretection\BeGroups\ViewHelpers\GroupTitleViewHelper;
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
#[CoversClass(RelationList::class)]
#[CoversClass(KindRegistry::class)]
#[CoversClass(KindDefinition::class)]
#[CoversClass(GroupTitleViewHelper::class)]
#[CoversClass(GroupKindLookup::class)]
#[CoversClass(KindPrefix::class)]
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
    public function showsTheKindOfMembersThatAreNoBuildingBlocks(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['subgroup' => '6,1'], ['uid' => 7]);

        $html = (string)$this->get(OverviewController::class)->indexAction($this->createModuleRequest())->getBody();

        self::assertMatchesRegularExpression(
            '#<span class="badge badge-danger" title="Roles may only contain building blocks\.[^"]*">not a building block</span>(?:(?!</div>).)*>META: R editor</a>#s',
            $html,
        );
    }

    #[Test]
    public function marksDisabledGroupsWhereverTheyAreListed(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['hidden' => 1], ['uid' => 1]);
        $connection->update('be_groups', ['hidden' => 1], ['uid' => 6]);

        $html = $this->renderModule();

        // TYPO3 does not apply hidden groups: own row and the rows of the roles "R editor" and "R other"
        self::assertSame(3, preg_match_all('#>ACL editing</a>\s*<span class="badge badge-default">disabled</span>#', $html));
        // Own row and "Used in" of "ACL editing" and "DBM site A"
        self::assertSame(3, preg_match_all('#>(?:META: )?R editor</a>\s*<span class="badge badge-default">disabled</span>#', $html));
    }

    #[Test]
    public function separatesRolesAndOtherGroupsUsingABuildingBlockWithoutASpaceBeforeTheComma(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        // "ACL editing" is part of the roles "R editor" and "R other" and of the classic group "Classic all-in-one"
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['subgroup' => '1'], ['uid' => 5]);

        $html = $this->renderModule();

        self::assertMatchesRegularExpression('#>META: R other</a>, (?:(?!</td>).)*>Classic all-in-one</a>#s', $html);
        self::assertDoesNotMatchRegularExpression('#</a>\s+,#', $html);
    }

    #[Test]
    public function namesAMissingKindOfAGroup(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['tx_begroups_kind' => ''], ['uid' => 9]);
        $connection->update('be_groups', ['tx_begroups_kind' => '0'], ['uid' => 5]);

        $html = $this->renderModule();

        self::assertStringContainsString('<span class="badge badge-warning">no kind</span>', $html);
        self::assertStringNotContainsString('kind &quot;&quot;', $html);
        // The numeric kind of old versions is still named
        self::assertStringContainsString('<span class="badge badge-warning">kind &quot;0&quot;</span>', $html);
    }

    #[Test]
    public function sortsTitlesForTheLanguageOfTheEditor(): void
    {
        $this->setUpBackendUser(1);
        // Swedish sorts "Ä" after "Z", English and German sort it with "A"
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('sv');
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $connection->update('be_groups', ['title' => 'Ärzte'], ['uid' => 6]);
        $connection->update('be_groups', ['title' => 'Zentrale'], ['uid' => 7]);

        $html = $this->renderModule();

        self::assertMatchesRegularExpression('#<th scope="row">(?:(?!</th>).)*>Zentrale</a>.*<th scope="row">(?:(?!</th>).)*>Ärzte</a>#s', $html);
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

    /**
     * @param array<string, string> $moduleData
     */
    private function renderModule(array $moduleData = []): string
    {
        return (string)$this->get(OverviewController::class)->indexAction($this->createModuleRequest($moduleData))->getBody();
    }

    private function setUpBackendUserWithLanguage(int $uid): void
    {
        $backendUser = $this->setUpBackendUser($uid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @param array<string, string> $moduleData
     */
    private function createModuleRequest(array $moduleData = []): ServerRequestInterface
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
            ->withAttribute('moduleData', ModuleData::createFromModule($module, $moduleData));
        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $request;
    }
}
