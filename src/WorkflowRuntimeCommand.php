<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Highcore\TemporalBundle\Registry\ActivityRegistry;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'temporal:workflow:runtime')]
final class WorkflowRuntimeCommand extends Command
{
    private WorkerFactoryInterface $workerFactory;
    private ActivityRegistry $activityRegistry;
    private KernelInterface $kernel;
    private ?string $workerQueue;

    public function __construct(
        WorkerFactoryInterface $workerFactory,
        ActivityRegistry $activityRegistry,
        KernelInterface $kernel,
        ?string $workerQueue = null
    ) {
        parent::__construct();
        $this->kernel = $kernel;
        $this->workerQueue = $workerQueue;
        $this->activityRegistry = $activityRegistry;
        $this->workerFactory = $workerFactory;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        if ('' === $this->workerQueue) {
            $style->error(\sprintf('Worker queue name "%s" is not valid.', $this->workerQueue));
            return Command::FAILURE;
        }

        $queueName = $this->workerQueue ?? WorkerFactoryInterface::DEFAULT_TASK_QUEUE;


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
