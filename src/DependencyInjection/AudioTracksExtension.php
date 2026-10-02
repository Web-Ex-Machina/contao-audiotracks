<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use WEM\AudioTracksBundle\Classes\ClientIdentifier;
use WEM\AudioTracksBundle\Classes\RequestRateLimiter;

/**
 * Adds the bundle services to the container.
 */
class AudioTracksExtension extends Extension
{
    public function load(array $mergedConfig, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $mergedConfig);
        $container->setParameter('wem_audiotracks.retention_months', $config['retention_months']);
        $container->setParameter('wem_audiotracks.identifier', $config['identifier']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $this->registerRateLimiters($container, $config['rate_limit']);
    }

    /**
     * One limiter for the requests that write (likes, sessions) and one for the ones
     * that read (state).
     */
    private function registerRateLimiters(ContainerBuilder $container, int $limit): void
    {
        $container->setDefinition('wem.audiotracks.rate_limit.storage', new Definition(CacheStorage::class, [new Reference('cache.rate_limiter')]));

        foreach (['write', 'state'] as $name) {
            $options = ['id' => 'wem_audiotracks_'.$name, 'policy' => 'no_limit'];

            if ($limit > 0) {
                $options = ['id' => 'wem_audiotracks_'.$name, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'];
            }

            $container->setDefinition('wem.audiotracks.rate_limit.'.$name.'_factory', new Definition(RateLimiterFactory::class, [
                $options,
                new Reference('wem.audiotracks.rate_limit.storage'),
                new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]));

            $container->setDefinition('wem.audiotracks.rate_limit.'.$name, new Definition(RequestRateLimiter::class, [
                new Reference('wem.audiotracks.rate_limit.'.$name.'_factory'),
                new Reference(ClientIdentifier::class),
                new Reference('translator'),
            ]));
        }
    }
}
