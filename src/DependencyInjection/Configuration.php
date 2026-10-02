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
            ->integerNode('rate_limit')
            ->info('Maximum number of requests per minute and per visitor on the endpoints of the player (likes, listening sessions, state). 0 = no limit.')
            ->min(0)
            ->defaultValue(120)
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
