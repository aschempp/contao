<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Enhancer;

use Contao\CoreBundle\Exception\ContentNotFoundException;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\PageModel;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Cmf\Component\Routing\Enhancer\RouteEnhancerInterface;
use Symfony\Component\HttpFoundation\Request;

class ContentEnhancer implements RouteEnhancerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PageRegistry $pageRegistry,
        private readonly ContentUrlGenerator $urlGenerator,
        /** @var iterable<ContentUrlResolverInterface> $urlResolvers */
        private readonly iterable $urlResolvers
    ) {
    }

    public function enhance(array $defaults, Request $request): array
    {
        $pageModel = $defaults['pageModel'] ?? null;

        if (!$pageModel instanceof PageModel || isset($defaults['_content'])) {
            return $defaults;
        }

        $content = null;
        $identifier = $this->getContentIdentifyingParameter($defaults, $pageModel);

        if (!$identifier) {
            return $defaults;
        }

        foreach ($this->pageRegistry->getParameterResolvers($pageModel) as $resolver) {
            if ($content = $resolver->loadContent($defaults[$identifier->getName()], $identifier, $pageModel)) {
                break;
            }
        }

        // At this point we know the page has an identifier, so we must be able to load the content
        if (!$content) {
            throw new ContentNotFoundException('Content not found: '.$request->getRequestUri());
        }

        if (!$this->validateContentParameters($defaults, $content, $pageModel)) {
            return [
                '_controller' => RedirectController::class.'::urlRedirectAction',
                'path' => $this->urlGenerator->generate($content),
                'permanent' => true
            ];
        }

        $defaults['_content'] = $content;

        return $defaults;
    }

    private function getContentIdentifyingParameter(array $defaults, PageModel $pageModel): UrlParameter|null
    {
        foreach ($this->pageRegistry->getUrlParameters($pageModel) as $parameter) {
            if ($parameter->isIdentifier() && array_key_exists($parameter->getName(), $defaults)) {
                return $parameter;
            }
        }

        return null;
    }

    protected function validateContentParameters(array $defaults, object $content, PageModel $pageModel): bool
    {
        $params = array_merge(
            ...array_map(
                static fn (ContentUrlResolverInterface $resolver) => $resolver->getParametersForContent($content, $pageModel),
                [...$this->urlResolvers]
            )
        );

        foreach ($params as $name => $value) {
            if (array_key_exists($name, $defaults) && $defaults[$name] !== $value) {
                return false;
            }
        }

        return true;
    }
}
