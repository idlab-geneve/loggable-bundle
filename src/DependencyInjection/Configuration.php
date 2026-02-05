<?php

namespace Idlab\Loggable\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('idlab_loggable');

        $treeBuilder->getRootNode()
            ->children()
            // Enabled or disabled
            ?->booleanNode('enabled')
            ->defaultTrue()
            ->isRequired()
            ->end()
            // Connection name
            ?->scalarNode('logs_target_connection_name')
            ->defaultValue('default')
            ->end()
            // Table prefix
            ?->scalarNode('table_prefix')
            ->defaultValue('')
            ->end()
            // Disallowed namespaces
            ?->arrayNode('disallowed_namespaces')
            ?->beforeNormalization()->castToArray()
            ->end()
            ->end()
            // Disallowed classes
            ?->arrayNode('disallowed_classes')
            ?->beforeNormalization()->castToArray()->end()
            ->end()
            ?->end();

        return $treeBuilder;
    }
}
