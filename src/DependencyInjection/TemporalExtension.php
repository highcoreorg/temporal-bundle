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
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;

final class TemporalExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $options = $this->createOptions($config['workflow_client']['options'] ?? []);
        $workflowClientFactoryClass = $config['workflow_client']['factory'] ?? WorkflowClientFactory::class;
        $dataConverterConverters = $config['worker']['data_converter']['converters'];
        $workerFactory = $config['worker']['factory'] ?? WorkerFactory::class;
        $dataConverterClass = $config['worker']['data_converter']['class'];
        $temporalRpcAddress = $config['address'];
        $queue = $config['worker']['queue'] ?? 'default';

        $container->setParameter('temporal.worker.queue', $queue);
        $container->setParameter('temporal.address', $temporalRpcAddress);
        $container->setParameter('temporal.namespace', $options->namespace);

        if (!is_a($workflowClientFactoryClass, WorkflowClientFactoryInterface::class, true)) {
            throw new \RuntimeException(\sprintf('Class "%s" should implemenets "%s" interface.', $workflowClientFactoryClass, WorkflowClientFactoryInterface::class));
        }

        [$workerFactoryClass] = explode('::', $workerFactory);
        if (!$container->hasDefinition($workerFactoryClass)) {
            $workerFactoryDefinition = $container->register($workerFactoryClass, $workerFactoryClass);
            $workerFactoryDefinition->setArgument('$dataConverter', new Reference($dataConverterClass));
        }

        $dataConverterDefintion = $container->register($dataConverterClass, $dataConverterClass);
        foreach ($dataConverterConverters as $converterId) {
            if (class_exists($converterId) && !$container->hasDefinition($converterId)) {
                $container->register($converterId, $converterId);
            }

            $serviceConveters[] = new Reference($converterId);
        }
        $dataConverterDefintion->setArguments($serviceConveters);

        if (!$container->hasAlias(DataConverterInterface::class)) {
            $container->setAlias(DataConverterInterface::class, $dataConverterClass);
        }

        if (!$container->hasDefinition($workflowClientFactoryClass)) {
            $definition = $container->register($workflowClientFactoryClass, $workflowClientFactoryClass);
            $definition->addMethodCall('setDataConverter', [new Reference($dataConverterClass)]);
            $definition->addMethodCall('setAddress', [$temporalRpcAddress]);
            $definition->addMethodCall('setOptions', [$options]);
        }

        $workerFactory = str_contains('::', $workerFactory) ? $workerFactory : new Reference($workerFactoryClass);
        $container->register(\Temporal\WorkerFactory::class, \Temporal\WorkerFactory::class)
            ->setFactory($workerFactory);
        $container->register(\Temporal\Client\WorkflowClient::class, \Temporal\Client\WorkflowClient::class)
            ->setFactory($workflowClientFactoryClass);

        $container->setAlias(\Temporal\Worker\WorkerFactoryInterface::class, \Temporal\WorkerFactory::class);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        if (!$container->hasDefinition($dataConverterClass)) {
            throw new \LogicException('Data Converter Class "%s" should be registered.');
        }
    }

    private function createOptions(mixed $options): ClientOptions
    {
        $clientOptions = new ClientOptions();

        if (isset($options['namespace'])) {
            $clientOptions = $clientOptions->withNamespace($options['namespace']);
        }

        if (isset($options['identity'])) {
            $clientOptions = $clientOptions->withIdentity($options['identity']);
        }

        if (isset($options['query-rejection-condition'])) {
            $clientOptions = $clientOptions->withQueryRejectionCondition($options['query-rejection-condition']);
        }

        return $clientOptions;
    }
}
