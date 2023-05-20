<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DependencyInjection;

use Highcore\TemporalBundle\WorkerFactory;
use Highcore\TemporalBundle\WorkflowClientFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\DataConverter\DataConverter;

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
                        ->variableNode('factory')->defaultValue(WorkerFactory::class)->end()
                        ->arrayNode('data_converter')
                            ->children()
                                ->arrayNode('converters')
                                    ->requiresAtLeastOneElement()
                                    ->useAttributeAsKey('name')
                                    ->prototype('scalar')->end()
                                ->end()
                                ->scalarNode('class')->defaultValue(DataConverter::class)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('workflow_client')
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
