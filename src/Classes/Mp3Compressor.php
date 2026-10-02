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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use WEM\AudioTracksBundle\Util\AudioDuration;
use WEM\AudioTracksBundle\Util\MP3File;

/**
 * Re-encodes an MP3 to a lower bit rate (and to mono) with ffmpeg: a spoken
 * episode does not need more than 64 kbps mono, which is 3 to 5 times smaller
 * than the 192 / 320 kbps stereo files often uploaded.
 *
 * The new file is written beside the original, checked (same duration, really smaller),
 * and only then replaces it. The original can be kept out of the public directory.
 */
class Mp3Compressor
{
    // A file under the target bit rate + this margin is already small enough
    private const MARGIN = 1.15;

    // The duration of the new file may differ from this many seconds
    private const DURATION_TOLERANCE = 2;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $bitrate = 64,
        private readonly int $channels = 1,
        private readonly bool $keepOriginal = true,
        private readonly int $timeout = 600,
    ) {
    }

    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->ffmpeg, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether the file is an MP3 above the target (average bit rate, so the variable
     * bit rate files are right too, or with more channels than the target).
     */
    public function needsCompression(string $path): bool
    {
        if ('mp3' !== strtolower(pathinfo($path, PATHINFO_EXTENSION)) || !is_file($path)) {
            return false;
        }

        $frame = (new MP3File($path))->getFirstFrame();
        $seconds = AudioDuration::forFile($path);

        if ([] === $frame || $seconds < 1) {
            return false;
        }

        $average = (int) filesize($path) * 8 / 1000 / $seconds;

        return $average > $this->bitrate * self::MARGIN || $frame['Channels'] > $this->channels;
    }

    /**
     * @param string $path Absolute path of the MP3
     *
     * @return array{before: int, after: int}|null Sizes in bytes, null if nothing was done
     *
     * @throws \RuntimeException If ffmpeg is missing or fails, the original is then untouched
     */
    public function compress(string $path): array|null
    {
        if (!$this->needsCompression($path)) {
            return null;
        }

        $filesystem = new Filesystem();
        $temporary = Path::join(\dirname($path), '.'.pathinfo($path, PATHINFO_FILENAME).'.compressing.mp3');

        try {
            $this->encode($path, $temporary);

            $before = (int) filesize($path);
            $after = is_file($temporary) ? (int) filesize($temporary) : 0;
            $expected = AudioDuration::forFile($path);
            $actual = AudioDuration::forFile($temporary);

            if (0 === $after || abs($expected - $actual) > self::DURATION_TOLERANCE) {
                throw new \RuntimeException(\sprintf('The compressed file is not valid (duration %ds instead of %ds), the original is kept.', $actual, $expected));
            }

            if ($after >= $before) {
                // Nothing gained: do not touch the file
                return null;
            }

            if ($this->keepOriginal) {
                $filesystem->copy($path, $this->originalPath($path), true);
            }

            $filesystem->rename($temporary, $path, true);

            return ['before' => $before, 'after' => $after];
        } finally {
            $filesystem->remove($temporary);
        }
    }

    /**
     * Where the original is kept: outside of the public directory.
     */
    public function originalPath(string $path): string
    {
        return Path::join($this->projectDir, 'var/audiotracks-originals', Path::makeRelative($path, $this->projectDir));
    }

    private function encode(string $source, string $target): void
    {
        $process = new Process([
            $this->ffmpeg, '-nostdin', '-y', '-v', 'error',
            '-i', $source,
            // Only the audio: the embedded cover is useless (the page has its own)
            '-map', '0:a:0', '-map_metadata', '0',
            '-c:a', 'libmp3lame', '-b:a', $this->bitrate.'k', '-ac', (string) $this->channels,
            '-id3v2_version', '3',
            '-f', 'mp3', $target,
        ]);
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new \RuntimeException(\sprintf('ffmpeg took more than %d seconds, the original is kept.', $this->timeout));
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('ffmpeg could not be run: '.$throwable->getMessage(), 0, $throwable);
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('ffmpeg failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
