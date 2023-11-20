<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Routing\Content;

final class UrlParameter
{
    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
        private readonly string|null $requirement = null,
        private readonly int|string|null $default = null,
        private readonly bool $identifier = false,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRequirement(): string|null
    {
        return $this->requirement;
    }

    public function getDefault(): int|string|null
    {
        return $this->default;
    }

    public function isIdentifier(): bool
    {
        return $this->identifier;
    }
}
