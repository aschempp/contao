<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Page;

use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

class PageRegistry implements ResetInterface
{
    private const DISABLE_CONTENT_COMPOSITION = ['redirect', 'forward'];

    private array|null $urlPrefixes = null;

    private array|null $urlSuffixes = null;

    /**
     * @var array<string,RouteConfig>
     */
    private array $routeConfigs = [];

    /**
     * @var array<string,DynamicRouteInterface>
     */
    private array $routeEnhancers = [];

    /**
     * @var array<string,ContentCompositionInterface|bool>
     */
    private array $contentComposition = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly iterable $parameterResolvers,
    ) {
    }

    /**
     * Returns the route for a page.
     *
     * If no path is configured (is null), the route will accept
     * any parameters after the page alias (e.g. "en/page-alias/foo/bar.html").
     *
     * A route enhancer might enhance the route for a specific page.
     */
    public function getRoute(PageModel $pageModel): PageRoute
    {
        $type = $pageModel->type;
        $config = $this->routeConfigs[$type] ?? new RouteConfig();
        $defaults = $config->getDefaults();
        $requirements = $config->getRequirements();
        $options = $config->getOptions();
        $path = $config->getPath();

        if (false === $path) {
            $path = '';
            $options['compiler_class'] = UnroutablePageRouteCompiler::class;
        } elseif (null === $path) {
            $path = '/'.($pageModel->alias ?: $pageModel->id);

            if (str_contains($path, '{')) {
                foreach ($this->getUrlParameters($pageModel) as $parameter) {
                    // Only set parameters that exist in the path
                    if (!preg_match('/\{!?'.$parameter->getName().'(<[^>]+>)?(\?)?/', $path, $matches)) {
                        continue;
                    }

                    if (!($matches[1] ?? null) && $parameter->getRequirement()) {
                        $requirements[$parameter->getName()] = $parameter->getRequirement();
                    }

                    if (false !== $parameter->getDefault() && '?' !== ($matches[2] ?? null)) {
                        $defaults[$parameter->getName()] = $parameter->getDefault();
                    }
                }
            } elseif (!$this->isParameterless($pageModel)) {
                $path = '/'.($pageModel->alias ?: $pageModel->id).'{!parameters}';
                $defaults['parameters'] = '';
                $requirements['parameters'] = $pageModel->requireItem ? '/.+?' : '(/.+?)?';
            }
        }

        $route = new PageRoute($pageModel, $path, $defaults, $requirements, $options, $config->getMethods());

        if (null !== $config->getUrlSuffix()) {
            $route->setUrlSuffix($config->getUrlSuffix());
        }

        if (!isset($this->routeEnhancers[$type]) || !$this->routeEnhancers[$type] instanceof DynamicRouteInterface) {
            return $route;
        }

        $enhancer = $this->routeEnhancers[$type];
        $enhancer->configurePageRoute($route);

        return $route;
    }

    public function getPathRegex(): array
    {
        $prefixes = [];

        foreach ($this->routeConfigs as $type => $config) {
            $regex = $config->getPathRegex();

            if (null !== $regex) {
                $prefixes[$type] = $regex;
            }
        }

        return $prefixes;
    }

    public function supportsContentComposition(PageModel $pageModel): bool
    {
        if (!isset($this->contentComposition[$pageModel->type])) {
            return !\in_array($pageModel->type, self::DISABLE_CONTENT_COMPOSITION, true);
        }

        $service = $this->contentComposition[$pageModel->type];

        if ($service instanceof ContentCompositionInterface) {
            return $service->supportsContentComposition($pageModel);
        }

        return (bool) $service;
    }

    public function getContentTypes(): array
    {
        return array_unique(
            array_merge(
                array_map(
                    static fn (ContentParameterResolverInterface $resolver) => $resolver->getSupportedContent(),
                    [...$this->parameterResolvers]
                )
            )
        );
    }

    /**
     * @return array<string,UrlParameter>
     */
    public function getUrlParameters(PageModel $pageModel): array
    {
        $parameters = [];

        foreach ($this->getResolvers($pageModel) as $resolver) {
            foreach ($resolver->getAvailableParameters($pageModel) as $parameter) {
                // Resolvers are sorted by priority, this makes sure the first parameter wins
                if (isset($parameters[$parameter->getName()])) {
                    continue;
                }

                $parameters[$parameter->getName()] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * @return array<ContentParameterResolverInterface>
     */
    public function getParameterResolvers(PageModel|null $pageModel = null): array
    {
        if (null === $pageModel) {
            return [...$this->parameterResolvers];
        }

        return $this->getResolvers($pageModel);
    }

    /**
     * @return array<string>
     */
    public function getUrlPrefixes(): array
    {
        $this->initializePrefixAndSuffix();

        return $this->urlPrefixes;
    }

    /**
     * @return array<string>
     */
    public function getUrlSuffixes(): array
    {
        $this->initializePrefixAndSuffix();

        return $this->urlSuffixes;
    }

    public function add(string $type, RouteConfig $config, DynamicRouteInterface|null $routeEnhancer = null, ContentCompositionInterface|bool $contentComposition = true): self
    {
        // Override existing pages with the same identifier
        $this->routeConfigs[$type] = $config;

        if ($routeEnhancer) {
            $this->routeEnhancers[$type] = $routeEnhancer;
        }

        $this->contentComposition[$type] = $contentComposition;

        // Make sure to reset caches when a page type is added
        $this->urlPrefixes = null;
        $this->urlSuffixes = null;

        return $this;
    }

    public function remove(string $type): self
    {
        unset(
            $this->routeConfigs[$type],
            $this->routeEnhancers[$type],
            $this->contentComposition[$type]
        );

        $this->urlPrefixes = $this->urlSuffixes = null;

        return $this;
    }

    public function keys(): array
    {
        return array_keys($this->routeConfigs);
    }

    /**
     * Checks whether this is a routable page type (see #3415).
     */
    public function isRoutable(PageModel $page): bool
    {
        $type = $page->type;

        // Any legacy page without route config is routable by default
        if (!isset($this->routeConfigs[$type])) {
            return true;
        }

        // Check if page controller is routable
        return false !== $this->routeConfigs[$type]->getPath();
    }

    /**
     * @return array<string>
     */
    public function getUnroutableTypes(): array
    {
        $types = [];

        foreach ($this->routeConfigs as $type => $config) {
            if (false === $config->getPath()) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function reset(): void
    {
        $this->urlPrefixes = null;
        $this->urlSuffixes = null;
    }

    private function initializePrefixAndSuffix(): void
    {
        if (null !== $this->urlPrefixes || null !== $this->urlSuffixes) {
            return;
        }

        $results = $this->connection->fetchAllAssociative("SELECT urlPrefix, urlSuffix FROM tl_page WHERE type='root'");

        $urlSuffixes = [
            array_column($results, 'urlSuffix'),
            array_filter(array_map(
                static fn (RouteConfig $config) => $config->getUrlSuffix(),
                $this->routeConfigs,
            )),
        ];

        foreach ($this->routeConfigs as $config) {
            if (null !== ($suffix = $config->getUrlSuffix())) {
                $urlSuffixes[] = [$suffix];
            }
        }

        foreach ($this->routeEnhancers as $enhancer) {
            if ($enhancer instanceof DynamicRouteInterface) {
                $urlSuffixes[] = $enhancer->getUrlSuffixes();
            }
        }

        $this->urlSuffixes = array_values(array_unique(array_merge(...$urlSuffixes)));
        $this->urlPrefixes = array_values(array_unique(array_column($results, 'urlPrefix')));
    }

    private function isParameterless(PageModel $pageModel): bool
    {
        if ('redirect' === $pageModel->type) {
            return true;
        }

        return 'forward' === $pageModel->type && !$pageModel->alwaysForward;
    }

    private function getResolvers(PageModel $pageModel): array
    {
        $contentType = $pageModel->requireItem;

        if (!$contentType || '1' === $contentType) {
            return [];
        }

        return array_filter(
            [...$this->parameterResolvers],
            static fn (ContentParameterResolverInterface $resolver) => $resolver->getSupportedContent() === $contentType
        );
    }
}
