<?php

namespace Contao\CoreBundle\Controller\Page;

use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractCompositeController extends AbstractController
{
    protected function renderPageContent(PageModel $pageModel, bool $checkPermission = true): Response
    {
        if ($checkPermission) {
            $this->denyAccessUnlessGrantedForPage($pageModel);
        }

        global $objPage;

        $objPage = $pageModel;

        $this->initializeAdminEmail((string) $pageModel->adminEmail);

        // Backup some globals (see #7659)
        $arrHead = $GLOBALS['TL_HEAD'];
        $arrBody = $GLOBALS['TL_BODY'];
        $arrMootools = $GLOBALS['TL_MOOTOOLS'];
        $arrJquery = $GLOBALS['TL_JQUERY'];

        try {
            $pageHandler = new PageRegular();

            return $pageHandler->getResponse($pageModel, true);

        } catch (\UnusedArgumentsException $e) {

            // Restore the globals (see #7659)
            $GLOBALS['TL_HEAD'] = $arrHead;
            $GLOBALS['TL_BODY'] = $arrBody;
            $GLOBALS['TL_MOOTOOLS'] = $arrMootools;
            $GLOBALS['TL_JQUERY'] = $arrJquery;

            throw $e;
        }
    }

    private function initializeAdminEmail(string $adminEmail)
    {
        if ('' === $adminEmail) {
            /** @var Config $config */
            $config = $this->get('contao.framework')->getAdapter(Config::class);
            $adminEmail = (string) $config->get('adminEmail');
        }

        list($GLOBALS['TL_ADMIN_NAME'], $GLOBALS['TL_ADMIN_EMAIL']) = StringUtil::splitFriendlyEmail($adminEmail);
    }
}
