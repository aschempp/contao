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

use Contao\CoreBundle\Controller\AbstractController;
use Contao\InsertTags;
use Contao\PageModel;

class RedirectPageController extends AbstractController
{
    public function __invoke(PageModel $pageModel)
    {
        $this->initializeContaoFramework();
        $this->denyAccessUnlessGrantedForPage($pageModel);

        /** @var InsertTags $insertTags */
        $insertTags = $this->get('contao.framework')->createInstance(InsertTags::class);

        return $this->redirect(
            $insertTags->replace($pageModel->url, false),
            'temporary' === $pageModel->redirect ? 302 : 301
        );
    }
}
