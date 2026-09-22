<?php

namespace Idlab\Loggable\Tests\DependencyInjection;

use Idlab\Loggable\DependencyInjection\Configuration;
use Idlab\Loggable\DependencyInjection\IdlabLoggableExtension;
use Idlab\Loggable\Config\IdlabLoggableConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testValidConfiguration(): void
    {
        $config = [
            'enabled' => false,
            'logs_target_connection_name' => 'example_logs',
            'table_prefix' => 'example_table_prefix',
            'disallowed_namespaces' => [],
            'disallowed_classes' => [],
        ];

        $processor = new Processor();
        $processed = $processor->processConfiguration(
            new Configuration(),
            [$config]
        );

        $this->assertSame('example_logs', $processed['logs_target_connection_name']);
        $this->assertSame('example_table_prefix', $processed['table_prefix']);
        $this->assertFalse($processed['enabled']);
        $this->assertFalse($processed['snapshot_on_delete']);
    }

    public function testDeleteSnapshotCanBeEnabled(): void
    {
        $processor = new Processor();
        $processed = $processor->processConfiguration(
            new Configuration(),
            [['snapshot_on_delete' => true]]
        );

        $this->assertTrue($processed['snapshot_on_delete']);
    }

    public function testDeleteSnapshotIsPassedToTheContainerConfiguration(): void
    {
        $container = new ContainerBuilder();

        (new IdlabLoggableExtension())->load([
            ['snapshot_on_delete' => true],
        ], $container);

        $definition = $container->getDefinition(IdlabLoggableConfig::class);

        $this->assertTrue($definition->getArgument(5));
    }
}
