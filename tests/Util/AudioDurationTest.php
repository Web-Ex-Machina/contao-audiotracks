<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Util;

use PHPUnit\Framework\TestCase;
use WEM\AudioTracksBundle\Util\AudioDuration;
use WEM\AudioTracksBundle\Util\MP3File;

/**
 * The audio files are built here: a header followed by silence, only the structure matters.
 */
class AudioDurationTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function file(string $content, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wemaudio').'.'.$extension;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    /** MPEG 1 Layer III, 128 kbps, 44.1 kHz, stereo: 417 bytes, 1152 samples */
    private static function frameV1(): string
    {
        return "\xFF\xFB\x90\x00".str_repeat("\0", 413);
    }

    /** MPEG 2 Layer III, 64 kbps, 22.05 kHz, mono: 208 bytes, 576 samples */
    private static function frameV2(): string
    {
        return "\xFF\xF3\x80\xC4".str_repeat("\0", 204);
    }

    private static function id3v2(int $size): string
    {
        return "ID3\x03\x00\x00".pack('C4', ($size >> 21) & 0x7F, ($size >> 14) & 0x7F, ($size >> 7) & 0x7F, $size & 0x7F).str_repeat("\0", $size);
    }

    public function testMp3WithConstantBitRate(): void
    {
        // 3000 frames of 1152 samples at 44100 Hz = 78.37 s
        $path = $this->file(str_repeat(self::frameV1(), 3000), 'mp3');

        $this->assertSame(78, AudioDuration::forFile($path));
        $this->assertSame(78.0, (new MP3File($path))->getDurationEstimate());
    }

    public function testMp3LayerThreeOfMpegTwoHasHalfTheSamplesPerFrame(): void
    {
        // 3000 frames of 576 samples at 22050 Hz = 78.37 s (the frames are 208 bytes, not 417)
        $this->assertSame(78, AudioDuration::forFile($this->file(str_repeat(self::frameV2(), 3000), 'mp3')));
    }

    public function testTheTagsAreIgnored(): void
    {
        $audio = str_repeat(self::frameV1(), 3000);
        $path = $this->file(self::id3v2(20000).$audio.'TAG'.str_repeat("\0", 125), 'mp3');

        $this->assertSame(78, AudioDuration::forFile($path));
    }

    public function testTheXingHeaderGivesTheNumberOfFrames(): void
    {
        // A Xing frame (MPEG 1 stereo: the header is 32 bytes after the frame header) announcing 5000 frames,
        // and only a few real ones: the announced number is used (5000 * 1152 / 44100 = 130.6)
        $xing = "\xFF\xFB\x90\x00".str_repeat("\0", 32).'Xing'.pack('N', 1).pack('N', 5000);
        $xing .= str_repeat("\0", 417 - \strlen($xing));
        $path = $this->file($xing.str_repeat(self::frameV1(), 10), 'mp3');

        $this->assertSame(131, AudioDuration::forFile($path));
    }

    public function testTheVbriHeaderGivesTheNumberOfFrames(): void
    {
        $vbri = "\xFF\xFB\x90\x00".str_repeat("\0", 32).'VBRI'.str_repeat("\0", 10).pack('N', 5000);
        $vbri .= str_repeat("\0", 417 - \strlen($vbri));
        $path = $this->file($vbri.str_repeat(self::frameV1(), 10), 'mp3');

        $this->assertSame(131, AudioDuration::forFile($path));
    }

    public function testADamagedPartIsSkipped(): void
    {
        // 1500 frames, 5000 bytes of garbage (with a byte that looks like a frame header), 1500 frames
        $garbage = str_repeat("\0", 2000)."\xFF\xFB\x90\x00".str_repeat("\x12", 3000);
        $path = $this->file(str_repeat(self::frameV1(), 1500).$garbage.str_repeat(self::frameV1(), 1500), 'mp3');

        $this->assertSame(78, AudioDuration::forFile($path));
    }

    public function testNoAudioMeansNoDuration(): void
    {
        $this->assertSame(0, AudioDuration::forFile($this->file('', 'mp3')));
        $this->assertSame(0, AudioDuration::forFile($this->file(random_bytes(5000), 'mp3')));
        // Nothing but garbage for longer than the limit: given up, quickly
        $start = microtime(true);
        $this->assertSame(0, AudioDuration::forFile($this->file(str_repeat("\x12", 3 * 1048576), 'mp3')));
        $this->assertLessThan(5, microtime(true) - $start);
        $this->assertSame(0, AudioDuration::forFile('/does/not/exist.mp3'));
        $this->assertSame(0, AudioDuration::forFile($this->file('some text', 'txt')));
    }

    public function testFrameHeaders(): void
    {
        $info = MP3File::parseFrameHeader("\xFF\xFB\x90\x00");
        $this->assertSame(['1', 3, 128, 44100, 417, 1152], [$info['Version'], $info['Layer'], $info['Bitrate'], $info['Sampling Rate'], $info['Framesize'], $info['Samples']]);

        $info = MP3File::parseFrameHeader("\xFF\xFD\x90\x00"); // MPEG 1 Layer II, index 9: 160 kbps
        $this->assertSame([2, 160, intdiv(144 * 160000, 44100), 1152], [$info['Layer'], $info['Bitrate'], $info['Framesize'], $info['Samples']]);

        $info = MP3File::parseFrameHeader("\xFF\xFF\x90\x00"); // MPEG 1 Layer I, 288 kbps at 44.1 kHz: 12 * 288000 / 44100 * 4
        $this->assertSame([1, 384, 4 * intdiv(12 * 288000, 44100)], [$info['Layer'], $info['Samples'], $info['Framesize']]);

        foreach (["\xFF\xFB\x00\x00" /* free bit rate */, "\xFF\xFB\xF0\x00" /* bad bit rate */, "\xFF\xFB\x9C\x00" /* reserved rate */, "\xFF\xE9\x90\x00" /* reserved layer */, "\xFF\xEB\x90\x00" /* reserved version */, "\xFE\xFB\x90\x00", 'ab'] as $invalid) {
            $this->assertSame([], MP3File::parseFrameHeader($invalid));
        }
    }

    public function testWav(): void
    {
        $fmt = pack('vvVVvv', 1, 2, 44100, 176400, 4, 16);
        $header = 'RIFF'.pack('V', 36 + 1764000).'WAVE'.'fmt '.pack('V', 16).$fmt.'data'.pack('V', 1764000);

        // 1764000 bytes at 176400 bytes per second = 10 s
        $this->assertSame(10, AudioDuration::forFile($this->file($header.str_repeat("\0", 1764000), 'wav')));

        // Streamed file: the size of the data is not known
        $streamed = 'RIFF'.pack('V', 0xFFFFFFFF).'WAVE'.'fmt '.pack('V', 16).$fmt.'LIST'.pack('V', 5).'abcde'."\0".'data'.pack('V', 0xFFFFFFFF);
        $this->assertSame(5, AudioDuration::forFile($this->file($streamed.str_repeat("\0", 882000), 'wav')));

        $this->assertSame(0, AudioDuration::forFile($this->file('RIFF....WAVEjunk', 'wav')));
    }

    private static function oggPage(string $packet, int $granule, int $type = 0): string
    {
        return 'OggS'."\0".\chr($type).pack('P', $granule).pack('V3', 1, 0, 0).\chr(1).\chr(\strlen($packet)).$packet;
    }

    public function testOggVorbis(): void
    {
        $ident = "\x01vorbis".pack('V', 0).\chr(2).pack('V', 44100).str_repeat("\0", 12);
        $content = self::oggPage($ident, 0, 2).str_repeat("\0", 100000).self::oggPage('data', 441000 + 44100 * 2);

        $this->assertSame(12, AudioDuration::forFile($this->file($content, 'ogg')));
    }

    public function testOggOpus(): void
    {
        $head = 'OpusHead'.\chr(1).\chr(2).pack('v', 312).pack('V', 44100).pack('v', 0).\chr(0);
        $content = self::oggPage($head, 0, 2).str_repeat("\0", 100000).self::oggPage('data', 312 + 48000 * 60);

        $this->assertSame(60, AudioDuration::forFile($this->file($content, 'opus')));
        $this->assertSame(0, AudioDuration::forFile($this->file('OggS not a real file', 'ogg')));
    }
}
