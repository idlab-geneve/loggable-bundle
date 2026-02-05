<?php

namespace Idlab\Loggable\DependencyInjection;

use Idlab\Loggable\Config\IdlabLoggableConfig;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class IdlabLoggableExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        // Feature toggle
        if ($config['enabled']) {
            $definition = new Definition(IdlabLoggableConfig::class);
            $definition->setArguments([
                $config['enabled'],
                $config['logs_target_connection_name'] ?? 'default',
                $config['table_prefix'] ?? '',
                $config['disallowed_namespaces'] ?? [],
                $config['disallowed_classes'] ?? [],
            ]);

            // Define where are the YAML files of the bundle
            $loader = new YamlFileLoader(
                $container,
                new FileLocator(__DIR__ . '/../../Resources/config')
            );

            // Load the services for the bundle
            $loader->load('services.yaml');

            $container->setDefinition(IdlabLoggableConfig::class, $definition);
        }
    }
}
