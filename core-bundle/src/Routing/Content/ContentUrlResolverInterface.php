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

interface ContentUrlResolverInterface
{
    /**
     * This method should return the target page to render this content on.
     * It can also return a string for an absolute URL, a new content to redirect to (e.g. from news to article)
     * or NULL if it cannot handle the content (to continue resolving to the next resolver).
     */
    public function resolve(object $content): ContentUrlResult;
}
