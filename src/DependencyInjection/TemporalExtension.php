<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DependencyInjection;

use Highcore\TemporalBundle\WorkerFactory;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Highcore\TemporalBundle\WorkflowClientFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Temporal\Client\ClientOptions;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory as TemporalWorkerFactory;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class TemporalExtension extends Extension
{
    const TEMPORAL_OPTIONS_SERVICE_ID = 'temporal.workflow_client.options';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $options = $config['workflow_client']['options'] ?? [];
        $workflowClientFactoryClass = $config['workflow_client']['factory'] ?? WorkflowClientFactory::class;
        $dataConverterConverters = $config['worker']['data_converter']['converters'];
        $workerFactory = $config['worker']['factory'] ?? WorkerFactory::class;
        $dataConverterClass = $config['worker']['data_converter']['class'];
        $queue = $config['worker']['queue'] ?? 'default';
        $namespace = $options['namespace'] ?? 'default';
        $temporalRpcAddress = $config['address'];

        $container->setParameter('temporal.worker.queue', $queue);
        $container->setParameter('temporal.address', $temporalRpcAddress);
        $container->setParameter('temporal.namespace', $namespace);

        if (!is_a($workflowClientFactoryClass, WorkflowClientFactoryInterface::class, true)) {
            throw new \RuntimeException(\sprintf('Class "%s" should implemenets "%s" interface.', $workflowClientFactoryClass, WorkflowClientFactoryInterface::class));
        }

        [$workerFactoryClass] = explode('::', $workerFactory);
        if (!$container->hasDefinition($workerFactoryClass)) {
            $container->setDefinition($workerFactoryClass, (new Definition($workerFactoryClass))
                ->setPublic(true)->setAutowired(true)->setAutoconfigured(true)
                ->setArgument('$dataConverter', new Reference($dataConverterClass))
            );
        }

        $dataConverterDefinition = $container->register($dataConverterClass, $dataConverterClass);
        $serviceConverters = [];
        foreach ($dataConverterConverters as $converterId) {
            if (class_exists($converterId) && !$container->hasDefinition($converterId)) {
                $container->setDefinition($converterId, (new Definition($converterId))
                    ->setAutoconfigured(true)
                    ->setAutowired(true)
                    ->setPublic(true)
                );
            }

            $serviceConverters[] = new Reference($converterId);
        }
        $dataConverterDefinition->setArguments($serviceConverters);

        if (!$container->hasAlias(DataConverterInterface::class)) {
            $container->setAlias(DataConverterInterface::class, $dataConverterClass);
        }

        if (!$container->hasDefinition($workflowClientFactoryClass)) {
            $container->setDefinition($workflowClientFactoryClass, (new Definition($workflowClientFactoryClass))
                ->setPublic(true)->setAutowired(true)->setAutoconfigured(true)
                ->addMethodCall('setDataConverter', [new Reference($dataConverterClass)])
                ->addMethodCall('setAddress', [$temporalRpcAddress])
                ->addMethodCall('setOptions', [$options])
            );
        }

        $workerFactory = str_contains('::', $workerFactory) ? $workerFactory : new Reference($workerFactoryClass);
        $container->register(TemporalWorkerFactory::class, TemporalWorkerFactory::class)
            ->setFactory($workerFactory);
        $container->register(WorkflowClient::class, WorkflowClient::class)
            ->setFactory([new Reference($workflowClientFactoryClass), '__invoke']);

        $container->setAlias(WorkerFactoryInterface::class, TemporalWorkerFactory::class);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        if (!$container->hasDefinition($dataConverterClass)) {
            throw new \LogicException('Data Converter Class "%s" should be registered.');
        }
    }
}
