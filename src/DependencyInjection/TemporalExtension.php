<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DependencyInjection;

use Highcore\TemporalBundle\WorkflowClientFactory;
use Highcore\TemporalBundle\WorkflowClientFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\ActivityInvocationCache\InMemoryActivityInvocationCache;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory as WorkerFactory;
use Temporal\Testing\WorkerFactory as TestingWorkerFactory;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class TemporalExtension extends Extension
{
    const TEMPORAL_OPTIONS_SERVICE_ID = 'temporal.workflow_client.options';

    private function isTestingEnabled(array $options): bool
    {
        return $config['worker']['testing']['enabled'] ?? false;
    }

    private function extractWorkerQueue(array $config, string $default = 'default'): string
    {
        return $config['worker']['queue'] ?? $default;
    }

    private function extractTemporalAddress(array $config, string $default = 'localhost:7233'): string
    {
        return $config['address'] ?? $default;
    }

    private function extractTemporalNamespace(array $config, string $default = 'default'): string
    {
        return $config['namespace'] ?? $default;
    }

    private function extractWorkflowClientOptions(array $config): array
    {
        return $config['workflow_client']['options'] ?? [];
    }

    private function extractWorkflowClientFactoryClass(array $config, string $default = WorkflowClientFactory::class): string
    {
        $class = $config['workflow_client']['factory'] ?? $default;

        if (!is_a($class, WorkflowClientFactoryInterface::class, true)) {
            throw new InvalidArgumentException(\sprintf(
                'Class "%s" should implement "%s" interface.',
                $class,
                WorkflowClientFactoryInterface::class,
            ));
        }

        return $class;
    }

    private function extractDataConverterClasses(array $config): array
    {
        return $config['worker']['data_converter']['converters'] ?? [];
    }

    private function extractDataConverterFacadeClass(array $config, string $default = DataConverter::class): string
    {
        return $config['worker']['data_converter']['class'] ?? $default;
    }

    private function extractWorkerFactoryClass(array $config, string $default = WorkerFactory::class): string
    {
        return $config['worker']['factory'] ?? $default;
    }

    private function extractActivityInvocationCacheClass(array $config, string $default = InMemoryActivityInvocationCache::class): string
    {
        return $config['worker']['testing']['activity_invocation_cache'] ?? $default;
    }

    private function registerWorkerQueueParameter(
        array $config,
        ContainerBuilder $container,
        string $defaultQueue = 'default',
    ): void {
        $container->setParameter(
            'temporal.worker.queue',
            $this->extractWorkerQueue($config, $defaultQueue),
        );
    }

    private function registerTemporalAddressParameter(
        array $config,
        ContainerBuilder $container,
    ): void {
        $container->setParameter(
            'temporal.address',
            $this->extractTemporalAddress($config),
        );
    }

    private function registerTemporalNamespaceParameter(
        array $config,
        ContainerBuilder $container,
        string $defaultNamespace = 'default',
    ): void {
        $container->setParameter(
            'temporal.namespace',
            $this->extractTemporalNamespace($config, $defaultNamespace),
        );
    }

    private function registerDataConverterService(
        array $config,
        ContainerBuilder $container,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Definition {
        $dataConverterFacadeClass = $this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass);
        if ($container->hasDefinition($dataConverterFacadeClass)) {
            return $container->getDefinition($dataConverterFacadeClass);
        }

        return $container->register($dataConverterFacadeClass)
            ->setArguments(array_map(function ($dataConverterDelegateClass) use ($container): Reference {
                if (class_exists($dataConverterDelegateClass) && !$container->hasDefinition($dataConverterDelegateClass)) {
                    $container->register($dataConverterDelegateClass)
                        ->setAutoconfigured(true)
                        ->setAutowired(true)
                    ;
                }

                return new Reference($dataConverterDelegateClass);
            }, $this->extractDataConverterClasses($config)));
    }

    private function registerDataConverterAlias(
        array $config,
        ContainerBuilder $container,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Alias {
        if ($container->hasAlias(DataConverterInterface::class)) {
            return $container->getAlias(DataConverterInterface::class);
        }

        return $container->setAlias(DataConverterInterface::class, $this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass));
    }

    private function registerWorkerFactoryService(
        array $config,
        ContainerBuilder $container,
        string $defaultWorkerFactoryClass = WorkerFactory::class,
        string $defaultDataConverterFacadeClass = DataConverter::class,
        string $defaultActivityInvocationCacheClass = InMemoryActivityInvocationCache::class,
    ): Definition {
        $workerFactoryClass = $this->extractWorkerFactoryClass($config, $defaultWorkerFactoryClass);
        if ($container->hasDefinition($workerFactoryClass)) {
            return $container->getDefinition($workerFactoryClass);
        }

        if ($this->isTestingEnabled($config)) {
            return $container->register($workerFactoryClass)
                ->setPublic(true)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setFactory("{$workerFactoryClass}::create")
                ->setArgument('$converter', new Reference($this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass)))
                ->setArgument('$activityCache', new Reference($this->extractActivityInvocationCacheClass($config, $defaultActivityInvocationCacheClass)))
            ;
        }

        return $container->register($workerFactoryClass)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setFactory("{$workerFactoryClass}::create")
            ->setArgument('$converter', new Reference($this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass)))
        ;
    }

    private function registerWorkerFactoryAlias(
        array $config,
        ContainerBuilder $container,
        string $defaultWorkerFactoryClass = WorkerFactory::class,
    ): Alias {
        $workerFactoryClass = $this->extractWorkerFactoryClass($config, $defaultWorkerFactoryClass);
        if ($container->hasDefinition($workerFactoryClass)) {
            return $container->setAlias(WorkerFactoryInterface::class, $workerFactoryClass);
        }

        if ($this->isTestingEnabled($config)) {
            return $container->setAlias(WorkerFactoryInterface::class, TestingWorkerFactory::class);
        }

        return $container->setAlias(WorkerFactoryInterface::class, WorkerFactory::class);
    }

    private function registerActivityInvocationCacheService(
        array $config,
        ContainerBuilder $container,
        string $defaultActivityInvocationCacheClass = InMemoryActivityInvocationCache::class,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Definition {
        return match ($this->extractActivityInvocationCacheClass($config, $defaultActivityInvocationCacheClass)) {
            RoadRunnerActivityInvocationCache::class => $this->registerRoadRunnerActivityInvocationCacheService($config, $container, $defaultDataConverterFacadeClass),
            InMemoryActivityInvocationCache::class => $this->registerInMemoryActivityInvocationCacheService($config, $container, $defaultDataConverterFacadeClass),
            default => $container->getDefinition($this->extractActivityInvocationCacheClass($config, $defaultActivityInvocationCacheClass)),
        };
    }

    private function registerRoadRunnerActivityInvocationCacheService(
        array $config,
        ContainerBuilder $container,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Definition {
        if ($container->hasDefinition(RoadRunnerActivityInvocationCache::class)) {
            return $container->getDefinition(RoadRunnerActivityInvocationCache::class);
        }

        return $container->register(RoadRunnerActivityInvocationCache::class)
            ->setFactory(sprintf('%s::create', RoadRunnerActivityInvocationCache::class))
            ->addArgument('$dataConverter', new Reference($this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass)))
        ;
    }

    private function registerInMemoryActivityInvocationCacheService(
        array $config,
        ContainerBuilder $container,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Definition {
        if ($container->hasDefinition(InMemoryActivityInvocationCache::class)) {
            return $container->getDefinition(InMemoryActivityInvocationCache::class);
        }

        return $container->register(InMemoryActivityInvocationCache::class)
            ->setFactory(sprintf('%s::create', InMemoryActivityInvocationCache::class))
            ->addArgument('$dataConverter', new Reference($this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass)))
        ;
    }

    private function registerActivityInvocationCacheAlias(
        array $config,
        ContainerBuilder $container,
        string $defaultActivityInvocationCacheClass = InMemoryActivityInvocationCache::class,
    ): Alias {
        $activityInvocationCacheClass = $this->extractActivityInvocationCacheClass($config, $defaultActivityInvocationCacheClass);

        return $container->setAlias(ActivityInvocationCacheInterface::class, new Alias($activityInvocationCacheClass));
    }

    private function registerWorkflowClientFactoryService(
        array $config,
        ContainerBuilder $container,
        string $defaultWorkflowClientFactoryClass = WorkflowClientFactory::class,
        string $defaultDataConverterFacadeClass = DataConverter::class,
    ): Definition {
        $workflowClientFactoryClass = $this->extractWorkflowClientFactoryClass($config, $defaultWorkflowClientFactoryClass);

        if ($container->hasDefinition($workflowClientFactoryClass)) {
            return $container->getDefinition($workflowClientFactoryClass);
        }

        return $container->register($workflowClientFactoryClass)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addMethodCall('setDataConverter', [new Reference($this->extractDataConverterFacadeClass($config, $defaultDataConverterFacadeClass))])
            ->addMethodCall('setAddress', [$this->extractTemporalAddress($config)])
            ->addMethodCall('setOptions', [$this->extractWorkflowClientOptions($config)])
        ;
    }

    private function registerWorkflowClientService(
        array $config,
        ContainerBuilder $container,
        string $defaultWorkflowClientFactoryClass = WorkflowClientFactory::class,
    ): Definition {
        return $container->register(WorkflowClient::class)
                ->setFactory(new Reference($this->extractWorkflowClientFactoryClass($config, $defaultWorkflowClientFactoryClass)));
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $options = $config['workflow_client']['options'] ?? [];

        $this->registerWorkerQueueParameter($config, $container);
        $this->registerTemporalAddressParameter($config, $container);
        $this->registerTemporalNamespaceParameter($config, $container);

        $this->registerDataConverterService($config, $container);
        $this->registerDataConverterAlias($config, $container);
        $this->registerWorkerFactoryService($config, $container);
        $this->registerWorkerFactoryAlias($config, $container);
        $this->registerWorkflowClientFactoryService($config, $container);
        $this->registerWorkflowClientService($config, $container);

        if ($this->isTestingEnabled($options)) {
            $this->registerActivityInvocationCacheService($config, $container);
            $this->registerActivityInvocationCacheAlias($config, $container);
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }
}
