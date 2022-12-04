<?php

declare(strict_types=1);

namespace Loper\TemporalBundle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Loper\TemporalBundle\Registry\ActivityRegistry;

final class WorkflowRuntimeCommand extends Command
{
    public const WORKER_QUEUE = 'worker_queue';

    protected static $defaultName = 'temporal:workflow:runtime';

    private WorkerFactoryInterface $workerFactory;
    private ActivityRegistry $activityRegistry;
    private KernelInterface $kernel;

    public function __construct(
        WorkerFactoryInterface $workerFactory,
        ActivityRegistry $activityRegistry,
        KernelInterface $kernel,
    ) {
        parent::__construct();
        $this->kernel = $kernel;
        $this->activityRegistry = $activityRegistry;
        $this->workerFactory = $workerFactory;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workerQueue = $input->getOption(self::WORKER_QUEUE);
        $style = new SymfonyStyle($input, $output);

        if ('' === $workerQueue) {
            $style->error(\sprintf('Worker queue name "%s" is not valid.', $workerQueue));
            return Command::FAILURE;
        }

        $queueName = $workerQueue ?? WorkerFactoryInterface::DEFAULT_TASK_QUEUE;
        $worker = $this->workerFactory->newWorker($queueName);

        foreach ($this->getWorkflowTypes() as $workflowType) {
            $worker->registerWorkflowTypes($workflowType);
        }

        foreach ($this->activityRegistry->all() as $activity) {
            $worker->registerActivity(get_class($activity), static fn() => $activity);
        }

        $this->workerFactory->run();

        return Command::SUCCESS;
    }

    private function getWorkflowTypes(): array
    {
        $workflowTypesConfig = $this->kernel->getProjectDir() . '/config/workflows.php';

        if (!\file_exists($workflowTypesConfig)) {
            return [];
        }

        $workflowTypes = require $workflowTypesConfig;

        if (!\is_array($workflowTypes)) {
            throw new \RuntimeException('Workflow config should return array.');
        }

        return $workflowTypes;
    }
}