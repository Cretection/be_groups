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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Beuser\Event\AfterBackendGroupFilterListIsAssembledEvent;
use TYPO3\CMS\Core\Core\Environment;
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
        unlink($copyPath . 'Templates/Permission/ChangeGroupSelector.fluid.html');

        self::assertSame(
            ['Templates/BackendUserGroup/Show.fluid.html', 'Templates/Permission/ChangeGroupSelector.fluid.html'],
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
    public function showsTheGroupFilterWithThePrefixOfTheKinds(): void
    {
        $group = self::createStub(DomainObjectInterface::class);
        $group->method('getUid')->willReturn(2);
        $event = new AfterBackendGroupFilterListIsAssembledEvent(self::createStub(RequestInterface::class), ['', $group]);

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertSame(['', ['uid' => 2, 'title' => 'ACL: Editors']], $event->backendGroups);
    }
}
