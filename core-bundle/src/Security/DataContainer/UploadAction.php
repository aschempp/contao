<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Security\DataContainer;

class UploadAction extends AbstractAction
{
    use CurrentTrait;

    public function __construct(
        string $dataSource,
        private readonly array $current,
        private readonly string|null $content = null,
    ) {
        parent::__construct($dataSource);
    }

    public function getContent(): string|null
    {
        return $this->content;
    }

    protected function getSubjectInfo(): array
    {
        $subject = parent::getSubjectInfo();
        $subject[] = 'ID: '.$this->getCurrentId();

        return $subject;
    }
}
