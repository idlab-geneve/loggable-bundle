<?php

namespace Idlab\Loggable\Tests\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Idlab\Loggable\IdlabLoggableBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    public function __construct(
        string                  $environment,
        bool                    $debug,
        private readonly string $configFile
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),
            new IdlabLoggableBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function ($container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'router' => [
                    'enabled' => true,
                    'resource' => '%kernel.project_dir%/config/routes/test.yaml',
                ],
            ]);
            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'memory' => true,
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'mappings' => [
                        'Test' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/../Entity',
                            'prefix' => 'Idlab\\Loggable\\Tests\\Entity',
                        ],
                        'IdlabLoggableBundle' => [
                            'dir' => __DIR__ . '/../../src/Entity',
                            'prefix' => 'Idlab\\Loggable\\Entity',
                        ],
                    ],
                ],
            ]);
            $container->loadFromExtension('security', [
                'providers' => [
                    'in_memory' => ['memory' => null],
                ],
                'firewalls' => [
                    'main' => [
                        'pattern' => '^/',
                        'stateless' => false,
                    ],
                ],
            ]);
        });


        $loader->load(__DIR__ . '/../config/packages/' . $this->configFile);
    }
}
