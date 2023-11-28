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
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArticleResolver implements ContentUrlResolverInterface, ContentParameterResolverInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof ArticleModel) {
            return ContentUrlResult::abstain();
        }

        return ContentUrlResult::create(PageModel::findWithDetails($content->pid));
    }

    public function getContentType(): string
    {
        return ArticleModel::getTable();
    }

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        return ArticleModel::findPublishedByIdOrAliasAndPid($identifier, $pageModel->id);
    }

    public function getAvailableParameters(PageModel $pageModel): array
    {
        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('title', $this->describeParameter('title')),
        ];
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof ArticleModel) {
            return [];
        }

        return [
            'parameters' => '/articles/'.($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'title' => StringUtil::standardize($content->title), // TODO: maybe use slugger?
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_article.'.$key, [], 'route_parameters');
    }
}
