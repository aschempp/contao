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
use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\Routing\Page\UrlResolverInterface;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @internal
 */
#[AsPage(contentComposition: false)]
class LogoutPageController extends AbstractController implements UrlResolverInterface
{
    public function __construct(
        private readonly LogoutUrlGenerator $logoutUrlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(Request $request, PageModel $pageModel): Response
    {
        $uri = $this->getLogoutUrl($request, $pageModel);

        return new RedirectResponse($uri, Response::HTTP_TEMPORARY_REDIRECT);
    }

    public function resolvePageUrl(PageModel $pageModel): PageModel|string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return $pageModel;
        }

        return $this->getLogoutUrl($request, $pageModel);
    }

    private function getLogoutUrl(Request $request, PageModel $pageModel): string
    {
        $this->initializeContaoFramework();

        $strRedirect = $request->getBaseUrl();

        if ($pageModel->redirectBack && ($strReferer = System::getReferer())) {
            $strRedirect = $strReferer;
        } elseif (($objTarget = $pageModel->getRelated('jumpTo')) instanceof PageModel) {
            try {
                $strRedirect = $this->generateContentUrl($objTarget);
            } catch (ExceptionInterface) {
                // keep the base URL if we cannot generate a jumpTo URL
            }
        }

        // Redirect immediately if there is no logged-in user (see #2388)
        if (!$this->isGranted('IS_AUTHENTICATED')) {
            return $strRedirect;
        }

        $pairs = [];
        $strLogoutUrl = $this->logoutUrlGenerator->getLogoutUrl();
        $request = Request::create($strLogoutUrl);

        if ($request->server->has('QUERY_STRING')) {
            parse_str($request->server->get('QUERY_STRING'), $pairs);
        }

        // Add the redirect= parameter to the logout URL
        $pairs['redirect'] = $strRedirect;

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().'?'.http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
    }
}
