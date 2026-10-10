<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the bundle.
 */
class AudioTracksBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
