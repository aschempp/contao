<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\DataContainer;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\Routing\Route;
use Twig\Environment;

class PageRoutingListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly PageRegistry $pageRegistry,
        private readonly Environment $twig,
    ) {
    }

    #[AsCallback(table: 'tl_page', target: 'fields.routePath.input_field')]
    public function generateRoutePath(DataContainer $dc): string
    {
        $pageModel = $this->framework->getAdapter(PageModel::class)->findByPk($dc->id);

        if (!$pageModel) {
            return '';
        }

        return $this->twig->render('@ContaoCore/Backend/be_route_path.html.twig', [
            'path' => $this->getPathWithParameters($this->pageRegistry->getRoute($pageModel)),
        ]);
    }

    #[AsCallback(table: 'tl_page', target: 'fields.routeParameters.input_field')]
    public function generateRouteParameters(DataContainer $dc): string
    {
        $pageModel = $this->framework->getAdapter(PageModel::class)->findByPk($dc->id);

        if (!$pageModel) {
            return '';
        }

        $parameters = $this->pageRegistry->getUrlParameters($pageModel);

        if (!$parameters) {
            return '';
        }

        return $this->twig->render('@ContaoCore/Backend/be_route_parameters.html.twig', [
            'parameters' => $parameters,
        ]);
    }

    #[AsCallback(table: 'tl_page', target: 'fields.routeConflicts.input_field')]
    public function generateRouteConflicts(DataContainer $dc): string
    {
        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        if (!$currentPage = $pageAdapter->findWithDetails($dc->id)) {
            return '';
        }

        $aliasPages = $pageAdapter->findSimilarByAlias($currentPage);

        if (null === $aliasPages) {
            return '';
        }

        $conflicts = [];
        $currentUrl = $this->splitUrl($currentPage->alias, $currentPage->urlPrefix, $currentPage->urlSuffix);
        $backendAdapter = $this->framework->getAdapter(Backend::class);

        foreach ($aliasPages as $aliasPage) {
            $aliasPage->loadDetails();

            if ($currentPage->domain !== $aliasPage->domain) {
                continue;
            }

            $aliasUrl = $this->splitUrl($aliasPage->alias, $aliasPage->urlPrefix, $aliasPage->urlSuffix);

            if (count($aliasUrl) > count($currentUrl)) {
                $long = $aliasUrl;
                $short = $currentUrl;
            } else {
                $long = $currentUrl;
                $short = $aliasUrl;
            }

            foreach ($long as $k => $v) {
                if (!isset($short[$k]) && !preg_match('/\?[^}]*}/', $v)) {
                    continue 2;
                }

                if (str_contains($v, '{')) {
                    continue;
                }

                if ($v !== ($short[$k] ?? null)) {
                    continue 2;
                }
            }

            $conflicts[] = [
                'page' => $aliasPage,
                'path' => $this->getPathWithParameters($this->pageRegistry->getRoute($aliasPage)),
                'editUrl' => $backendAdapter->addToUrl(sprintf('act=edit&id=%s&popup=1&nb=1', $aliasPage->id)),
            ];
        }

        if (!$conflicts) {
            return '';
        }

        return $this->twig->render('@ContaoCore/Backend/be_route_conflicts.html.twig', [
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Builds the URL from prefix, alias and suffix and returns parts.
     * We cannot use the router for this, since pages might have non-optional parameters.
     * This value is only used to compare two pages and see if they _might_ conflict based on the alias itself.
     */
    private function splitUrl(string $alias, string $urlPrefix, string $urlSuffix): array
    {
        $url = '/'.$alias.$urlSuffix;

        if ($urlPrefix) {
            $url = '/'.$urlPrefix.$url;
        }

        return explode('/', trim($url, '/'));
    }

    private function getPathWithParameters(PageRoute $route): string
    {
        $path = $route->getPath();

        foreach ($route->getRequirements() as $name => $regexp) {
            $path = preg_replace('/{!?('.preg_quote($name, '/').')}/', '{<span class="tl_tip" title="'.StringUtil::specialchars($regexp).'">$1</span>}', $path);
        }

        return $path;
    }
}
