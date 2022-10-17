<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller\Page;

use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * @internal
 */
#[AsPage('error_410', contentComposition: false)]
class GonePageController
{
    public function __invoke(PageModel $pageModel): Response
    {
        throw new GoneHttpException();
    }
}
