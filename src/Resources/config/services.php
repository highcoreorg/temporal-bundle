<?php declare(strict_types=1);

use Highcore\TemporalBundle\TemporalBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(Highcore\TemporalBundle\WorkflowRuntimeCommand::class)
        ->arg('$workerFactory', service(Temporal\Worker\WorkerFactoryInterface::class))
        ->arg('$workflowRegistry', service(TemporalBundle::WORKFLOW_REGISTRY_DEFINITION))
        ->arg('$activityRegistry', service(TemporalBundle::ACTIVITY_REGISTRY_DEFINITION))
        ->arg('$workflowLoadingMode', '%temporal.workflow.loading.mode%')
        ->arg('$workerQueue', '%temporal.worker.queue%')
        ->arg('$kernel', service('kernel'))
        ->tag('console.command');
    $services->alias(Temporal\Client\WorkflowClientInterface::class, Temporal\Client\WorkflowClient::class);
};
