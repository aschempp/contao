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
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Contao\DataContainer;
use Contao\DC_Table;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;

/**
 * @internal
 */
class TableAccessVoter implements CacheableVoterInterface
{
    use FieldsOfTableTrait;

    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, ContaoCorePermissions::DC_PREFIX);
    }

    public function supportsType(string $subjectType): bool
    {
        return match ($subjectType) {
            CreateAction::class,
            UpdateAction::class => true,
            default => false,
        };
    }

    /**
     * @param CreateAction|UpdateAction $subject
     */
    public function vote(TokenInterface $token, $subject, array $attributes): int
    {
        if (
            !\is_a(DC_Table::class, DataContainer::getDriverForTable($subject->getDataSource(), true))
            || !array_filter($attributes, $this->supportsAttribute(...))
        ) {
            return self::ACCESS_ABSTAIN;
        }

        return $this->canEditFieldsOfTable($token, $subject->getDataSource()) ? self::ACCESS_ABSTAIN : self::ACCESS_DENIED;
    }
}
