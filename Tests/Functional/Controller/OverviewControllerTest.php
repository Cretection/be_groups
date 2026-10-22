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
            '#<span class="badge badge-danger">not a building block</span>(?:(?!</div>).)*>META: R editor</bdi></a>#s',
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
        self::assertSame(3, preg_match_all('#>ACL editing</bdi></a>\s*<span class="badge badge-default">disabled</span>#', $html));
        // Own row and "Used in" of "ACL editing" and "DBM site A"
        self::assertSame(3, preg_match_all('#>(?:META: )?R editor</bdi></a>\s*<span class="badge badge-default">disabled</span>#', $html));
    }

    #[Test]
    public function offersANewBuildingBlockOfEveryKind(): void
    {
        $this->setUpBackendUserWithLanguage(1);

        $html = $this->renderModule();

        // The form starts with the kind, as changing the kind of a group saves it
        $newGroupOfKind = static fn(string $kind): string => 'edit%5Bbe_groups%5D%5B0%5D=new&amp;defVals%5Bbe_groups%5D%5Btx_begroups_kind%5D=' . $kind . '&amp;';
        self::assertStringContainsString($newGroupOfKind('role'), $html);
        foreach (['acl', 'page_group', 'db_mount', 'file_mount', 'file_operations', 'category_mount', 'language', 'tsconfig'] as $kind) {
            self::assertStringContainsString($newGroupOfKind($kind), $html, $kind);
        }
        self::assertStringNotContainsString($newGroupOfKind('classic'), $html);
    }

    #[Test]
    public function marksTheSelectedSortingAndKindWithACheckIcon(): void
    {
        $this->setUpBackendUserWithLanguage(1);

        $html = $this->renderModule();

        self::assertSame(2, substr_count($html, 'data-identifier="actions-check"'));
        self::assertMatchesRegularExpression('#aria-current="true"\s*><span [^>]*data-identifier="actions-check"(?:(?!</a>).)*\bTitle</a>#s', $html);
        self::assertMatchesRegularExpression('#aria-current="true"\s*><span [^>]*data-identifier="actions-check"(?:(?!</a>).)*\bAll kinds</a>#s', $html);

        $html = $this->renderModule(['sorting' => OverviewBuilder::SORT_USAGE, 'kind' => 'acl']);

        self::assertSame(2, substr_count($html, 'data-identifier="actions-check"'));
        self::assertMatchesRegularExpression('#aria-current="true"\s*><span [^>]*data-identifier="actions-check"(?:(?!</a>).)*\bUsage</a>#s', $html);
        self::assertMatchesRegularExpression('#aria-current="true"\s*><span [^>]*data-identifier="actions-check"(?:(?!</a>).)*\bAccess rights</a>#s', $html);
    }

    #[Test]
    public function explainsMembersThatAreNoBuildingBlocksInTheText(): void
    {
        $this->setUpBackendUserWithLanguage(1);

        // "R other" contains the classic group "Classic all-in-one"
        $html = $this->renderModule();

        self::assertMatchesRegularExpression('#<p class="[^"]*">Roles may only contain building blocks\. TYPO3 still grants the permissions of this group; replace it with building blocks that grant the same\.</p>#', $html);
        // A tooltip reaches neither keyboard nor touch users
        self::assertStringNotContainsString('title="Roles may only contain building blocks', $html);

        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['subgroup' => '5,9,1'], ['uid' => 7]);

        self::assertMatchesRegularExpression('#<p class="[^"]*">Roles may only contain building blocks\. TYPO3 still grants the permissions of these groups; replace them with building blocks that grant the same\.</p>#', $this->renderModule());
    }

    #[Test]
    public function separatesRolesAndOtherGroupsUsingABuildingBlockWithoutASpaceBeforeTheComma(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        // "ACL editing" is part of the roles "R editor" and "R other" and of the classic group "Classic all-in-one"
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['subgroup' => '1'], ['uid' => 5]);

        $html = $this->renderModule();

        self::assertMatchesRegularExpression('#>META: R other</bdi></a>, (?:(?!</td>).)*>Classic all-in-one</bdi></a>#s', $html);
        self::assertDoesNotMatchRegularExpression('#</(?:bdi|a|span)>\s+,#', $html);
    }

    /**
     * A link without text has no accessible name: untitled groups are named like in the lists of the core.
     */
    #[Test]
    public function namesUntitledGroupsInTheirLinks(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        // A building block, a role in "Used in" and a classic group, also as invalid member of "R other"
        foreach ([1, 6, 5] as $uid) {
            $connection->update('be_groups', ['title' => ''], ['uid' => $uid]);
        }

        $html = $this->renderModule();

        self::assertDoesNotMatchRegularExpression('#<bdi>\s*</bdi>|<bdi>META: </bdi>#', $html);
        self::assertMatchesRegularExpression('#<a title="Edit &quot;\[No title\]&quot;" href="[^"]+"><bdi>\[No title\]</bdi></a>#', $html);
        self::assertMatchesRegularExpression('#<a title="Edit &quot;META: \[No title\]&quot;" href="[^"]+"><bdi>META: \[No title\]</bdi></a>#', $html);
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
    public function explainsWhatToDoWithWorkspaceBuildingBlocksWithoutWorkspaces(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        // Without EXT:workspaces, the kind "workspace" is not configured
        $this->get(ConnectionPool::class)->getConnectionForTable('be_groups')
            ->update('be_groups', ['tx_begroups_kind' => 'workspace', 'allowed_languages' => ''], ['uid' => 9]);

        $html = $this->renderModule();

        self::assertMatchesRegularExpression(
            '#<h2>Workspace building blocks without EXT:workspaces <span class="badge badge-warning">1</span></h2>(?:(?!<h2>).)*>Classic default language</bdi></a>#s',
            $html,
        );
        // Choosing another kind would clear the access to the live workspace
        self::assertStringContainsString('Keep them if EXT:workspaces may be installed again: choosing another kind clears that access, which their users then lack.', $html);
        self::assertStringNotContainsString('Assign a kind.', $html);
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

        self::assertMatchesRegularExpression('#<th scope="row">(?:(?!</th>).)*>Zentrale</bdi></a>.*<th scope="row">(?:(?!</th>).)*>Ärzte</bdi></a>#s', $html);
    }

    #[Test]
    public function isolatesTitlesAndUsernamesFromTheTextAroundThem(): void
    {
        $this->setUpBackendUserWithLanguage(1);
        // A right-to-left override in a title must not reorder the groups and commas that follow it
        $groups = $this->get(ConnectionPool::class)->getConnectionForTable('be_groups');
        $groups->update('be_groups', ['title' => "ACL \u{202E}editing"], ['uid' => 1]);
        $groups->update('be_groups', ['title' => "R \u{202E}editor"], ['uid' => 6]);
        $this->get(ConnectionPool::class)->getConnectionForTable('be_users')
            ->update('be_users', ['username' => "mi\u{202E}xed", 'realName' => "Max \u{202E}Mustermann"], ['uid' => 4]);

        $html = $this->renderModule();

        // Building block in the rows of the roles, role in "Used in", user and name in the lists of users
        self::assertStringContainsString("<bdi>ACL \u{202E}editing</bdi>", $html);
        self::assertStringContainsString("<bdi>META: R \u{202E}editor</bdi>", $html);
        self::assertStringContainsString("<bdi>mi\u{202E}xed</bdi>", $html);
        self::assertStringContainsString("(<bdi>Max \u{202E}Mustermann</bdi>)", $html);
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
        // Escaped once, in the links of the role and of "Used in" and in their titles
        self::assertStringNotContainsString('&amp;lt;', $html);
        self::assertSame(6, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
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
