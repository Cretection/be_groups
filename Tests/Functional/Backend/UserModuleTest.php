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

namespace Cretection\BeGroups\Tests\Functional\Backend;

use Cretection\BeGroups\Domain\Kind\GroupKindLookup;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\EventListener\OverrideUserModuleTemplates;
use Cretection\BeGroups\EventListener\PrefixGroupsInUserFilter;
use Cretection\BeGroups\ViewHelpers\GroupTitleViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Beuser\Event\AfterBackendGroupFilterListIsAssembledEvent;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The core module "Users" shows groups with the prefix of their kind.
 */
#[CoversClass(OverrideUserModuleTemplates::class)]
#[CoversClass(PrefixGroupsInUserFilter::class)]
#[CoversClass(GroupTitleViewHelper::class)]
#[CoversClass(GroupKindLookup::class)]
#[CoversClass(KindPrefix::class)]
final class UserModuleTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $coreExtensionsToLoad = ['beuser'];

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Groups.csv');
    }

    #[Test]
    public function theTemplatesOfTheCoreAreThoseTheOverridesAreBasedOn(): void
    {
        // Fails when the core changes one of the templates: update the copies and their hashes.
        self::assertSame([], $this->get(OverrideUserModuleTemplates::class)->findChangedTemplates());
    }

    #[Test]
    public function overridesTheTemplatesOfTheUsersModule(): void
    {
        $templates = BackendUtility::getPagesTSconfig(0)['templates.'] ?? [];

        self::assertIsArray($templates);
        self::assertSame(
            ['1791446400' => 'cretection/be-groups:Resources/Private/TemplateOverrides/typo3/cms-beuser'],
            $templates['typo3/cms-beuser.'] ?? null,
        );
        $overridePath = $this->get(PackageManager::class)->getPackage('be_groups')->getPackagePath() . 'Resources/Private/TemplateOverrides/typo3/cms-beuser/';
        foreach (array_keys(OverrideUserModuleTemplates::BASE_TEMPLATES) as $file) {
            self::assertStringContainsString('begroups:groupTitle(', (string)file_get_contents($overridePath . $file), $file);
        }
    }

    #[Test]
    public function theOverridesOnlyDifferFromTheTemplatesOfTheCoreInTheGroupTitles(): void
    {
        // Fails when the hashes were updated without copying the changed templates of the core again.
        $corePath = $this->get(PackageManager::class)->getPackage('beuser')->getPackagePath() . 'Resources/Private/';
        $overridePath = $this->get(PackageManager::class)->getPackage('be_groups')->getPackagePath() . 'Resources/Private/TemplateOverrides/typo3/cms-beuser/';
        foreach (array_keys(OverrideUserModuleTemplates::BASE_TEMPLATES) as $file) {
            $override = preg_replace(
                ['#^    xmlns:begroups="http://typo3\\.org/ns/Cretection/BeGroups/ViewHelpers"\\n#m', '#\\{begroups:groupTitle\\(uid: [\\w.]+, title: ([\\w.]+)\\)\\}#'],
                ['', '{$1}'],
                (string)file_get_contents($overridePath . $file),
            );
            self::assertSame((string)file_get_contents($corePath . $file), $override, $file);
        }
    }

    #[Test]
    public function detectsChangedTemplatesOfTheCore(): void
    {
        $listener = $this->get(OverrideUserModuleTemplates::class);
        $corePath = $this->get(PackageManager::class)->getPackage('beuser')->getPackagePath() . 'Resources/Private/';
        $copyPath = Environment::getVarPath() . '/tests/beuser-templates/';
        foreach (array_keys(OverrideUserModuleTemplates::BASE_TEMPLATES) as $file) {
            @mkdir(dirname($copyPath . $file), 0777, true);
            copy($corePath . $file, $copyPath . $file);
        }
        file_put_contents($copyPath . 'Templates/BackendUserGroup/Show.fluid.html', "\n", FILE_APPEND);
        unlink($copyPath . 'Partials/Compare/Information.fluid.html');

        self::assertSame(
            ['Templates/BackendUserGroup/Show.fluid.html', 'Partials/Compare/Information.fluid.html'],
            $listener->findChangedTemplates($copyPath),
        );
    }

    #[Test]
    public function rendersGroupTitlesWithThePrefixOfTheirKind(): void
    {
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(templatePathAndFilename: __DIR__ . '/Fixtures/GroupTitle.fluid.html'));
        $view->assign('groups', [
            ['uid' => 1, 'title' => 'Editors'],
            ['uid' => '2', 'title' => 'Editors'],
            ['uid' => 3, 'title' => 'Legacy <b>'],
            ['uid' => 99, 'title' => 'Missing'],
        ]);

        self::assertSame('[META: Editors][ACL: Editors][Legacy &lt;b&gt;][Missing]', trim($view->render()));
    }

    #[Test]
    public function showsTheGroupsInTheGroupSelectorOfThePermissionsModuleWithThePrefixOnce(): void
    {
        // The module "Permissions" gets the groups from BackendUtility::getGroupNames() (@internal),
        // which sorts them by their record title, so the label_userFunc of be_groups already applies.
        $groups = [];
        foreach ([2, 3, 1] as $uid) {
            $groups[$uid] = ['title' => BackendUtility::getRecordTitle('be_groups', BackendUtility::getRecord('be_groups', $uid) ?? []), 'uid' => $uid];
        }

        $html = $this->renderTemplateOfTheUsersModule('Permission/ChangeGroupSelector', [
            'elementId' => 'g_1',
            'pageId' => 1,
            'groupUid' => 2,
            'groupname' => 'ACL: Editors',
            'groups' => $groups,
            'addCurrentGroup' => false,
        ]);

        preg_match_all('#<option value="(\\d+)"[^>]*>([^<]*)</option>#', $html, $options);
        self::assertSame(['0', '2', '3', '1'], $options[1]);
        self::assertSame(['', 'ACL: Editors', 'Legacy &lt;b&gt;', 'META: Editors'], $options[2]);
    }

    #[Test]
    public function showsTheGroupFilterWithThePrefixOfTheKinds(): void
    {
        $group = self::createStub(DomainObjectInterface::class);
        $group->method('getUid')->willReturn(2);
        $event = new AfterBackendGroupFilterListIsAssembledEvent(self::createStub(RequestInterface::class), ['', $group]);

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertSame(['', ['uid' => 2, 'title' => 'ACL: Editors']], $event->backendGroups);
    }

    /**
     * Renders a template of EXT:beuser as the module does, with the overrides of the page TSconfig.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTemplateOfTheUsersModule(string $template, array $variables): string
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/module/users/permissions')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $view = $this->get(BackendViewFactory::class)->create($request, ['typo3/cms-beuser']);
        $view->assignMultiple($variables);
        return $view->render($template);
    }
}
