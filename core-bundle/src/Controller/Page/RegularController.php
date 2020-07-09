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

use Contao\PageModel;

class RegularController extends AbstractCompositeController
{
    public function __invoke(PageModel $pageModel)
    {
        return $this->renderPageContent($pageModel);
    }
}
