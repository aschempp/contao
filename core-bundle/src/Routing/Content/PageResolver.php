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
use Contao\PageModel;

class PageResolver implements ContentUrlResolverInterface
{
    public function resolve(object $content): ContentUrlResult
    {
        if (!$content instanceof PageModel) {
            return ContentUrlResult::abstain();
        }

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
