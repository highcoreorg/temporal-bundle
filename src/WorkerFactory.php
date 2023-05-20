<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\DataConverter\DataConverter;
use Temporal\Worker\WorkerFactoryInterface as TemporalWorkerFactory;

final class WorkerFactory
{
    private DataConverter $dataConverter;

    public function __construct(DataConverter $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    public function __invoke(): TemporalWorkerFactory
    {
        return \Temporal\WorkerFactory::create($this->dataConverter);
    }
}
