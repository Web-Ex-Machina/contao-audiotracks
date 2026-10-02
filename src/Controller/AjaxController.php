<?php

declare(strict_types=1);

/**
 * Audiotracks for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/contao-audiotracks
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/contao-audiotracks/
 */

namespace WEM\AudioTracksBundle\Controller;

use Contao\Environment;
use Contao\Model\Collection;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use WEM\AudioTracksBundle\Model\AudioTrack;
use WEM\AudioTracksBundle\Model\Feedback;
use WEM\AudioTracksBundle\Model\Session;

/**
 * Endpoint used by the frontend player to store the likes and the listening sessions.
 * The Contao request token (REQUEST_TOKEN) is checked by the core for POST requests.
 */
#[AsController]
#[Route(
    '/_wem_audiotracks/{action}',
    name: 'wem_audiotracks_ajax',
    requirements: ['action' => 'feedback|syncSession'],
    defaults: ['_scope' => 'frontend', '_token_check' => true],
    methods: ['POST'],
)]
class AjaxController
{
    public function __invoke(Request $request, string $action): JsonResponse
    {
        $audiotrack = (int) $request->request->get('audiotrack');

        if ($audiotrack < 1 || null === AudioTrack::findByPk($audiotrack)) {
            return new JsonResponse(['status' => 'error', 'message' => 'No audiotrack provided'], 400);
        }

        match ($action) {
            'feedback' => $this->updateFeedback($audiotrack, 'false' !== $request->request->get('liked')),
            'syncSession' => $this->updateSession(
                $audiotrack,
                (float) $request->request->get('currentTime', 0),
                (float) ($request->request->get('volume') ?: 1),
                'true' === $request->request->get('complete'),
            ),
        };

        return new JsonResponse(['status' => 'success']);
    }

    private function updateFeedback(int $pid, bool $like): void
    {
        $strIp = Environment::get('ip');

        if (!$like && $objFeedback = Feedback::findItems(['pid' => $pid, 'ip' => $strIp], 1)) {
            $objFeedback->delete();
        }

        if ($like && 0 === Feedback::countItems(['pid' => $pid, 'ip' => $strIp])) {
            $objFeedback = new Feedback();
            $objFeedback->tstamp = time();
            $objFeedback->createdAt = time();
            $objFeedback->pid = $pid;
            $objFeedback->ip = $strIp;
            $objFeedback->save();
        }
    }

    private function updateSession(int $pid, float $currentTime, float $volume, bool $markAsComplete): void
    {
        $strIp = Environment::get('ip');
        $objSession = Session::findItems(['pid' => $pid, 'ip' => $strIp], 1);

        if (!$objSession instanceof Collection) {
            $objSession = new Session();
            $objSession->createdAt = time();
            $objSession->pid = $pid;
            $objSession->ip = $strIp;
        }

        $objSession->tstamp = time();
        $objSession->volume = $volume;
        $objSession->currentTime = $currentTime;
        $objSession->complete = $markAsComplete ? 1 : '';
        $objSession->save();
    }
}
