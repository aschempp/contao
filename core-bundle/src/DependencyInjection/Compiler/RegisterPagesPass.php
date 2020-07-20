<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\DependencyInjection\Compiler;

use Contao\CoreBundle\Routing\Page\CompositionAwareInterface;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Routing\Page\PageRouteEnhancerInterface;
use Contao\CoreBundle\Routing\Page\RouteConfig;
use Contao\FrontendIndex;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers Contao pages in the registry.
 */
class RegisterPagesPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private const TAG_NAME = 'contao.page';

    /**
     * Adds the fragments to the registry.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(PageRegistry::class)) {
            return;
        }

        $this->registerPages($container);
    }

    protected function registerPages(ContainerBuilder $container): void
    {
        $registry = $container->findDefinition(PageRegistry::class);

        foreach ($this->findAndSortTaggedServices(self::TAG_NAME, $container) as $reference) {
            $definition = $container->findDefinition((string) $reference);

            $tags = $definition->getTag(self::TAG_NAME);
            $definition->clearTag(self::TAG_NAME);

            foreach ($tags as $attributes) {
                $routeEnhancer = null;
                $compositionAware = null;
                $class = $definition->getClass();
                $type = $this->getPageType($class, $attributes);

                unset($attributes['type']);

                if (is_a($class, PageRouteEnhancerInterface::class, true)) {
                    $routeEnhancer = $reference;
                }

                if (is_a($class, CompositionAwareInterface::class, true)) {
                    $compositionAware = $reference;
                }

                $config = $this->getRouteConfig($reference, $definition, $attributes);
                $registry->addMethodCall('add', [$type, $config, $routeEnhancer, $compositionAware]);

                $definition->addTag(self::TAG_NAME, $attributes);
            }
        }
    }

    protected function getRouteConfig(Reference $reference, Definition $definition, array $attributes): Definition
    {
        $defaults = $attributes['defaults'] ?? [];
        $defaults['_controller'] = $this->getControllerName($reference, $definition, $attributes);

        return new Definition(
            RouteConfig::class,
            [
                $attributes['parameters'] ?? null,
                $attributes['requirements'] ?? [],
                $attributes['options'] ?? [],
                $defaults,
                $attributes['methods'] ?? [],
            ]
        );
    }

    /**
     * Returns the controller name from the service and method name.
     */
    private function getControllerName(Reference $reference, Definition $definition, array $attributes): string
    {
        if (isset($attributes['defaults']['_controller'])) {
            return $attributes['defaults']['_controller'];
        }

        $controller = (string) $reference;

        // Support a specific method on the controller
        if (isset($attributes['method'])) {
            $definition->setPublic(true);

            return $controller.':'.$attributes['method'];
        }

        if (($class = $definition->getClass()) && method_exists($class, '__invoke')) {
            $definition->setPublic(true);

            return $controller;
        }

        return FrontendIndex::class.'::renderPage';
    }

    private function getPageType(string $className, array $attributes): string
    {
        if (isset($attributes['type'])) {
            return (string) $attributes['type'];
        }

        $className = ltrim(strrchr($className, '\\'), '\\');

        if ('Controller' === substr($className, -10)) {
            $className = substr($className, 0, -10);
        }

        if ('Page' === substr($className, -4)) {
            $className = substr($className, 0, -4);
        }

        return Container::underscore($className);
    }
}
