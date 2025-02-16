<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Runtime;

use Highcore\Component\Registry\ServiceRegistryInterface;
use Highcore\TemporalBundle\TemporalBundle;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Temporal\Worker\WorkerFactoryInterface;

final readonly class Runner implements RunnerInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private string $workerQueue =  WorkerFactoryInterface::DEFAULT_TASK_QUEUE
    ) {
    }

    public function run(): int
    {
        $this->kernel->boot();

        /** @var LoggerInterface $logger */
        $logger = $this->kernel->getContainer()->get(
            LoggerInterface::class,
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        );

        if ('' === $this->workerQueue) {
            $logger?->error('Worker queue name is empty.');

            return 1;
        }

        /** @var WorkerFactoryInterface $workerFactory */
        $workerFactory = $this->kernel->getContainer()->get(WorkerFactoryInterface::class);

        $worker = $workerFactory->newWorker($this->workerQueue);
        /** @var ServiceRegistryInterface $workflowRegistry */
        $workflowRegistry = $this->kernel->getContainer()
            ->get(TemporalBundle::WORKFLOW_REGISTRY_DEFINITION);

        foreach ($workflowRegistry->all() as $workflowType) {
            $worker->registerWorkflowTypes($workflowType);
        }

        /** @var ServiceRegistryInterface $activityRegistry */
        $activityRegistry = $this->kernel->getContainer()
            ->get(TemporalBundle::ACTIVITY_REGISTRY_DEFINITION);
        foreach ($activityRegistry->all() as $activity) {
            $worker->registerActivity($activity::class, static fn() => $activity);
        }

        return $workerFactory->run();
    }
}