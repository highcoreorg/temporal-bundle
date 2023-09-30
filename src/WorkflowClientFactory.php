<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Highcore\TemporalBundle\Factory\ClientOptionsFactory;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;

final class WorkflowClientFactory implements WorkflowClientFactoryInterface
{
    private DataConverterInterface $dataConverter;
    private ClientOptions $options;
    private string $address;

    public function __invoke(): WorkflowClientInterface
    {
        return WorkflowClient::create(
            serviceClient: ServiceClient::create($this->address),
            options: $this->options,
            converter: $this->dataConverter
        );
    }

    public function setOptions(array $options): void
    {
        $this->options = (new ClientOptionsFactory())->createFromArray($options);
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
