<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->arrayNode('db')
                    ->isRequired()
                    ->children()
                        ->scalarNode('host')->isRequired()->end()
                        ->scalarNode('port')->defaultValue('30845')->end()
                        ->scalarNode('username')->isRequired()->end()
                        ->scalarNode('#password')->isRequired()->end()
                        ->scalarNode('database')->isRequired()->end()
                    ->end()
                ->end()
                ->scalarNode('dbName')->isRequired()->end()
                ->scalarNode('tableId')->isRequired()->end()
                ->arrayNode('items')
                    ->isRequired()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('dbName')->isRequired()->end()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('type')->isRequired()->end()
                            ->scalarNode('size')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('relationships')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('column')->isRequired()->end()
                            ->arrayNode('target')
                                ->children()
                                    ->scalarNode('table')->isRequired()->end()
                                    ->scalarNode('column')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
