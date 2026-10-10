<?php

declare(strict_types=1);

namespace WEM\AudioTracksBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use WEM\AudioTracksBundle\Classes\Mp3Compressor;
use WEM\AudioTracksBundle\Util\AudioDuration;
use WEM\AudioTracksBundle\Util\MP3File;

class Mp3CompressorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/audiotracks-compressor-'.uniqid();
        (new Filesystem())->mkdir($this->dir.'/files');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testCompressesAHighBitRateStereoFile(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('big.mp3', 192, 2);
        $before = filesize($path);

        $this->assertTrue($this->compressor()->needsCompression($path));
        $result = $this->compressor()->compress($path);

        $this->assertNotNull($result);
        $this->assertSame($before, $result['before']);
        $this->assertSame(filesize($path), $result['after']);
        $this->assertLessThan($before / 2, $result['after']);

        $frame = (new MP3File($path))->getFirstFrame();
        $this->assertSame(1, $frame['Channels']);
        $this->assertEqualsWithDelta(6, AudioDuration::forFile($path), 1);

        // Nothing is left beside the file, and a second pass has nothing to do
        $this->assertSame(['big.mp3'], array_values(array_diff(scandir($this->dir.'/files'), ['.', '..'])));
        $this->assertFalse($this->compressor()->needsCompression($path));
        $this->assertNull($this->compressor()->compress($path));
    }

    public function testKeepsTheOriginalOutsideOfThePublicDirectory(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('big.mp3', 192, 2);
        $before = filesize($path);

        $this->compressor()->compress($path);

        $original = $this->dir.'/var/audiotracks-originals/files/big.mp3';
        $this->assertSame($original, $this->compressor()->originalPath($path));
        $this->assertFileExists($original);
        $this->assertSame($before, filesize($original));
    }

    public function testDoesNotKeepTheOriginalIfNotAsked(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('big.mp3', 192, 2);

        $this->compressor('ffmpeg', 64, 1, false)->compress($path);

        $this->assertDirectoryDoesNotExist($this->dir.'/var');
    }

    public function testAFileAlreadyAtTheTargetIsLeftAlone(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('small.mp3', 64, 1);
        $hash = md5_file($path);

        $this->assertFalse($this->compressor()->needsCompression($path));
        $this->assertNull($this->compressor()->compress($path));
        $this->assertSame($hash, md5_file($path));
    }

    public function testAStereoFileUnderTheBitRateIsStillConvertedToMono(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('stereo.mp3', 48, 2);

        $this->assertTrue($this->compressor()->needsCompression($path));
    }

    public function testStereoIsKeptWhenTheTargetIsStereo(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('stereo.mp3', 128, 2);

        $this->assertFalse($this->compressor('ffmpeg', 128, 2)->needsCompression($path));
    }

    public function testOtherFilesAndMissingFilesAreIgnored(): void
    {
        file_put_contents($this->dir.'/files/a.wav', 'RIFF');

        $this->assertFalse($this->compressor()->needsCompression($this->dir.'/files/a.wav'));
        $this->assertFalse($this->compressor()->needsCompression($this->dir.'/files/missing.mp3'));
        $this->assertFalse($this->compressor()->needsCompression(__FILE__));
    }

    public function testAMissingBinaryFailsAndLeavesTheOriginalUntouched(): void
    {
        $this->requireFfmpeg();
        $path = $this->encode('big.mp3', 192, 2);
        $hash = md5_file($path);
        $compressor = $this->compressor('/nonexistent/ffmpeg');

        $this->assertFalse($compressor->isAvailable());

        try {
            $compressor->compress($path);
            $this->fail('An exception was expected');
        } catch (\RuntimeException $runtimeException) {
            $this->assertStringContainsString('ffmpeg', $runtimeException->getMessage());
        }

        $this->assertSame($hash, md5_file($path));
        $this->assertSame(['big.mp3'], array_values(array_diff(scandir($this->dir.'/files'), ['.', '..'])));
    }

    private function compressor(string $ffmpeg = 'ffmpeg', int $bitrate = 64, int $channels = 1, bool $keep = true): Mp3Compressor
    {
        return new Mp3Compressor($this->dir, $ffmpeg, $bitrate, $channels, $keep, 60);
    }

    private function encode(string $name, int $kbps, int $channels, int $seconds = 6): string
    {
        $path = $this->dir.'/files/'.$name;
        $process = new Process(['ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration='.$seconds, '-ac', (string) $channels, '-c:a', 'libmp3lame', '-b:a', $kbps.'k', '-y', $path]);
        $process->run();

        return $path;
    }

    private function requireFfmpeg(): void
    {
        if (!$this->compressor()->isAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed');
        }
    }
}
