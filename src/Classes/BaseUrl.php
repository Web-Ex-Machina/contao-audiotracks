<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Classes;

use Contao\Database;
use Contao\Environment;
use Contao\System;

/**
 * The address of the website (https://www.example.org/), for the absolute URLs of
 * the RSS feeds.
 *
 * A feed is generated in the back end (a request: its host is known), but also by
 * the cron and the command line, where there is no request. The address is, in
 * this order:
 *  - the "audio_tracks.base_url" setting, if there is one,
 *  - the address of the current request,
 *  - the domain of the root pages of the website, if they all have the same one.
 */
final class BaseUrl
{
    /**
     * @throws \RuntimeException If the address of the website cannot be known
     */
    public static function get(): string
    {
        $container = System::getContainer();
        $configured = (string) ($container->hasParameter('wem_audiotracks.base_url') ? $container->getParameter('wem_audiotracks.base_url') : '');

        return self::resolve($configured, (string) Environment::get('base'), static fn (): array => self::findRootPages());
    }

    /**
     * @param callable(): list<array{dns: string, useSSL: string}> $rootPages The published root pages, called only if there is nothing else
     *
     * @throws \RuntimeException
     */
    public static function resolve(string $configured, string $request, callable $rootPages): string
    {
        foreach ([$configured, $request] as $candidate) {
            if (self::isValid($candidate)) {
                return self::normalize($candidate);
            }
        }

        // The root pages tell the address only if there is no doubt: all of them have
        // the same domain. A root page without domain answers for any domain, and with
        // several domains we cannot guess.
        $roots = $rootPages();
        $domains = array_unique(array_map(static fn (array $root): string => strtolower(trim((string) $root['dns'])).'|'.($root['useSSL'] ? '1' : '0'), $roots));

        if ([] !== $roots && 1 === \count($domains)) {
            [$dns, $ssl] = explode('|', (string) reset($domains));

            if ('' !== $dns && self::isValid(('1' === $ssl ? 'https://' : 'http://').$dns)) {
                return self::normalize(('1' === $ssl ? 'https://' : 'http://').$dns);
            }
        }

        throw new \RuntimeException('the address of the website is unknown (there is no request, and the root pages do not give a single domain): set "audio_tracks.base_url" in config/config.yaml');
    }

    private static function isValid(string $url): bool
    {
        return 1 === preg_match('#^https?://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d+)?(/[^\s]*)?$#i', trim($url));
    }

    private static function normalize(string $url): string
    {
        return rtrim(trim($url), '/').'/';
    }

    /**
     * @return list<array{dns: string, useSSL: string}>
     */
    private static function findRootPages(): array
    {
        return Database::getInstance()
            ->execute("SELECT dns, useSSL FROM tl_page WHERE type = 'root' AND published = '1'")
            ->fetchAllAssoc()
        ;
    }
}
