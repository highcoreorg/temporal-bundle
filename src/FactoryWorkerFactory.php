<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\WorkerFactoryInterface as TemporalWorkerFactory;
use Temporal\WorkerFactory;

final class FactoryWorkerFactory implements FactoryWorkerFactoryInterface
{
    private DataConverterInterface $dataConverter;

    public function __construct(DataConverterInterface $dataConverter)
    {
        $this->dataConverter = $dataConverter;
    }

    public function __invoke(): TemporalWorkerFactory
    {
        return WorkerFactory::create($this->dataConverter);
    }
}
