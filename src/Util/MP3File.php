<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Util;

/**
 * Duration of an MP3 file, without any library.
 *
 * The duration is read from the Xing / Info / VBRI header when the file has one
 * (that is the case of the files encoded by LAME and most of the tools), else all
 * the frames are walked: it is exact for the constant and the variable bit rate.
 * A damaged part is skipped (bounded), the ID3v2 / ID3v1 tags are ignored.
 */
class MP3File
{
    // Bytes without any valid frame after which we give up
    private const MAX_GARBAGE = 1048576;

    private const VERSIONS = [0 => '2.5', 2 => '2', 3 => '1'];

    private const LAYERS = [1 => 3, 2 => 2, 3 => 1];

    // [version][layer] => bitrates (kbps) of the index 1 to 14
    private const BITRATES = [
        '1' => [
            1 => [32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
            2 => [32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            3 => [32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
        ],
        '2' => [
            1 => [32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            2 => [8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            3 => [8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        ],
    ];

    private const SAMPLE_RATES = [
        '1' => [44100, 48000, 32000],
        '2' => [22050, 24000, 16000],
        '2.5' => [11025, 12000, 8000],
    ];

    protected string|null $filename;

    public function __construct(string|null $filename)
    {
        $this->filename = $filename;
    }

    public static function formatTime($duration): string // as hh:mm:ss
    {
        $hours = floor($duration / 3600);
        $minutes = floor(($duration - ($hours * 3600)) / 60);
        $seconds = $duration - ($hours * 3600) - ($minutes * 60);

        return \sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    // Only the first frame is read: right for the constant bit rate files
    public function getDurationEstimate(): float|int
    {
        return $this->getDuration(true);
    }

    /**
     * Duration in seconds, 0 if the file cannot be read or has no audio frame.
     */
    public function getDuration(bool $use_cbr_estimate = false): float|int
    {
        if (!$this->filename || !is_file($this->filename) || !($fd = @fopen($this->filename, 'r'))) {
            return 0;
        }

        try {
            $size = (int) filesize($this->filename);
            $start = $this->skipID3v2Tag((string) fread($fd, 10));
            $end = $this->audioEnd($fd, $size);

            $position = $this->findFrame($fd, $start, $end);

            if (null === $position) {
                return 0;
            }

            [$info] = $position;
            $first = $position[1];

            $frames = $this->readFrameCount($fd, $first, $info);

            if (null !== $frames) {
                return round($frames * $info['Samples'] / $info['Sampling Rate']);
            }

            if ($use_cbr_estimate) {
                return round(($end - $first) / ($info['Bitrate'] * 125));
            }

            return round($this->walk($fd, $first, $end));
        } finally {
            fclose($fd);
        }
    }

    /**
     * Header of a frame: null if the four bytes are not a valid MPEG audio frame header.
     *
     * @return array{Version: string, Layer: int, Bitrate: int, 'Sampling Rate': int, Framesize: int, Samples: int, Channels: int}|array{}
     */
    public static function parseFrameHeader(string $fourbytes): array
    {
        if (4 !== \strlen($fourbytes)) {
            return [];
        }

        $b1 = \ord($fourbytes[1]);
        $b2 = \ord($fourbytes[2]);
        $b3 = \ord($fourbytes[3]);

        // 11 synchronization bits, a valid version, a valid layer
        if (0xFF !== \ord($fourbytes[0]) || 0xE0 !== ($b1 & 0xE0) || !isset(self::VERSIONS[($b1 & 0x18) >> 3]) || !isset(self::LAYERS[($b1 & 0x06) >> 1])) {
            return [];
        }

        $version = self::VERSIONS[($b1 & 0x18) >> 3];
        $layer = self::LAYERS[($b1 & 0x06) >> 1];
        $family = '1' === $version ? '1' : '2';

        $bitrateIndex = ($b2 & 0xF0) >> 4;
        $rateIndex = ($b2 & 0x0C) >> 2;

        // "free" (0) and "bad" (15) bit rates, reserved sample rate
        if (0 === $bitrateIndex || 15 === $bitrateIndex || 3 === $rateIndex) {
            return [];
        }

        $bitrate = self::BITRATES[$family][$layer][$bitrateIndex - 1];
        $sampleRate = self::SAMPLE_RATES[$version][$rateIndex];
        $padding = ($b2 & 0x02) >> 1;

        if (1 === $layer) {
            $frameSize = (intdiv(12 * $bitrate * 1000, $sampleRate) + $padding) * 4;
            $samples = 384;
        } else {
            // Layer III of the MPEG 2 / 2.5 has half the samples per frame, so half the size
            $samples = 3 === $layer && '1' !== $family ? 576 : 1152;
            $frameSize = intdiv($samples / 8 * $bitrate * 1000, $sampleRate) + $padding;
        }

        return [
            'Version' => $version,
            'Layer' => $layer,
            'Bitrate' => $bitrate,
            'Sampling Rate' => $sampleRate,
            'Framesize' => $frameSize,
            'Samples' => $samples,
            'Channels' => 3 === ($b3 & 0xC0) >> 6 ? 1 : 2,
        ];
    }

    /**
     * Position of the first valid frame from $from: [header, position], null if there
     * is none. A header is only accepted if another frame follows it (or the audio
     * stops there), so a sequence of bytes that looks like a header inside some data
     * does not count.
     *
     * @param resource $fd
     */
    private function findFrame($fd, int $from, int $end): array|null
    {
        $position = $from;
        $skipped = 0;

        while ($position < $end - 4 && $skipped <= self::MAX_GARBAGE) {
            fseek($fd, $position);
            $chunk = (string) fread($fd, 8192);

            if (\strlen($chunk) < 4) {
                return null;
            }

            $offset = 0;

            while (false !== ($found = strpos($chunk, "\xFF", $offset)) && $found <= \strlen($chunk) - 4) {
                $info = self::parseFrameHeader(substr($chunk, $found, 4));

                if ([] !== $info && $this->isFollowedByAFrame($fd, $position + $found, $info['Framesize'], $end)) {
                    return [$info, $position + $found];
                }

                $offset = $found + 1;
            }

            $step = max(1, \strlen($chunk) - 3);
            $position += $step;
            $skipped += $step;
        }

        return null;
    }

    /**
     * @param resource $fd
     */
    private function isFollowedByAFrame($fd, int $position, int $frameSize, int $end): bool
    {
        $next = $position + $frameSize;

        // The last frame of the file
        if ($next >= $end - 3) {
            return true;
        }

        fseek($fd, $next);

        return [] !== self::parseFrameHeader((string) fread($fd, 4));
    }

    /**
     * Number of frames announced by the Xing / Info (LAME) or the VBRI (Fraunhofer)
     * header of the first frame.
     *
     * @param resource $fd
     */
    private function readFrameCount($fd, int $position, array $info): int|null
    {
        fseek($fd, $position);
        $frame = (string) fread($fd, 4 + 36 + 16);

        // Position of the Xing header: after the side information
        $mono = 1 === $info['Channels'];
        $xingAt = '1' === $info['Version'] ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
        $xingAt += 4;

        $tag = substr($frame, $xingAt, 4);

        if (('Xing' === $tag || 'Info' === $tag) && \strlen($frame) >= $xingAt + 12) {
            $flags = unpack('N', substr($frame, $xingAt + 4, 4))[1];

            if (($flags & 1) !== 0) {
                $frames = unpack('N', substr($frame, $xingAt + 8, 4))[1];

                return $frames > 0 ? $frames : null;
            }
        }

        // VBRI: always 32 bytes after the header, the number of frames is at the offset 14
        if ('VBRI' === substr($frame, 36, 4) && \strlen($frame) >= 36 + 18) {
            $frames = unpack('N', substr($frame, 36 + 14, 4))[1];

            return $frames > 0 ? $frames : null;
        }

        return null;
    }

    /**
     * Walks all the frames: exact for the variable bit rate.
     *
     * @param resource $fd
     */
    private function walk($fd, int $position, int $end): float
    {
        $duration = 0.0;
        $skipped = 0;

        while ($position < $end - 4) {
            fseek($fd, $position);
            $info = self::parseFrameHeader((string) fread($fd, 4));

            if ([] !== $info) {
                $duration += $info['Samples'] / $info['Sampling Rate'];
                $position += $info['Framesize'];
                $skipped = 0;

                continue;
            }

            // Damaged part: look for the next frame, give up if there is none for a long time
            $next = $this->findFrame($fd, $position + 1, $end);

            if (null === $next) {
                break;
            }

            $skipped += $next[1] - $position;

            if ($skipped > self::MAX_GARBAGE) {
                break;
            }

            $position = $next[1];
        }

        return $duration;
    }

    /**
     * End of the audio: before the ID3v1 tag (128 bytes "TAG...") at the end of the file.
     *
     * @param resource $fd
     */
    private function audioEnd($fd, int $size): int
    {
        if ($size > 128) {
            fseek($fd, $size - 128);

            if ('TAG' === fread($fd, 3)) {
                return $size - 128;
            }
        }

        return $size;
    }

    /**
     * Size of the ID3v2 tag at the beginning of the file (header, tag, footer), 0 if
     * there is none.
     */
    private function skipID3v2Tag(string $header): int
    {
        if (10 !== \strlen($header) || 'ID3' !== substr($header, 0, 3)) {
            return 0;
        }

        $flags = \ord($header[5]);
        $bytes = [\ord($header[6]), \ord($header[7]), \ord($header[8]), \ord($header[9])];

        // The size is stored on 4 x 7 bits
        foreach ($bytes as $byte) {
            if (($byte & 0x80) !== 0) {
                return 0;
            }
        }

        $tagSize = ($bytes[0] * 2097152) + ($bytes[1] * 16384) + ($bytes[2] * 128) + $bytes[3];
        $footer = ($flags & 0x10) !== 0 ? 10 : 0;

        return 10 + $tagSize + $footer;
    }
}
