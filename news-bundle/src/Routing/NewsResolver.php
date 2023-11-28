<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\NewsBundle\Routing;

use Contao\ArticleModel;
use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\CoreBundle\Routing\Content\StringUrl;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsResolver implements ContentUrlResolverInterface, ContentParameterResolverInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof NewsModel) {
            return ContentUrlResult::abstain();
        }

        switch ($content->source) {
            // Link to an external page
            case 'external':
                return ContentUrlResult::create(new StringUrl($content->url));

            // Link to an internal page
            case 'internal':
                $page = $content->getRelated('jumpTo');

                if ($page instanceof PageModel) {
                    return ContentUrlResult::create($page);
                }
                break;

            // Link to an article
            case 'article':
                $article = ArticleModel::findByPk($content->articleId);

                if ($article instanceof ArticleModel) {
                    return ContentUrlResult::create($article);
                }
                break;
        }

        // Link to the default page
        return ContentUrlResult::create(PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo));
    }

    public function getContentType(): string
    {
        return NewsModel::getTable();
    }

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        return NewsModel::findPublishedByIdOrAlias($identifier);
    }

    public function getAvailableParameters(PageModel $pageModel): array
    {
        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('headline', $this->describeParameter('headline')),
            new UrlParameter('year', $this->describeParameter('year'), '\d{4}', date('Y')),
            new UrlParameter('month', $this->describeParameter('month'), '\d{2}', date('m')),
            new UrlParameter('day', $this->describeParameter('day'), '\d{2}', date('d')),
        ];
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof NewsModel) {
            return [];
        }

        return [
            'parameters' => '/'.($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'headline' => StringUtil::standardize($content->headline), // TODO: maybe use slugger?
            'year' => date('Y', (int) $content->time),
            'month' => date('m', (int) $content->time),
            'day' => date('d', (int) $content->time),
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_news.'.$key, [], 'route_parameters');
    }
}
