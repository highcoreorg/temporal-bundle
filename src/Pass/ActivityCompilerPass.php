<?php

declare(strict_types=1);

namespace Loper\TemporalBundle\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Loper\TemporalBundle\Registry\ActivityRegistry;

final class ActivityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ActivityRegistry::class)) {
            return;
        }

        $definition = $container->findDefinition(ActivityRegistry::class);
        $taggedServices = $container->findTaggedServiceIds('temporal.activity.registry');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}