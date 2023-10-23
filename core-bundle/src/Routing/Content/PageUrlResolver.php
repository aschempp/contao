<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Content;

use Contao\CoreBundle\Exception\ForwardPageNotFoundException;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\PageModel;

class PageUrlResolver implements ContentUrlResolverInterface
{
    public function __construct(
        private readonly PageRegistry $pageRegistry,
        private readonly InsertTagParser $insertTagParser,
    ) {
    }

    public function supportsType(string $contentType): bool
    {
        return PageModel::class === $contentType;
    }

    public function resolve(object $content): PageModel|string
    {
        if (!$content instanceof PageModel) {
            throw new \InvalidArgumentException();
        }

        if ($resolver = $this->pageRegistry->getPageUrlResolver($content)) {
            return $resolver->resolvePageUrl($content);
        }

        // Handle legacy page types until they have a page controller
        switch ($content->type) {
            case 'redirect':
                return $this->insertTagParser->replaceInline($content->url);

            case 'forward':
                if ($content->jumpTo) {
                    $forwardPage = PageModel::findPublishedById($content->jumpTo);
                } else {
                    $forwardPage = PageModel::findFirstPublishedRegularByPid($content->id);
                }

                if (!$forwardPage) {
                    throw new ForwardPageNotFoundException();
                }

                return $forwardPage;
        }

        return $content;
    }

    public function getParametersForContent(object $content): array
    {
        return [];
    }

    public function getAvailableParameters(string $contentType): array
    {
        return [];
    }
}
