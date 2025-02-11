<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DependencyInjection;

use Highcore\TemporalBundle\FactoryWorkerFactory;
use Highcore\TemporalBundle\FactoryWorkerFactoryInterface;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Highcore\TemporalBundle\WorkflowClientFactoryInterface;
use Highcore\TemporalBundle\WorkflowLoadingMode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\WorkerFactory as TemporalWorkerFactory;

final class TemporalExtension extends Extension
{
    public const DATA_CONVERTER_INVALID_DEFINITION = 'DataConverter "%s" should be registered in the service container or should be a class which implements "%s"';
    public const WORKER_FACTORY_INVALID_DEFINITION = 'WorkerFactory "%s" should be registered in the service container or should be a class which implements "%s"';
    public const WORKFLOW_CLIENT_FACTORY_INVALID_DEFINITION = 'WorkflowClientFactory "%s" should be registered in the service container or should be a class which implements "%s"';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $workflowClient = $config['workflow']['client'] ?? $config['workflow_client'];
        $workflowLoadingMode = $config['workflow']['loading_mode'] ?? WorkflowLoadingMode::FileMode;
        $dataConverterConverters = $config['worker']['data_converter']['converters'];
        $workflowClientFactoryId = $this->getDefinitionWithCheck(
            container: $container,
            shouldImplements: WorkflowClientFactoryInterface::class,
            defaultDefinition: WorkflowClientFactory::class,
            definition: $workflowClient['factory'] ?? null,
            failMessage: self::WORKFLOW_CLIENT_FACTORY_INVALID_DEFINITION
        );
        $workerFactoryId = $this->getDefinitionWithCheck(
            container: $container,
            shouldImplements: FactoryWorkerFactoryInterface::class,
            defaultDefinition: FactoryWorkerFactory::class,
            definition: $config['worker']['factory'] ?? null,
            failMessage: self::WORKER_FACTORY_INVALID_DEFINITION
        );
        $dataConverterId = $this->getDefinitionWithCheck(
            container: $container,
            shouldImplements: DataConverterInterface::class,
            defaultDefinition: DataConverter::class,
            definition: $config['worker']['data_converter']['class'] ?? null,
            failMessage: self::DATA_CONVERTER_INVALID_DEFINITION,
            allowRegisterOnTheFly: true,
        );
        $options = $workflowClient['options'] ?? [];
        $queue = $config['worker']['queue'] ?? 'default';
        $namespace = $options['namespace'] ?? 'default';
        $temporalRpcAddress = $config['address'];

        $container->setParameter('temporal.worker.queue', $queue);
        $container->setParameter('temporal.address', $temporalRpcAddress);
        $container->setParameter('temporal.namespace', $namespace);
        $container->setParameter('temporal.workflow.loading.mode', $workflowLoadingMode);

        [$workerFactoryIdValue] = explode('::', $workerFactoryId);
        if (!$container->hasDefinition($workerFactoryIdValue)) {
            $container->setDefinition($workerFactoryIdValue, (new Definition($workerFactoryIdValue))
                ->setPublic(true)->setAutowired(true)->setAutoconfigured(true)
                ->setArgument('$dataConverter', new Reference($dataConverterId))
            );
        }

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
        $dataConverterDefinition = !$container->hasDefinition($dataConverterId)
            ? $container->register($dataConverterId, $dataConverterId)
            : $container->getDefinition($dataConverterId);
        $dataConverterDefinition->setArguments($serviceConverters);

        if (!$container->hasAlias(DataConverterInterface::class)) {
            $container->setAlias(DataConverterInterface::class, $dataConverterId);
        }

        if (!$container->hasDefinition($workflowClientFactoryId)) {
            $container->setDefinition($workflowClientFactoryId, (new Definition($workflowClientFactoryId))
                ->setPublic(true)->setAutowired(true)->setAutoconfigured(true)
                ->addMethodCall('setDataConverter', [new Reference($dataConverterId)])
                ->addMethodCall('setAddress', [$temporalRpcAddress])
                ->addMethodCall('setOptions', [$options])
            );
        }

        $factoryWorkerFactory = str_contains('::', $workerFactoryId) ? $workerFactoryId : new Reference($workerFactoryIdValue);
        $container->register(TemporalWorkerFactory::class, TemporalWorkerFactory::class)
            ->setFactory($factoryWorkerFactory);
        $container->register(WorkflowClient::class, WorkflowClient::class)
            ->setFactory([new Reference($workflowClientFactoryId), '__invoke']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }

    /**
     * @param ContainerBuilder $container
     * @param class-string $shouldImplements
     * @param class-string $defaultDefinition
     * @param class-string|string|null $definition
     * @param string $failMessage
     * @return string
     */
    public function getDefinitionWithCheck(
        ContainerBuilder $container,
        string $shouldImplements,
        string $defaultDefinition,
        ?string $definition,
        string $failMessage,
        bool $allowRegisterOnTheFly = false,
    ): string {
        $definitionId = $definition ??= $defaultDefinition;

        if (!class_exists($definitionId)) {
            $definitionId = !$container->hasDefinition($definitionId)
                ? null
                : $container->getDefinition($definitionId)->getClass();
        }

        if ((null === $definitionId && !$allowRegisterOnTheFly) || !is_a($definitionId, $shouldImplements, true)) {
            throw new InvalidConfigurationException(sprintf(
                $failMessage,
                $definition,
                $shouldImplements
            ));
        }

        return $definition;
    }
}
