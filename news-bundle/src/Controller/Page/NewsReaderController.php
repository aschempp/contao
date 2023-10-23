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

use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\Content\UrlParameter;
use Contao\CoreBundle\Routing\ResponseContext\CoreResponseContextFactory;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContext;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\ModuleNews;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsPage(contentTypes: [NewsModel::class])]
class NewsReaderController extends AbstractContentPageController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CoreResponseContextFactory $responseContextFactory,
        private readonly HtmlDecoder $htmlDecoder,
    ) {
    }

    protected function loadContent(string $identifier, UrlParameter $urlParameter, PageModel $pageModel): object|null
    {
        $this->framework->initialize();

        $archives = ModuleNews::sortOutProtected(StringUtil::deserialize($pageModel->newsArchives));

        if (empty($archives) || !\is_array($archives)) {
            throw new InternalServerErrorException('The news_reader page ID '.$pageModel->id.' has no archives specified.');
        }

        return NewsModel::findPublishedByParentAndIdOrAlias($identifier, $archives);
    }

    public function getResponse(Request $request, PageModel $pageModel, object $content): Response
    {
        if (!$content instanceof NewsModel) {
            throw new \InvalidArgumentException();
        }

        // Redirect if the news item has a target URL (see #1498)
        if (\in_array($content->source, ['internal', 'article', 'external'], true)) {
            $url = $this->generateContentUrl($content);

            if ($url === $request->getRequestUri()) {
                throw new InternalServerErrorException('Invalid target URL for news.');
            }

            return new RedirectResponse($url, 301);
        }

        $responseContext = $this->responseContextFactory->createContaoWebpageResponseContext($pageModel);

        $this->updateHtmlHeadBag($responseContext, $content);

        return $this->renderPage($pageModel, $responseContext);
    }

    private function updateHtmlHeadBag(ResponseContext $responseContext, NewsModel $newsModel): void
    {
        $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);

        if ($newsModel->pageTitle) {
            $htmlHeadBag->setTitle($newsModel->pageTitle); // Already stored decoded
        } elseif ($newsModel->headline) {
            $htmlHeadBag->setTitle($this->htmlDecoder->inputEncodedToPlainText($newsModel->headline));
        }

        if ($newsModel->description) {
            $htmlHeadBag->setMetaDescription($this->htmlDecoder->inputEncodedToPlainText($newsModel->description));
        } elseif ($newsModel->teaser) {
            $htmlHeadBag->setMetaDescription($this->htmlDecoder->htmlToPlainText($newsModel->teaser));
        }

        if ($newsModel->robots) {
            $htmlHeadBag->setMetaRobots($newsModel->robots);
        }
    }
}
