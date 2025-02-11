<?php declare(strict_types=1);

use Highcore\TemporalBundle\TemporalBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$workflowRegistry', service(TemporalBundle::WORKFLOW_REGISTRY_DEFINITION))
        ->bind('$activityRegistry', service(TemporalBundle::ACTIVITY_REGISTRY_DEFINITION))
        ->bind('$workerFactory', service(Temporal\Worker\WorkerFactoryInterface::class))
        ->bind('$workerQueue', param('temporal.worker.queue'))
    ;

    $services->alias(Temporal\Worker\WorkerFactoryInterface::class, Temporal\WorkerFactory::class);
    $services->alias(Temporal\Client\WorkflowClientInterface::class, Temporal\Client\WorkflowClient::class);
};
