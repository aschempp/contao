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
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\PageModel;

class PageResolver implements ContentUrlResolverInterface
{
    public function __construct(private readonly PageRegistry $pageRegistry)
    {
    }

    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof PageModel) {
            return ContentUrlResult::abstain();
        }

        if ($resolver = $this->pageRegistry->getPageUrlResolver($content)) {
            return ContentUrlResult::create($resolver->resolvePageUrl($content));
        }

        // Handle legacy page types until they have a page controller
        switch ($content->type) {
            case 'redirect':
                return ContentUrlResult::create(new StringUrl($content->url));

            case 'forward':
                if ($content->jumpTo) {
                    $forwardPage = PageModel::findPublishedById($content->jumpTo);
                } else {
                    $forwardPage = PageModel::findFirstPublishedRegularByPid($content->id);
                }

                if (!$forwardPage) {
                    throw new ForwardPageNotFoundException();
                }

                return ContentUrlResult::create($forwardPage);
        }

        return ContentUrlResult::create($content);
    }
}
