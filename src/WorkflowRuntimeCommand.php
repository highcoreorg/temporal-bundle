<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Highcore\Component\Registry\ServiceRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Worker\WorkerFactoryInterface;

#[AsCommand(name: 'temporal:workflow:runtime')]
final class WorkflowRuntimeCommand extends Command
{
    public function __construct(
        private readonly WorkerFactoryInterface $workerFactory,
        private readonly ServiceRegistryInterface $activityRegistry,
        private readonly ServiceRegistryInterface $workflowRegistry,
        private readonly WorkflowLoadingMode $workflowLoadingMode,
        private readonly KernelInterface $kernel,
        private readonly ?string $workerQueue = null
    ) {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function getFromConfigFile(): array
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
        if ($this->workflowLoadingMode === WorkflowLoadingMode::ContainerMode) {
            return $this->workflowRegistry->all();
        }

        return $this->getFromConfigFile();
    }
}