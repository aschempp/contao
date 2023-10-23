<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Page;

use Contao\PageModel;

/**
 * The ContentTypesInterface allows a page to dynamically determine what
 * content types the given PageModel supports. If the value is always the
 * same, use the service tag or "contentTypes=[FQCN]" attribute instead.
 */
interface ContentTypesInterface
{
    /**
     * Returns the fully-qualified class names of object the given page type
     * supports. This value is then used to check available parameters from
     * all content URL resolvers.
     *
     * If the page model does not support any specific parameters, it will
     * fall back to the legacy "parameters" key like Contao always did.
     *
     * @return array<string>
     */
    public function getSupportedContentTypes(PageModel $pageModel): array;
}
