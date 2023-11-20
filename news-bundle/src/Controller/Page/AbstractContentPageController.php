<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\NewsBundle\Controller\Page;

use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\Content\ContentUrlResolverInterface;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;
use Contao\UnusedArgumentsException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractContentPageController extends AbstractController
{
    public function __invoke(Request $request, Pagemodel $pageModel): Response
    {
        if (!($parameter = $this->getContentIdenfyingParameter($request, $pageModel))) {
            throw new PageNotFoundException('Page not found: '.$request->getRequestUri());
        }

        $content = $this->loadContent($request->attributes->get($parameter->getName()), $parameter, $pageModel);

        if (!$content) {
            throw new PageNotFoundException('Page not found: '.$request->getRequestUri());
        }

        if (!$this->validateContentParameters($request, $content, $pageModel)) {
            if ($pageModel->redirectParameters) {
                return new RedirectResponse(
                    $this->generateContentUrl($content, [], UrlGeneratorInterface::ABSOLUTE_URL),
                    Response::HTTP_MOVED_PERMANENTLY,
                );
            }

            throw new PageNotFoundException('Page not found: '.$request->getRequestUri());
        }

        $request->attributes->set('_content', $content);

        return $this->getResponse($request, $pageModel, $content);
    }

    abstract protected function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null;

    abstract protected function getResponse(Request $request, PageModel $pageModel, object $content): Response;

    protected function getContentIdenfyingParameter(Request $request, PageModel $pageModel): UrlParameter|null
    {
        $pageRegistry = $this->container->get('contao.routing.page_registry');

        foreach ($pageRegistry->getUrlParameters($pageModel) as $parameters) {
            foreach ($parameters as $parameter) {
                if ($parameter->isIdentifier() && $request->attributes->has($parameter->getName())) {
                    return $parameter;
                }
            }
        }

        return null;
    }

    protected function validateContentParameters(Request $request, object $content, PageModel $pageModel): bool
    {
        $pageRegistry = $this->container->get('contao.routing.page_registry');

        $params = array_merge(
            ...array_map(
                static fn (ContentUrlResolverInterface $resolver) => $resolver->getParametersForContent($content, $pageModel),
                $pageRegistry->getUrlResolversForContent($content)
            )
        );

        foreach ($params as $name => $value) {
            if ($request->attributes->has($name) && $request->attributes->get($name) !== $value) {
                return false;
            }
        }

        return true;
    }

    protected function renderPage(PageModel $pageModel, ResponseContext $responseContext = null): Response
    {
        /** @var PageModel $objPage */
        global $objPage;

        $objPage = $pageModel;
        $objPage->loadDetails();

        // Set the admin e-mail address
        if ($objPage->adminEmail) {
            [$GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']] = StringUtil::splitFriendlyEmail($objPage->adminEmail);
        } else {
            [$GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']] = StringUtil::splitFriendlyEmail(Config::get('adminEmail'));
        }

        // Backup some globals (see #7659)
        $arrBackup = [
            $GLOBALS['TL_HEAD'] ?? [],
            $GLOBALS['TL_BODY'] ?? [],
            $GLOBALS['TL_MOOTOOLS'] ?? [],
            $GLOBALS['TL_JQUERY'] ?? [],
            $GLOBALS['TL_USER_CSS'] ?? [],
            $GLOBALS['TL_FRAMEWORK_CSS'] ?? [],
        ];

        try {
            return (new PageRegular($responseContext))->getResponse($objPage, true);
        } // Render the error page (see #5570)
        catch (UnusedArgumentsException $e) {
            // Restore the globals (see #7659)
            [
                $GLOBALS['TL_HEAD'],
                $GLOBALS['TL_BODY'],
                $GLOBALS['TL_MOOTOOLS'],
                $GLOBALS['TL_JQUERY'],
                $GLOBALS['TL_USER_CSS'],
                $GLOBALS['TL_FRAMEWORK_CSS']
            ] = $arrBackup;

            throw $e;
        }
    }

    /**
     * @return array<string>
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.routing.page_registry'] = PageRegistry::class;

        return $services;
    }
}
