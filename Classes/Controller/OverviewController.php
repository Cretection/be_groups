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

namespace Cretection\BeGroups\Controller;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Overview\OverviewBuilder;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Administration > Roles & Building Blocks: a read-only overview of the permission structure.
 *
 * Shows which building blocks every role consists of, which users have which role,
 * where a building block is used and where the structure is inconsistent.
 * All changes are made in the regular record forms, so the DataHandler rules apply.
 *
 * @internal
 */
#[AsController]
final readonly class OverviewController
{
    public const MODULE_IDENTIFIER = 'begroups_overview';
    private const LABEL_DOMAIN = 'be_groups.modules.overview';
    private const KIND_FILTER_PATTERN = '/^[a-z0-9_]{1,64}$/';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private ComponentFactory $componentFactory,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
        private BackendGroupRepository $backendGroupRepository,
        private BackendUserRepository $backendUserRepository,
        private OverviewBuilder $overviewBuilder,
        private ExtensionSettings $extensionSettings,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        // The module is registered for admins only; this is a second line of defence.
        $backendUser = $this->getBackendUser();
        if ($backendUser === null || !$backendUser->isAdmin()) {
            return $this->responseFactory->createResponse(403);
        }

        $moduleData = $request->getAttribute('moduleData');
        $sorting = OverviewBuilder::SORT_TITLE;
        $kindFilter = '';
        if ($moduleData instanceof ModuleData) {
            $moduleData->clean('sorting', OverviewBuilder::SORTINGS);
            $requestedSorting = $moduleData->get('sorting');
            $sorting = is_string($requestedSorting) ? $requestedSorting : OverviewBuilder::SORT_TITLE;
            $requestedKind = $moduleData->get('kind');
            $kindFilter = is_string($requestedKind) && preg_match(self::KIND_FILTER_PATTERN, $requestedKind) === 1 ? $requestedKind : '';
        }

        $overview = $this->overviewBuilder->build(
            $this->backendGroupRepository->findAllForOverview(),
            $this->backendUserRepository->findAllForOverview(),
            $sorting,
            $kindFilter,
        );

        $normalizedParams = $request->getAttribute('normalizedParams');
        $returnUrl = $normalizedParams instanceof NormalizedParams ? $normalizedParams->getRequestUri() : '';

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->translate('title'));
        $this->registerDocHeaderButtons($view, $returnUrl, $sorting, $kindFilter);

        return $view->assignMultiple([
            'overview' => $overview,
            'sorting' => $sorting,
            'sortings' => OverviewBuilder::SORTINGS,
            'kindFilter' => $kindFilter,
            'classicGroupsAllowed' => $this->extensionSettings->isClassicGroupsAllowed(),
            'moduleIdentifier' => self::MODULE_IDENTIFIER,
            'returnUrl' => $returnUrl,
        ])->renderResponse('Overview/Index');
    }

    private function registerDocHeaderButtons(ModuleTemplate $view, string $returnUrl, string $sorting, string $kindFilter): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton(
            $this->componentFactory->createLinkButton()
                ->setHref($this->buildNewGroupUri(GroupKind::Role, $returnUrl))
                ->setTitle($this->translate('button.newRole'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL)),
        );
        $buttonBar->addButton(
            $this->componentFactory->createLinkButton()
                ->setHref($this->buildNewGroupUri(GroupKind::AccessControl, $returnUrl))
                ->setTitle($this->translate('button.newBuildingBlock'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL)),
        );
        $view->getDocHeaderComponent()->setShortcutContext(
            self::MODULE_IDENTIFIER,
            $this->translate('title'),
            array_filter(['sorting' => $sorting, 'kind' => $kindFilter], static fn(string $value): bool => $value !== ''),
        );
    }

    private function buildNewGroupUri(GroupKind $kind, string $returnUrl): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => ['be_groups' => [0 => 'new']],
            'defVals' => ['be_groups' => [GroupKind::FIELD_NAME => $kind->value]],
            'returnUrl' => $returnUrl,
        ]);
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return $key;
        }
        return (string)$languageService->translate($key, self::LABEL_DOMAIN, [], $key);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}
