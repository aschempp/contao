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

use Contao\PageModel;

interface ContentParameterResolverInterface
{
    /**
     * Returns the content types this resolver has parameters for.
     */
    public function getContentType(): string;

    public function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null;

    /**
     * Should return a list of parameters names (array keys) that will be returned if an
     * instance of the given content type is passed to `getParametersForContent`.
     *
     * The list should not include the fallback "parameters" value even if returned by this resolver
     * since that is not a useful parameter to manually include in the page alias.
     *
     * @return array<UrlParameter>
     */
    public function getAvailableParameters(PageModel $pageModel): array;

    /**
     * Returns an array of parameters for the given content that can be used
     * to generate a URL for this content. If the parameter is used in the page alias,
     * it will be used to generate the URL. Otherwise, it is ignored (contrary to the Symfony
     * URL generator which would add it as a query parameter).
     *
     * @return array<string,string|int>
     */
    public function getParametersForContent(object $content, PageModel $pageModel): array;
}
