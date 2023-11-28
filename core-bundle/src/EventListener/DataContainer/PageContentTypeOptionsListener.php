<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\DataContainer;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCallback(table: 'tl_page', target: 'fields.requireItem.options')]
class PageContentTypeOptionsListener
{
    public function __construct(
        private readonly PageRegistry $pageRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(DataContainer $dc): array
    {
        $options = [
            '1' => $this->translator->trans('tl_page.requiredItems.1.0', [], 'contao_tl_page'),
        ];

        foreach ($this->pageRegistry->getContentTypes() as $contentType) {
            $options[$contentType] = $this->translator->trans('tl_page.requiredItems.'.$contentType.'.0', [], 'contao_tl_page');
        }

        return $options;
    }
}
