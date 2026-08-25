<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('portadesign_data_quality');

        /** @var ArrayNodeDefinition $root */
        $root = $treeBuilder->getRootNode();
        $root->children()
            ->integerNode('classification_store_id')
                ->defaultValue(1)
                ->info('Classification Store ID that classificationStoreKey-typed QualityConfiguration rules are resolved against.')
            ->end()
            ->scalarNode('channel_relation_field_name')
                ->defaultValue('channels')
                ->info('Field key expected on target DataObject classes: the relation field holding the object\'s Channel(s).')
            ->end()
            ->scalarNode('category_relation_field_name')
                ->defaultValue('categories')
                ->info('Field key expected on target DataObject classes: the relation field holding the object\'s Category/Categories.')
            ->end()
            ->integerNode('note_author_user_id')
                ->defaultValue(1)
                ->info('Backend user id used as the author of Notes written by quality observers (defaults to Pimcore\'s bootstrap "admin" user).')
            ->end()
        ->end();

        return $treeBuilder;
    }
}
