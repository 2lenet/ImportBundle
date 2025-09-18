<?php

namespace Lle\ImportBundle\DependencyInjection;

use Lle\ImportBundle\Contracts\ReaderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LleImportExtension extends Extension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $processedConfig = $this->processConfiguration($configuration, $configs);

        $container->registerForAutoconfiguration(ReaderInterface::class)->addTag('import.reader');

        $container->setParameter('lle_import.configs', $processedConfig['configs']);
    }
}
