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
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEventsUrlResolver implements ContentUrlResolverInterface
{
    public function __construct(
        private readonly InsertTagParser $insertTagParser,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supportsType(string $contentType): bool
    {
        return CalendarEventsModel::class === $contentType;
    }

    public function resolve(object $content): PageModel|string|null
    {
        if (!$content instanceof CalendarEventsModel) {
            throw new \InvalidArgumentException();
        }

        switch ($content->source) {
            // Link to an external page
            case 'external':
                return $this->insertTagParser->replaceInline($content->url);

            // Link to an internal page
            case 'internal':
                $page = $content->getRelated('jumpTo');

                if ($page instanceof PageModel) {
                    return $page;
                }
                break;

            // Link to an article
            case 'article':
                $article = ArticleModel::findByPk($content->articleId);

                if ($article instanceof ArticleModel) {
                    return $article;
                }
                break;
        }

        // Link to the default page
        return PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo);
    }

    public function getParametersForContent(object $content): array
    {
        if (!$content instanceof CalendarEventsModel) {
            throw new \InvalidArgumentException();
        }

        return [
            'parameters' => '/' . ($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'title' => StringUtil::standardize($content->title),
            'year' => date('Y', (int) $content->startTime),
            'month' => date('m', (int) $content->startTime),
            'day' => date('d', (int) $content->startTime),
        ];
    }

    public function getAvailableParameters(string $contentType): array
    {
        if (CalendarEventsModel::class !== $contentType) {
            return [];
        }

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
