<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Highcore\CommandBus\AsContextMiddleware;
use Highcore\CommandBus\Context\ContextMiddleware;
use Highcore\Component\Registry\ServiceRegistry;
use Highcore\Registry\Bundle\DependencyInjection\Pass\ServiceAttributeRegistryPass;
use Highcore\TemporalBundle\Pass\ActivityTagCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Temporal\Activity\ActivityInterface;
use Temporal\Workflow\WorkflowInterface;

final class TemporalBundle extends Bundle
{
    public const WORKFLOW_REGISTRY_DEFINITION = 'temporal.workflow.registry';
    public const ACTIVITY_REGISTRY_DEFINITION = 'temporal.activity.registry';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ServiceAttributeRegistryPass(
            definition: self::ACTIVITY_REGISTRY_DEFINITION,
            targetClassAttribute: ActivityInterface::class,
            definitionClass: ServiceRegistry::class,
        ));
        $container->addCompilerPass(new ServiceAttributeRegistryPass(
            definition: self::WORKFLOW_REGISTRY_DEFINITION,
            targetClassAttribute: WorkflowInterface::class,
            definitionClass: ServiceRegistry::class,
        ));
    }
}