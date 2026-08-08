<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the bundle.
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */
class AudioTracksBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
