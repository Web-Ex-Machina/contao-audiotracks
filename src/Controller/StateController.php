<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS Copyright (c) 2023 Web ex Machina.
 *
 * @category ContaoBundle
 *
 * @see     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use WEM\AudioTracksBundle\Classes\ClientIdentifier;

/**
 * Gives the data that depend on the visitor (likes, listening sessions) of a list
 * of tracks.
 *
 * The pages of the list and of the reader do not contain any of it (nor the
 * request token), so they can be cached and shared: the player asks for it once
 * the page is loaded. The response is private and is never cached.
 */
#[AsController]
#[Route(
    '/_wem_audiotracks/state',
    name: 'wem_audiotracks_state',
    defaults: ['_scope' => 'frontend', '_token_check' => false],
    methods: ['GET'],
)]
class StateController
{
    private const MAX_IDS = 200;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClientIdentifier $clientIdentifier,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $request->query->get('ids', ''))),
            static fn (int $id): bool => $id > 0,
        )));
        $ids = \array_slice($ids, 0, self::MAX_IDS);

        $tracks = [];

        if ([] !== $ids) {
            $ip = $this->clientIdentifier->get();

            foreach ($ids as $id) {
                $tracks[$id] = ['likes' => 0, 'liked' => false, 'session' => null];
            }

            $likes = $this->connection->fetchAllKeyValue(
                'SELECT pid, COUNT(*) FROM tl_wem_audiotrack_feedback WHERE pid IN (?) GROUP BY pid',
                [$ids],
                [ArrayParameterType::INTEGER],
            );

            foreach ($likes as $pid => $count) {
                $tracks[(int) $pid]['likes'] = (int) $count;
            }

            $liked = $this->connection->fetchFirstColumn(
                'SELECT pid FROM tl_wem_audiotrack_feedback WHERE ip = ? AND pid IN (?)',
                [$ip, $ids],
                [ParameterType::STRING, ArrayParameterType::INTEGER],
            );

            foreach ($liked as $pid) {
                $tracks[(int) $pid]['liked'] = true;
            }

            $sessions = $this->connection->fetchAllAssociative(
                'SELECT pid, currentTime, volume, complete FROM tl_wem_audiotrack_session WHERE ip = ? AND pid IN (?)',
                [$ip, $ids],
                [ParameterType::STRING, ArrayParameterType::INTEGER],
            );

            foreach ($sessions as $session) {
                $tracks[(int) $session['pid']]['session'] = [
                    'currentTime' => (int) $session['currentTime'],
                    'volume' => (float) $session['volume'],
                    'complete' => '1' === (string) $session['complete'],
                ];
            }
        }

        // The request token is given here (and not in the HTML) for the AJAX calls of
        // the player
        $response = new JsonResponse(['requestToken' => $this->csrfTokenManager->getDefaultTokenValue(), 'tracks' => (object) $tracks]);
        // Personal data: never stored by the browser cache, the proxy or the Contao page cache
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
