<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Highcore\Component\Registry\ServiceRegistry;
use Highcore\Registry\Bundle\DependencyInjection\Pass\ServiceAttributeRegistryPass;
use Highcore\TemporalBundle\Attribute\AsActivity;
use Highcore\TemporalBundle\Attribute\AsWorkflow;
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
            definitionId: self::ACTIVITY_REGISTRY_DEFINITION,
            definitionClass: ServiceRegistry::class,
            targetClassAttribute: AsActivity::class,
        ));
        $container->addCompilerPass(new ServiceAttributeRegistryPass(
            definitionId: self::WORKFLOW_REGISTRY_DEFINITION,
            definitionClass: ServiceRegistry::class,
            targetClassAttribute: AsWorkflow::class,
        ));
    }
}