<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\NewsletterBundle\Routing;

use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\NewsletterModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsletterResolver implements ContentUrlResolverInterface, ContentParameterResolverInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof NewsletterModel) {
            return ContentUrlResult::abstain();
        }

        return ContentUrlResult::create(PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo));
    }

    public function getContentType(): string
    {
        return NewsletterModel::getTable();
    }

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        return NewsletterModel::findSentByIdOrAlias($identifier);
    }

    public function getAvailableParameters(PageModel $pageModel): array
    {
        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('subject', $this->describeParameter('subject')),
            new UrlParameter('year', $this->describeParameter('year'), '\d{4}', null),
            new UrlParameter('month', $this->describeParameter('month'), '\d{2}', null),
            new UrlParameter('day', $this->describeParameter('day'), '\d{2}', null),
        ];
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof NewsletterModel) {
            return [];
        }

        return [
            'parameters' => '/'.($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'subject' => StringUtil::standardize($content->subject),
            'year' => date('Y', (int) $content->date),
            'month' => date('m', (int) $content->date),
            'day' => date('d', (int) $content->date),
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_newsletter.'.$key, [], 'route_parameters');
    }
}
