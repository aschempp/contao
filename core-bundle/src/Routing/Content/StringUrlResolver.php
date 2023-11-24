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

use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Util\UrlUtil;
use Contao\PageModel;
use Symfony\Component\Routing\RequestContext;

class StringUrlResolver implements ContentUrlResolverInterface
{
    public function __construct(
        private readonly InsertTagParser $insertTagParser,
        private readonly RequestContext $requestContext,
    ) {
    }

    public function supportsType(string $contentType): bool
    {
        return StringUrl::class === $contentType;
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof StringUrl) {
            throw new \InvalidArgumentException();
        }

        $url = $this->insertTagParser->replaceInline($content->value);
        $url = UrlUtil::makeAbsolute($url, $this->requestContext->getBaseUrl());

        return ContentUrlResult::absoluteUrl($url);
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        return [];
    }

    public function getAvailableParameters(string $contentType, PageModel $pageModel): array
    {
        return [];
    }
}
