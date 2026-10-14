<?php

declare(strict_types=1);

namespace App\Tests\Service\Audio;

use App\Contract\AudioProbeException;
use App\Service\Audio\FfprobeAudioProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Prueba la implementación real contra ffprobe. Se salta donde no está instalado (GitHub CI);
 * en local corre dentro de `diary-php`.
 */
class FfprobeAudioProbeTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__.'/../../fixtures/audio';

    protected function setUp(): void
    {
        if (null === (new ExecutableFinder())->find('ffprobe')) {
            self::markTestSkipped('ffprobe no está instalado.');
        }
    }

    #[DataProvider('admittedFormats')]
    public function testAdmittedFormatsAreRecognisedWithTheirDuration(string $fixture, string $format): void
    {
        $result = (new FfprobeAudioProbe())->probe(self::FIXTURES_DIR.'/'.$fixture);

        self::assertSame($format, $result->format);
        self::assertSame(2, $result->durationSeconds);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function admittedFormats(): iterable
    {
        yield 'm4a' => ['tone.m4a', 'm4a'];
        yield 'mp3' => ['tone.mp3', 'mp3'];
        yield 'ogg' => ['tone.ogg', 'ogg'];
        yield 'wav' => ['tone.wav', 'wav'];
    }

    public function testFormatDoesNotDependOnTheExtension(): void
    {
        $path = sys_get_temp_dir().'/probe-'.bin2hex(random_bytes(4)).'.m4a';
        copy(self::FIXTURES_DIR.'/tone.ogg', $path);

        try {
            self::assertSame('ogg', (new FfprobeAudioProbe())->probe($path)->format);
        } finally {
            unlink($path);
        }
    }

    public function testTextFileIsRejected(): void
    {
        $this->expectException(AudioProbeException::class);

        (new FfprobeAudioProbe())->probe(__FILE__);
    }

    public function testAudioInANonAdmittedFormatIsRejected(): void
    {
        $this->expectException(AudioProbeException::class);

        (new FfprobeAudioProbe())->probe(self::FIXTURES_DIR.'/tone.flac');
    }

    public function testVideoIsRejected(): void
    {
        $this->expectException(AudioProbeException::class);

        (new FfprobeAudioProbe())->probe(self::FIXTURES_DIR.'/video.mp4');
    }
}
