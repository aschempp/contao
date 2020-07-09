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
use Contao\CoreBundle\Exception\ForwardPageNotFoundException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\Page\CompositionAwareInterface;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;

class ForwardController extends AbstractController implements CompositionAwareInterface
{
    public function __invoke(Request $request, PageModel $pageModel)
    {
        $this->denyAccessUnlessGrantedForPage($pageModel);
        $this->initializeContaoFramework();

        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);

        if ($pageModel->jumpTo) {
            $forwardPage = $pageAdapter->findPublishedById($pageModel->jumpTo);
        } else {
            $forwardPage = $pageAdapter->findFirstPublishedRegularByPid($pageModel->id);
        }

        // Forward page does not exist
        if (!$forwardPage instanceof PageModel) {
            if (null !== ($logger = $this->get('logger'))) {
                $logger->error(
                    'Forward page ID "' . $pageModel->jumpTo . '" does not exist',
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
            }

            throw new ForwardPageNotFoundException('Forward page not found');
        }

        $params = $request->query->all();
        $params['parameters'] = $request->attributes->get('parameters');

        $this->redirectToContent($forwardPage, $params, 303);
    }

    public function supportsContentComposition(PageModel $pageModel): bool
    {
        return false;
    }
}
