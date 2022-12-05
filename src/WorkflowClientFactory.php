<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;

final class WorkflowClientFactory
{
    private string $temporalCliAddress;

    public function __construct(string $temporalCliAddress)
    {
        $this->temporalCliAddress = $temporalCliAddress;
    }

    public function __invoke(): WorkflowClient
    {
        return WorkflowClient::create(ServiceClient::create($this->temporalCliAddress));
    }
}