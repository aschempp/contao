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
    public function getSupportedContent(): string;

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
}
