<?php

namespace Contao\CoreBundle\Controller\Page;

use Contao\CoreBundle\Exception\ForwardPageNotFoundException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\Page\CompositionAwareInterface;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;

class ErrorPageController extends AbstractCompositeController implements CompositionAwareInterface
{
    public function __invoke(Request $request, PageModel $pageModel): Response
    {
        $this->denyAccessUnlessGrantedForPage($pageModel);

        if (!$pageModel->autoforward || !$pageModel->jumpTo) {
            // Reset inherited cache timeouts (see #231)
            if (!$pageModel->includeCache) {
                $pageModel->cache = 0;
                $pageModel->clientCache = 0;
            }

            return $this->renderPageContent($pageModel, false);
        }

        // Forward to another page

        /** @var PageModel $pageAdapter */
        $pageAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);
        $nextPage = $pageAdapter->findPublishedById($pageModel->jumpTo);

        if (null === $nextPage) {
            if (null !== $this->get('logger')) {
                $this->get('logger')->error(
                    'Forward page ID "'.$pageModel->jumpTo.'" does not exist',
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
            }

            throw new ForwardPageNotFoundException('Forward page not found');
        }

        // Add the referer so the login module can redirect back
        $url = $this->generateContentUrl($nextPage, ['redirect' => $request->getUri()]);

        return $this->redirect($this->get('uri_signer')->sign($url), 303);
    }

    public function supportsContentComposition(PageModel $pageModel): bool
    {
        return !$pageModel->autoforward || !$pageModel->jumpTo;
    }

    public static function getSubscribedServices()
    {
        $services = parent::getSubscribedServices();

        $services['uri_signer'] = UriSigner::class;

        return $services;
    }
}
