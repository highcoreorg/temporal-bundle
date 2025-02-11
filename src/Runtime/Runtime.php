<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Runtime;

use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

final class Runtime extends SymfonyRuntime
{
    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof KernelInterface && Mode::MODE_TEMPORAL === getenv('RR_MODE')) {
            return new Runner($application);
        }

        return parent::getRunner($application);
    }
}