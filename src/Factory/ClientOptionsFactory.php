<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Factory;

use Temporal\Client\ClientOptions;

final class ClientOptionsFactory
{
    public static function createFromArray(array $options): ClientOptions
    {
        $clientOptions = new ClientOptions();

        if (isset($options['namespace'])) {
            $clientOptions = $clientOptions->withNamespace($options['namespace']);
        }

        if (isset($options['identity'])) {
            $clientOptions = $clientOptions->withIdentity($options['identity']);
        }

        if (isset($options['query-rejection-condition'])) {
            $clientOptions = $clientOptions->withQueryRejectionCondition($options['query-rejection-condition']);
        }

        return $clientOptions;
    }
}