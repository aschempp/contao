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

use Contao\ArticleModel;
use Contao\PageModel;

class ArticleUrlResolver implements ContentUrlResolverInterface
{
    public function supportsType(string $contentType): bool
    {
        return ArticleModel::class === $contentType;
    }

    /**
     * @param ArticleModel $content
     */
    public function resolve(object $content): PageModel
    {
        if (!$content instanceof ArticleModel) {
            throw new \InvalidArgumentException();
        }

        return PageModel::findWithDetails($content->pid);
    }

    public function getParametersForContent(object $content): array
    {
        if (!$content instanceof ArticleModel) {
            throw new \InvalidArgumentException();
        }

        return ['parameters' => '/articles/' . ($content->alias ?: $content->id)];
    }

    public function getAvailableParameters(string $contentType): array
    {
        return [];
    }
}
