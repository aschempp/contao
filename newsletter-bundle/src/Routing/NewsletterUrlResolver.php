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

use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\NewsletterModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsletterUrlResolver implements ContentUrlResolverInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supportsType(string $contentType): bool
    {
        return NewsletterModel::class === $contentType;
    }

    public function resolve(object $content): PageModel|string|null
    {
        if (!$content instanceof NewsletterModel) {
            throw new \InvalidArgumentException();
        }

        return PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo);
    }

    public function getParametersForContent(object $content): array
    {
        if (!$content instanceof NewsletterModel) {
            throw new \InvalidArgumentException();
        }

        return [
            'parameters' => '/' . ($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'subject' => StringUtil::standardize($content->subject),
            'year' => date('Y', (int) $content->date),
            'month' => date('m', (int) $content->date),
            'day' => date('d', (int) $content->date),
        ];
    }

    public function getAvailableParameters(string $contentType): array
    {
        if (NewsletterModel::class !== $contentType) {
            return [];
        }

        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('subject', $this->describeParameter('subject')),
            new UrlParameter('year', $this->describeParameter('year'), requirement: '\d{4}'),
            new UrlParameter('month', $this->describeParameter('month'), requirement: '\d{2}'),
            new UrlParameter('day', $this->describeParameter('day'), requirement: '\d{2}'),
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_newsletter.'.$key, [], 'route_parameters');
    }
}
