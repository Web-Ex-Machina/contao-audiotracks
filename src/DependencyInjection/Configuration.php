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
            ->scalarNode('base_url')
            ->info('Address of the website for the absolute urls of the RSS feeds, ie: https://www.example.org/. Only needed if the feeds are generated without a request (cron, command) and the root page has no domain. Empty = the address of the current request, else the domain of the root pages if they all have the same one. An environment variable placeholder is accepted.')
            ->defaultValue('')
            ->validate()
            ->ifTrue(static fn ($v): bool => '' !== $v && !str_contains((string) $v, '%') && 1 !== preg_match('#^https?://[^\s/]+(/\S*)?$#i', (string) $v))
            ->thenInvalid('"base_url" must be an http(s) address, ie: https://www.example.org/')
            ->end()
            ->end()
            ->enumNode('identifier')
            ->info('How the visitors of the likes and sessions are stored: "encryption" (the IP is encrypted, it can be decrypted with the key) or "hmac" (one-way hash, the IP can never be retrieved). Switching to "hmac" converts the stored visitors with contao:migrate, it cannot be reverted.')
            ->values(['encryption', 'hmac'])
            ->defaultValue('encryption')
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
