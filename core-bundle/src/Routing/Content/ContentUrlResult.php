<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Routing\Content;

use Nyholm\Psr7\Uri;

final class ContentUrlResult
{
    public function __construct(public readonly object|string|null $result)
    {
        if (\is_string($result) && !(new Uri($result))->getScheme()) {
            throw new \InvalidArgumentException('ContentUrlResult must not be an relative URL.');
        }
    }

    public function isAbstained(): bool
    {
        return null === $this->result;
    }

    public function hasTargetUrl(): bool
    {
        return \is_string($this->result);
    }

    public function getTargetUrl(): string
    {
        if (!$this->hasTargetUrl()) {
            throw new \BadMethodCallException('ContentUrlResult does not have a target URL.');
        }

        return $this->result;
    }

    public static function create(object|string|null $content): self
    {
        return new self($content);
    }

    public static function abstain(): self
    {
        return new self(null);
    }

    public static function absoluteUrl(string $url): self
    {
        return new self($url);
    }
}
