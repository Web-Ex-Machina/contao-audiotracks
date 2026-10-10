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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use WEM\AudioTracksBundle\Model\AudioTrack;

/**
 * Compresses the audio file of a track and keeps the Contao file manager in sync.
 */
class AudioOptimizer
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Mp3Compressor $compressor,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly bool $enabled,
    ) {
    }

    /**
     * Whether the compression is enabled in the configuration (the command ignores it).
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCompressor(): Mp3Compressor
    {
        return $this->compressor;
    }

    /**
     * Absolute path of the local audio file of the track, null if it has none.
     */
    public function getPath(AudioTrack $track): string|null
    {
        if (!$track->audio) {
            return null;
        }

        $this->framework->initialize();
        $file = FilesModel::findByUuid($track->audio);

        return null === $file ? null : Path::join($this->projectDir, $file->path);
    }

    /**
     * @return array{before: int, after: int}|null Sizes in bytes, null if nothing was done
     *
     * @throws \RuntimeException If the compression failed, the original is untouched
     */
    public function optimize(AudioTrack $track): array|null
    {
        $path = $this->getPath($track);

        if (null === $path) {
            return null;
        }

        $result = $this->compressor->compress($path);

        if (null !== $result) {
            // The hash and the size of the file changed
            Dbafs::addResource(Path::makeRelative($path, $this->projectDir));

            $this->logger->info(\sprintf('Audiotrack %d: "%s" compressed from %d to %d bytes', $track->id, basename($path), $result['before'], $result['after']));
        }

        return $result;
    }
}
