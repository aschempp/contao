<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing;

use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\Model;
use Contao\PageModel;
use Contao\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;

class ContentUrlGenerator implements ResetInterface
{
    /**
     * @var array<string,string>
     */
    private array $urlCache = [];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PageRegistry $pageRegistry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function generate(object $content, array $parameters = []): string
    {
        $cacheKey = spl_object_hash($content).'__'.serialize($parameters);

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        // TODO: this would use the initial content instead of the last resolved one
        // TODO: if a news resolved to an article, and the article to a page, we must
        // TODO: use the article not the news to find parameters!!

        $page = $this->resolveContent($content);

        if (is_string($page)) {
            // Insert tags should be replaced by the resolver that knows insert tags are valid input
            // mailto:: links should only be encoded by the navigation module
            if (Validator::isRelativeUrl($page)) {
                $page = $this->urlGenerator->getContext()->getBaseUrl().'/'.$page;
            }

            return $this->urlCache[$cacheKey] = $page;
        }

        if (!$page instanceof PageModel) {
            $this->throwRouteNotFoundException($content);
        }

        $route = $this->pageRegistry->getRoute($page);
        $route->setContent($content);
        $route->setRouteKey($this->getRouteKey($content));

        $compiledRoute = $route->compile();

        $params = array_merge(
            ...array_map(
                fn (ContentUrlResolverInterface $resolver) => $resolver->getParametersForContent($content),
                $this->pageRegistry->getUrlResolversForContent($content)
            )
        );

        $parameters = [...$parameters, ...array_intersect_key($params, array_flip($compiledRoute->getVariables()))];

        return $this->urlCache[$cacheKey] = $this->urlGenerator->generate(
            PageRoute::PAGE_BASED_ROUTE_NAME,
            [...$parameters, RouteObjectInterface::ROUTE_OBJECT => $route],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function getContext(): RequestContext
    {
        return $this->urlGenerator->getContext();
    }

    public function reset(): void
    {
        $this->urlCache = [];
    }

    private function resolveContent(object $content): PageModel|string
    {
        // Recursively run the loop until a PageModel is returned, and then run it again to resolve the PageModel
        // which can possibly return a string URL. If the same result is returned as passed in, the route for that
        // page should be generated.
        foreach ($this->pageRegistry->getUrlResolversForContent($content) as $resolver) {
            $result = $resolver->resolve($content);

            if (null === $result) {
                continue;
            }

            if (is_string($result)) {
                return $result;
            }

            if ($result instanceof PageModel && $result === $content) {
                return $content;
            }

            return $this->resolveContent($result);
        }

        $this->throwRouteNotFoundException($content);
    }

    private function getRouteKey(object $content): string
    {
        if (is_subclass_of($content, Model::class)) {
            return sprintf('%s.%s', $content::getTable(), $content->id);
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($content::class);
        } catch (MappingException) {
            $metadata = null;
        }

        if (null === $metadata) {
            return get_class($content).'->'.spl_object_hash($content);
        }

        $identifier = $this->entityManager
            ->getUnitOfWork()
            ->getSingleIdentifierValue($content)
        ;

        return sprintf('%s.%s', $metadata->getTableName(), $identifier);
    }

    /**
     * @throws ExceptionInterface
     */
    private function throwRouteNotFoundException(object $content): void
    {
        throw new RouteNotFoundException('No route for content: '.$this->getRouteKey($content));
    }
}
