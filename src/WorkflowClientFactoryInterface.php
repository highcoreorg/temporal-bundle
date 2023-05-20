<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;

interface WorkflowClientFactoryInterface
{
    public function setOptions(ClientOptions $options): void;
    public function setAddress(string $address): void;
    public function setDataConverter(DataConverterInterface $converter): void;

    public function __invoke(): WorkflowClient;
}
