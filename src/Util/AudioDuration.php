<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Util;

/**
 * Duration of an audio file in seconds, from its header: nothing is decoded, no
 * library is needed. Supports the formats of the audio field: mp3, wav, ogg
 * (Vorbis and Opus).
 */
final class AudioDuration
{
    public const EXTENSIONS = ['mp3', 'wav', 'ogg', 'oga', 'opus'];

    /**
     * @return int Seconds, 0 if the format is not supported or the file cannot be read
     */
    public static function forFile(string $path): int
    {
        if (!is_file($path) || !is_readable($path)) {
            return 0;
        }

        try {
            $seconds = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'mp3' => (new MP3File($path))->getDuration(),
                'wav' => self::wav($path),
                'ogg', 'oga', 'opus' => self::ogg($path),
                default => 0,
            };
        } catch (\Throwable) {
            return 0;
        }

        return max(0, (int) round($seconds));
    }

    /**
     * WAV: the size of the data divided by the bytes per second of the "fmt " chunk.
     */
    private static function wav(string $path): float
    {
        $fd = fopen($path, 'r');

        if (!$fd) {
            return 0;
        }

        try {
            $header = (string) fread($fd, 12);

            if (12 !== \strlen($header) || 'RIFF' !== substr($header, 0, 4) || 'WAVE' !== substr($header, 8, 4)) {
                return 0;
            }

            $size = (int) filesize($path);
            $byteRate = 0;

            // The chunks: 4 bytes id, 4 bytes size, data (padded to an even size)
            for ($i = 0; $i < 64 && ftell($fd) + 8 <= $size; ++$i) {
                $chunk = (string) fread($fd, 8);

                if (8 !== \strlen($chunk)) {
                    break;
                }

                $id = substr($chunk, 0, 4);
                $length = unpack('V', substr($chunk, 4, 4))[1];

                if ('fmt ' === $id) {
                    $fmt = (string) fread($fd, min($length, 16));
                    $byteRate = \strlen($fmt) >= 12 ? unpack('V', substr($fmt, 8, 4))[1] : 0;
                    fseek($fd, $length - min($length, 16) + ($length & 1), SEEK_CUR);
                } elseif ('data' === $id) {
                    // A streamed file has no size (0 or 0xFFFFFFFF): everything up to the end of the file
                    $dataSize = 0 === $length || 0xFFFFFFFF === $length || $length > $size - ftell($fd) ? $size - ftell($fd) : $length;

                    return $byteRate > 0 ? $dataSize / $byteRate : 0;
                } else {
                    fseek($fd, $length + ($length & 1), SEEK_CUR);
                }
            }

            return 0;
        } finally {
            fclose($fd);
        }
    }

    /**
     * Ogg: the position (in samples) of the last page, divided by the sample rate of
     * the first packet.
     */
    private static function ogg(string $path): float
    {
        $fd = fopen($path, 'r');

        if (!$fd) {
            return 0;
        }

        try {
            // First page: the identification header gives the sample rate (Vorbis) or the
            // pre-skip (Opus)
            $first = (string) fread($fd, 4096);

            if ('OggS' !== substr($first, 0, 4)) {
                return 0;
            }

            $segments = \ord($first[26] ?? "\0");
            $packet = substr($first, 27 + $segments, 32);
            $preSkip = 0;

            if ("\x01vorbis" === substr($packet, 0, 7)) {
                $rate = unpack('V', substr($packet, 12, 4))[1];
            } elseif ('OpusHead' === substr($packet, 0, 8)) {
                // Opus always counts the positions at 48 kHz
                $rate = 48000;
                $preSkip = unpack('v', substr($packet, 10, 2))[1];
            } else {
                return 0;
            }

            if ($rate < 1) {
                return 0;
            }

            // Last page: it is in the last 64 KB, its granule position is the total number
            // of samples
            $size = (int) filesize($path);
            $tail = max(0, $size - 65536);
            fseek($fd, $tail);
            $data = (string) fread($fd, 65536);
            $last = strrpos($data, 'OggS');

            if (false === $last || \strlen($data) < $last + 14) {
                return 0;
            }

            $granule = unpack('P', substr($data, $last + 6, 8))[1];

            // -1 means "no position" (a page of a packet that goes on)
            return $granule > 0 ? max(0, $granule - $preSkip) / $rate : 0;
        } finally {
            fclose($fd);
        }
    }
}
