<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DependencyInjection;

use Highcore\TemporalBundle\DataConverter\SymfonySerializerJsonClassObjectConverter;
use Highcore\TemporalBundle\FactoryWorkerFactory;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Highcore\TemporalBundle\WorkflowLoadingMode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoJsonConverter;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('temporal');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('address')->defaultValue('localhost:7233')->end()
                ->arrayNode('worker')
                    ->children()
                        ->scalarNode('queue')->defaultValue('default')->end()
                        ->variableNode('factory')
                            ->defaultValue(FactoryWorkerFactory::class)
                        ->end()
                        ->arrayNode('data_converter')
                            ->children()
                                ->arrayNode('converters')
                                    ->requiresAtLeastOneElement()
                                    ->useAttributeAsKey('name')
                                    ->prototype('scalar')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->defaultValue([
                                        NullConverter::class,
                                        BinaryConverter::class,
                                        ProtoJsonConverter::class,
                                        SymfonySerializerJsonClassObjectConverter::class,
                                        JsonConverter::class,
                                    ])
                                ->end()
                                ->scalarNode('class')
                                    ->defaultValue(DataConverter::class)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('workflow')
                    ->children()
                        ->enumNode('loading_mode')
                            ->values(WorkflowLoadingMode::cases())
                            ->defaultValue(WorkflowLoadingMode::FileMode)
                        ->end()
                        ->arrayNode('client')
                            ->children()
                                ->arrayNode('options')
                                    ->children()
                                        ->scalarNode('namespace')->defaultValue('default')->end()
                                        ->scalarNode('identity')->example('pid@host')->end()
                                        ->enumNode('query_rejection_condition')
                                            ->values([
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                            ])
                                            ->defaultValue(QueryRejectCondition::QUERY_REJECT_CONDITION_NONE)
                                        ->end()
                                    ->end()
                                ->end()
                                ->scalarNode('factory')->defaultValue(WorkflowClientFactory::class)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('workflow_client')
                    ->setDeprecated('highcore/temporal-bundle', '1.2', 'The "%node%" at path "%path%" is deprecated, use "workflow.client" instead.')
                    ->children()
                        ->arrayNode('options')
                            ->children()
                                ->scalarNode('namespace')->defaultValue('default')->end()
                                ->scalarNode('identity')->example('pid@host')->end()
                                ->enumNode('query_rejection_condition')
                                    ->values([
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                    ])
                                    ->defaultValue(QueryRejectCondition::QUERY_REJECT_CONDITION_NONE)
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('factory')->defaultValue(WorkflowClientFactory::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
