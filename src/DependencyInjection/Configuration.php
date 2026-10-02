<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('audio_tracks');
        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('retention_months')
                    ->info('Months to keep the listening sessions and to link the likes to a visitor (0 = forever, nothing is purged).')
                    ->min(0)
                    ->defaultValue(0)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
