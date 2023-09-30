<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;

interface WorkflowClientFactoryInterface
{
    public function __invoke(): WorkflowClientInterface;

    public function setOptions(array $options): void;

    public function setAddress(string $address): void;

    public function setDataConverter(DataConverterInterface $converter): void;
}
