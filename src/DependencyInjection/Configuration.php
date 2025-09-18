<?php

namespace Lle\ImportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('lle_import');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('configs')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('entity')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('unique_key')->end()
                            ->scalarNode('import_helper_service')->end()
                            ->arrayNode('mappings')->requiresAtLeastOneElement()->variablePrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
