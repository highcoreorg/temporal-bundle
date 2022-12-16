<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;

final class WorkflowClientFactory
{
    private string $temporalCliAddress;
    private DataConverter $dataConverter;

    public function __construct(string $temporalCliAddress, DataConverter $dataConverter)
    {
        $this->temporalCliAddress = $temporalCliAddress;
        $this->dataConverter = $dataConverter;
    }

    public function __invoke(): WorkflowClient
    {
        return WorkflowClient::create(
            serviceClient: ServiceClient::create($this->temporalCliAddress),
            converter: $this->dataConverter
        );
    }
}
