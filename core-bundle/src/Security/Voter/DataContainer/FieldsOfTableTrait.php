<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Security\Voter\DataContainer;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

trait FieldsOfTableTrait
{
    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    private function canEditFieldsOfTable(TokenInterface $token, string $table): bool
    {
        $hasNotExcluded = false;

        // Intentionally do not load DCA, it should already be loaded. If DCA is not
        // loaded, the voter just always abstains because it can't decide.
        foreach ($GLOBALS['TL_DCA'][$table]['fields'] ?? [] as $config) {
            if (!($config['exclude'] ?? true)) {
                $hasNotExcluded = true;
                break;
            }
        }

        return $hasNotExcluded || $this->accessDecisionManager->decide($token, [ContaoCorePermissions::USER_CAN_EDIT_FIELDS_OF_TABLE], $table);
    }
}
