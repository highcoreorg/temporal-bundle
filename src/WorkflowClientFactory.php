<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;

final class WorkflowClientFactory implements WorkflowClientFactoryInterface
{
    private DataConverterInterface $dataConverter;
    private ClientOptions $options;
    private string $address;

    public function __invoke(): WorkflowClient
    {
        return WorkflowClient::create(
            serviceClient: ServiceClient::create($this->address),
            options: $this->options,
            converter: $this->dataConverter
        );
    }

    public function setOptions(ClientOptions $options): void
    {
        $this->options = $options;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function setDataConverter(DataConverterInterface $converter): void
    {
        $this->dataConverter = $converter;
    }
}
