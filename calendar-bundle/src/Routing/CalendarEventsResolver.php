<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CalendarBundle\Routing;

use Contao\ArticleModel;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\CoreBundle\Routing\Content\StringUrl;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEventsResolver implements ContentUrlResolverInterface, ContentParameterResolverInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof CalendarEventsModel) {
            return ContentUrlResult::abstain();
        }

        switch ($content->source) {
            // Link to an external page
            case 'external':
                return ContentUrlResult::redirect(new StringUrl($content->url));

            // Link to an internal page
            case 'internal':
                $page = $content->getRelated('jumpTo');

                if ($page instanceof PageModel) {
                    return ContentUrlResult::redirect($page);
                }
                break;

            // Link to an article
            case 'article':
                $article = ArticleModel::findByPk($content->articleId);

                if ($article instanceof ArticleModel) {
                    return ContentUrlResult::redirect($article);
                }
                break;
        }

        // Link to the default page
        return ContentUrlResult::resolve(PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo));
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof CalendarEventsModel) {
            return [];
        }

        return [
            'parameters' => '/'.($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'title' => StringUtil::standardize($content->title),
            'year' => date('Y', (int) $content->startTime),
            'month' => date('m', (int) $content->startTime),
            'day' => date('d', (int) $content->startTime),
        ];
    }

    public function getSupportedContent(): string
    {
        return CalendarEventsModel::CONTENT_TYPE;
    }

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        if (!\in_array($urlParameter->getName(), ['id', 'alias'])) {
            return null;
        }

        return CalendarEventsModel::findPublishedByIdOrAlias($identifier);
    }

    public function getAvailableParameters(PageModel $pageModel): array
    {
        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('title', $this->describeParameter('title')),
            new UrlParameter('year', $this->describeParameter('year'), requirement: '\d{4}'),
            new UrlParameter('month', $this->describeParameter('month'), requirement: '\d{2}'),
            new UrlParameter('day', $this->describeParameter('day'), requirement: '\d{2}'),
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_calendar_events.'.$key, [], 'route_parameters');
    }
}
