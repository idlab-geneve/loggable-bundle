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
            ->end()
            // Snapshot deleted entities
            ?->booleanNode('snapshot_on_delete')
            ->defaultFalse()
            ->end()
            // Include inverse-side Doctrine associations
            ?->booleanNode('include_inverse_associations')
            ->defaultFalse()
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
            ?->scalarPrototype()->end()
            ->end()
            // Disallowed classes
            ?->arrayNode('disallowed_classes')
            ?->scalarPrototype()->end()
            ->end()
            ?->end();

        return $treeBuilder;
    }
}
