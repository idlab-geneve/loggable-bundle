<?php

namespace Idlab\Loggable\Tests\DependencyInjection;

use Idlab\Loggable\DependencyInjection\Configuration;
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
    }
}
