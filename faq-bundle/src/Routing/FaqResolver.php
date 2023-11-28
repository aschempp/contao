<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\FaqBundle\Routing;

use Contao\CoreBundle\Routing\Content\ContentParameterResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\ContentUrlResult;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\FaqModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

class FaqResolver implements ContentUrlResolverInterface, ContentParameterResolverInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof FaqModel) {
            return ContentUrlResult::abstain();
        }

        return ContentUrlResult::create(PageModel::findWithDetails((int) $content->getRelated('pid')?->jumpTo));
    }

    public function getContentType(): string
    {
        return FaqModel::getTable();
    }

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        return FaqModel::findPublishedByIdOrAlias($identifier);
    }

    public function getAvailableParameters(PageModel $pageModel): array
    {
        return [
            new UrlParameter('alias', $this->describeParameter('alias'), identifier: true),
            new UrlParameter('id', $this->describeParameter('id'), requirement: '\d+', identifier: true),
            new UrlParameter('question', $this->describeParameter('question')),
        ];
    }

    public function getParametersForContent(object $content, PageModel $pageModel): array
    {
        if (!$content instanceof FaqModel) {
            return [];
        }

        return [
            'parameters' => '/'.($content->alias ?: $content->id),
            'alias' => ($content->alias ?: $content->id),
            'id' => (int) $content->id,
            'question' => StringUtil::standardize($content->question),
        ];
    }

    private function describeParameter(string $key): string
    {
        return $this->translator->trans('tl_faq.'.$key, [], 'route_parameters');
    }
}
