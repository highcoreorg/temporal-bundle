<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Highcore\TemporalBundle\Registry\ActivityRegistry;

final class ActivityAttributeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ActivityRegistry::class)) {
            return;
        }

        $definition = $container->findDefinition(ActivityRegistry::class);

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}