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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Limits the number of requests of a visitor on the public endpoints of the bundle.
 *
 * The visitor is the one of the likes and sessions (ClientIdentifier): no IP
 * address is kept in the cache.
 */
class RequestRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $factory,
        private readonly ClientIdentifier $clientIdentifier,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Counts a request, answers with a "429 Too Many Requests" if the visitor
     * exceeded the limit.
     */
    public function consume(): JsonResponse|null
    {
        $limit = $this->factory->create($this->clientIdentifier->get())->consume();

        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return new JsonResponse(
            ['status' => 'error', 'message' => $this->translator->trans('WEM.AUDIOTRACKS.tooManyRequests', [], 'contao_default')],
            JsonResponse::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retryAfter, 'Cache-Control' => 'no-store'],
        );
    }
}
