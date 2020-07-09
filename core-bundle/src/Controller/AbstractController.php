<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Exception\InsufficientAuthenticationException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\StringUtil;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\ServiceAnnotationBundle\ServiceAnnotationInterface;

abstract class AbstractController extends SymfonyAbstractController implements ServiceAnnotationInterface
{
    public static function getSubscribedServices()
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['logger'] = '?'.LoggerInterface::class;
        $services['fos_http_cache.http.symfony_response_tagger'] = '?'.SymfonyResponseTagger::class;

        return $services;
    }

    protected function initializeContaoFramework(): void
    {
        $this->get('contao.framework')->initialize();
    }

    protected function tagResponse(array $tags): void
    {
        if (!$this->has('fos_http_cache.http.symfony_response_tagger')) {
            return;
        }

        /* @phpstan-ignore-next-line  */
        $this->get('fos_http_cache.http.symfony_response_tagger')->addTags($tags);
    }

    /**
     * Uses the Symfony router to generate a URL for the given content.
     *
     * "Content" in this case should be any supported model/entity of Contao,
     * e.g. a PageModel, NewsModel or similar.
     */
    protected function generateContentUrl($content, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $parameters[PageRoute::CONTENT_PARAMETER] = $content;

        return $this->generateUrl(PageRoute::ROUTE_NAME, $parameters, $referenceType);
    }

    /**
     * Uses the Symfony router to generate redirect response to the URL of the given content.
     *
     * "Content" in this case should be any supported model/entity of Contao,
     * e.g. a PageModel, NewsModel or similar.
     */
    protected function redirectToContent($content, array $parameters = [], int $status = 302): RedirectResponse
    {
        return $this->redirect($this->generateContentUrl($content, $parameters, UrlGeneratorInterface::ABSOLUTE_URL), $status);
    }

    /**
     * Throws an exception the security user has access to the current page.
     *
     * @throws AccessDeniedException
     */
    protected function denyAccessUnlessGrantedForPage(PageModel $pageModel)
    {
        if (!$pageModel->protected) {
            return;
        }

        $token = $this->get('security.token_storage')->getToken();

        if ($token instanceof AnonymousToken) {
            throw new InsufficientAuthenticationException('Not authenticated');
        }

        $user = $token->getUser();

        if (!$user instanceof FrontendUser || !\in_array('ROLE_MEMBER', $token->getRoleNames(), true)) {
            throw new AccessDeniedException();
        }

        $groups = StringUtil::deserialize($pageModel->groups);
        $userGroups = StringUtil::deserialize($user->groups);

        if (
            empty($groups)
            || !\is_array($groups)
            || !\is_array($userGroups)
            || 0 === \count(array_intersect($groups, $userGroups))
        ) {
            if (null !== ($logger = $this->get('logger'))) {
                $logger->error(
                    sprintf(
                        'Page ID "%s" can only be accessed by groups "%s" (current user groups: %s)',
                        $pageModel->id,
                        implode(', ', (array)$pageModel->groups),
                        implode(', ', $token->getUser()->groups)
                    ),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                );
            }

            throw new AccessDeniedException();
        }
    }
}
