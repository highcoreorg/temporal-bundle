<?php

declare(strict_types=1);

namespace Tests\Highcore\TemporalBundle;

use Highcore\TemporalBundle\DataConverter\SymfonySerializerJsonClassObjectConverter;
use Highcore\TemporalBundle\DependencyInjection\Configuration;
use Highcore\TemporalBundle\WorkflowLoadingMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Highcore\TemporalBundle\FactoryWorkerFactory;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoJsonConverter;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $configValue = [
            'address' => 'some_cool_host:7233',
            'worker' => [
                'queue' => 'default',
                'factory' => FactoryWorkerFactory::class,
                'data_converter' => [
                    'converters' => [
                        NullConverter::class,
                    ],
                    'class' => DataConverter::class,
                ],
            ],
            'workflow' => [
                'loading_mode' => WorkflowLoadingMode::FileMode,
                'client' => [
                    'options' => [
                        'namespace' => 'default',
                        'identity' => null,
                        'query_rejection_condition' => QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                    ],
                    'factory' => WorkflowClientFactory::class,
                ],
            ],
        ];

        $processedConfig = $processor->processConfiguration($configuration, [
            'temporal' => $configValue
        ]);

        $this->assertEquals($configValue, $processedConfig);
    }

    public function testDefaultDeprecatedConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $configValue = [
            'address' => 'some_cool_host:7233',
            'worker' => [
                'queue' => 'default',
                'factory' => FactoryWorkerFactory::class,
                'data_converter' => [
                    'converters' => [
                        NullConverter::class,
                    ],
                    'class' => DataConverter::class,
                ],
            ],
            'workflow_client' => [
                'options' => [
                    'namespace' => 'default',
                    'identity' => null,
                    'query_rejection_condition' => QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                ],
                'factory' => WorkflowClientFactory::class,
            ],
        ];

        $processedConfig = $processor->processConfiguration($configuration, [
            'temporal' => $configValue
        ]);

        $this->assertEquals($configValue, $processedConfig);
    }

    public function testInvalidFileMode(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/^The value ".*" is not allowed for path ".*". Permissible values: .*$/');

        $processor = new Processor();
        $configuration = new Configuration();

        $configValue = [
            'address' => 'some_cool_host:7233',
            'worker' => [
                'queue' => 'default',
                'factory' => FactoryWorkerFactory::class,
                'data_converter' => [
                    'converters' => [
                        NullConverter::class,
                    ],
                    'class' => DataConverter::class,
                ],
            ],
            'workflow' => [
                'loading_mode' => 'invalid',
            ],
        ];

        $processedConfig = $processor->processConfiguration($configuration, [
            'temporal' => $configValue
        ]);

        $this->assertEquals($configValue, $processedConfig);
    }

    public function testInvalidQueryRejectionCondition(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/^The value \d+ is not allowed for path ".*". Permissible values: .*$/');

        $processor = new Processor();
        $configuration = new Configuration();

        $configValue = [
            'address' => 'some_cool_host:7233',
            'worker' => [
                'queue' => 'default',
                'factory' => FactoryWorkerFactory::class,
                'data_converter' => [
                    'converters' => [
                        NullConverter::class,
                    ],
                    'class' => DataConverter::class,
                ],
            ],
            'workflow' => [
                'client' => [
                    'options' => [
                        'query_rejection_condition' => 10012301203, // invalid value
                    ],
                ]
            ],
        ];

        $processedConfig = $processor->processConfiguration($configuration, [
            'temporal' => $configValue
        ]);

        $this->assertEquals($configValue, $processedConfig);
    }
}