<?php

declare(strict_types=1);

namespace Tests\Highcore\TemporalBundle;

use Highcore\TemporalBundle\DependencyInjection\TemporalExtension;
use Highcore\TemporalBundle\FactoryWorkerFactory;
use Highcore\TemporalBundle\FactoryWorkerFactoryInterface;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Highcore\TemporalBundle\WorkflowClientFactoryInterface;
use Highcore\TemporalBundle\WorkflowLoadingMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\NullConverter;

final class TemporalExtensionTest extends TestCase
{
    public static function correctRegisterProvider(): \Generator
    {
        yield 'correct register with all provided data' => [
            'config' => [
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
                            'namespace' => 'default',
                            'identity' => null,
                            'query_rejection_condition' => QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                        ],
                        'factory' => WorkflowClientFactory::class,
                    ]
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has(WorkflowClientFactory::class));
                self::assertFalse($container->has(FactoryWorkerFactory::class));
                self::assertFalse($container->has(DataConverter::class));
            },
            'assertion' => function (ContainerBuilder $container) {
                $container->compile();
                self::assertTrue($container->has(WorkflowClientFactory::class));
                self::assertTrue($container->has(FactoryWorkerFactory::class));
                self::assertTrue($container->has(DataConverter::class));
            }
        ];

        yield 'correct register without worker factory' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'queue' => 'default',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                        'class' => DataConverter::class,
                    ],
                ],
                'workflow' => [
                    'loading_mode' => WorkflowLoadingMode::ContainerMode,
                    'client' => [
                        'options' => [
                            'namespace' => 'default',
                            'identity' => null,
                            'query_rejection_condition' => QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                        ],
                        'factory' => WorkflowClientFactory::class,
                    ]
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has(WorkflowClientFactory::class));
                self::assertFalse($container->has(FactoryWorkerFactory::class));
                self::assertFalse($container->has(DataConverter::class));
            },
            'assertion' => function (ContainerBuilder $container) {
                $container->compile();
                self::assertTrue($container->has(WorkflowClientFactory::class));
                self::assertTrue($container->has(FactoryWorkerFactory::class));
                self::assertTrue($container->has(DataConverter::class));
            }
        ];

        yield 'correct register without workflow client' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'queue' => 'default',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                        'class' => DataConverter::class,
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has(WorkflowClientFactory::class));
                self::assertFalse($container->has(FactoryWorkerFactory::class));
                self::assertFalse($container->has(DataConverter::class));
            },
            'assertion' => function (ContainerBuilder $container) {
                $container->compile();
                self::assertTrue($container->has(WorkflowClientFactory::class));
                self::assertTrue($container->has(FactoryWorkerFactory::class));
                self::assertTrue($container->has(DataConverter::class));
            }
        ];

        yield 'correct register without data converter' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'queue' => 'default',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has(WorkflowClientFactory::class));
                self::assertFalse($container->has(FactoryWorkerFactory::class));
                self::assertFalse($container->has(DataConverter::class));
            },
            'assertion' => function (ContainerBuilder $container) {
                $container->compile();
                self::assertTrue($container->has(WorkflowClientFactory::class));
                self::assertTrue($container->has(FactoryWorkerFactory::class));
                self::assertTrue($container->has(DataConverter::class));
            }
        ];

        yield 'correct register data converter by id' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'queue' => 'default',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                        'class' => 'default.data.converter',
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('default.data.converter'));
                $container->register('default.data.converter', DataConverter::class);
            },
            'assertion' => function (ContainerBuilder $container) {
                $id = 'default.data.converter';
                self::assertTrue($container->has($id));
                self::assertTrue($container->has(DataConverterInterface::class));
                self::assertInstanceOf(DataConverterInterface::class, $container->get($id));
            }
        ];

        yield 'correct register factory worker factory by id' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'queue' => 'default',
                    'factory' => 'factory.of.worker.factory',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('factory.of.worker.factory'));
                $container->register(DataConverter::class, DataConverter::class);
                $container->setAlias(DataConverterInterface::class, DataConverter::class);

                $definition = $container->register('factory.of.worker.factory', FactoryWorkerFactory::class);
                $definition->setPublic(true);
                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);
            },
            'assertion' => function (ContainerBuilder $container) {
                $container->compile();
                $id = 'factory.of.worker.factory';
                self::assertTrue($container->has($id));
                self::assertInstanceOf(FactoryWorkerFactoryInterface::class, $container->get($id));
            }
        ];

        yield 'correct register workflow client factory by id' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                    ],
                ],
                'workflow' => [
                    'client' => [
                        'factory' => 'workflow.client.factory',
                    ]
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('factory.of.worker.factory'));
                $container->register('workflow.client.factory', WorkflowClientFactory::class);
            },
            'assertion' => function (ContainerBuilder $container) {
                $id = 'workflow.client.factory';
                self::assertTrue($container->has($id));
                self::assertInstanceOf(WorkflowClientFactoryInterface::class, $container->get($id));
            }
        ];
    }

    public static function incorrectRegisterProvider(): \Generator
    {
        yield 'incorrect factory of worker factory id' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'factory' => 'invalid_factory_id',
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('invalid_factory_id'));
            },
            'assertion' => function () {
                $this->expectException(InvalidConfigurationException::class);
                $this->expectExceptionMessageMatches('/^WorkerFactory "invalid_factory_id" should be registered/');
            }
        ];
        yield 'incorrect workflow client factory' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                    ],
                ],
                'workflow' => [
                    'client' => [
                        'factory' => 'workflow_client_factory_invalid',
                    ]
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('workflow_client_factory_invalid'));
            },
            'assertion' => function () {
                $this->expectException(InvalidConfigurationException::class);
                $this->expectExceptionMessageMatches('/^WorkflowClientFactory "workflow_client_factory_invalid" should be registered/');
            }
        ];
        yield 'incorrect data converter' => [
            'config' => [
                'address' => 'some_cool_host:7233',
                'worker' => [
                    'data_converter' => [
                        'converters' => [
                            NullConverter::class,
                        ],
                        'class' => 'data_converter_invalid',
                    ],
                ],
            ],
            'preload' => function (ContainerBuilder $container) {
                self::assertFalse($container->has('data_converter_invalid'));
            },
            'assertion' => function () {
                $this->expectException(InvalidConfigurationException::class);
                $this->expectExceptionMessageMatches('/^DataConverter "data_converter_invalid" should be registered/');
            }
        ];
    }

    #[DataProvider('correctRegisterProvider')]
    public function testCorrectRegister(array $config, ?callable $preload, callable $assertion): void
    {
        $container = new ContainerBuilder();
        ($preload ?? static fn () => null)(...)->call($this, $container);
        $extension = new TemporalExtension();
        $extension->load(['temporal' => $config], $container);
        $assertion(...)->call($this, $container);
    }

    #[DataProvider('incorrectRegisterProvider')]
    public function testInCorrectRegister(array $config, ?callable $preload, callable $assertion): void
    {
        $container = new ContainerBuilder();
        ($preload ?? static fn () => null)(...)->call($this, $container);
        $extension = new TemporalExtension();
        $assertion(...)->call($this, $container);
        $extension->load(['temporal' => $config], $container);
    }
}