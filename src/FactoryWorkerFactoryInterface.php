<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Worker\WorkerFactoryInterface;

interface FactoryWorkerFactoryInterface
{
    public function __invoke(): WorkerFactoryInterface;
}
