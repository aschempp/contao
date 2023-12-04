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

use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\Model;
use Contao\PageModel;
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
        /**
         * @var iterable<ContentUrlResolverInterface> $urlResolvers
         */
        private readonly iterable $urlResolvers,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function generate(object $content, array $parameters = [], array $optionalParameters = []): string
    {
        $cacheKey = spl_object_hash($content).'__'.serialize($parameters).'__'.serialize($optionalParameters);

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        [$target, $targetContent] = $this->resolveContent($content) + [null, null];

        if (\is_string($target)) {
            return $this->urlCache[$cacheKey] = $target;
        }

        if (!$target instanceof PageModel) {
            $this->throwRouteNotFoundException($target);
        }

        $route = $this->pageRegistry->getRoute($target);

        if ($targetContent) {
            $route->setContent($targetContent);
            $route->setRouteKey($this->getRouteKey($targetContent));
        }

        // The original content has changed, parameters are not valid anymore
        if ($targetContent !== $content && $target !== $content) {
            $parameters = $optionalParameters = [];
        }

        $compiledRoute = $route->compile();

        if ($targetContent) {
            $optionalParameters = array_replace(
                array_replace(
                    ...array_map(
                        static fn (ContentUrlResolverInterface $resolver) => $resolver->getParametersForContent($targetContent, $target),
                        [...$this->urlResolvers]
                    ),
                ),
                $optionalParameters,
            );

            $optionalParameters = array_intersect_key($optionalParameters, array_flip($compiledRoute->getVariables()));
        }

        return $this->urlCache[$cacheKey] = $this->urlGenerator->generate(
            PageRoute::PAGE_BASED_ROUTE_NAME,
            [...$optionalParameters, ...$parameters, RouteObjectInterface::ROUTE_OBJECT => $route],
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

    /**
     * @throws ExceptionInterface
     */
    private function resolveContent(object ...$contents): array
    {
        foreach ($this->urlResolvers as $resolver) {
            $result = $resolver->resolve($contents[0]);

            if ($result->isAbstained()) {
                continue;
            }

            if ($result->hasTargetUrl()) {
                return [$result->getTargetUrl()];
            }

            if ($result->isRedirect()) {
                return $this->resolveContent($result->content);
            }

            return $this->resolveContent($result->content, ...$contents);
        }

        if ($contents[0] instanceof PageModel) {
            return $contents;
        }

        $this->throwRouteNotFoundException($contents[0]);
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
            return \get_class($content).'->'.spl_object_hash($content);
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
