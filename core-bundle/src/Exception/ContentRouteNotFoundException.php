<?php

namespace Contao\CoreBundle\Exception;

use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Throwable;

class ContentRouteNotFoundException extends RouteNotFoundException
{
    public function __construct($content, $code = 0, Throwable $previous = null)
    {
        parent::__construct('No route found for '.static::getRouteDebugMessage($content), $code, $previous);
    }

    public static function getRouteDebugMessage($content): string
    {
        if (is_scalar($content)) {
            return $content;
        }

        if (\is_array($content)) {
            return serialize($content);
        }

        if ($content instanceof RouteObjectInterface) {
            return 'key '.$content->getRouteKey();
        }

        if ($content instanceof Route) {
            return 'path '.$content->getPath();
        }

        if (\is_object($content)) {
            return \get_class($content);
        }

        return 'null route';
    }
}
