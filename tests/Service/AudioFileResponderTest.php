<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioSource;
use App\Service\AudioFileMissingException;
use App\Service\AudioFileResponder;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AudioFileResponderTest extends TestCase
{
    private TestHandler $logHandler;
    private AudioFileResponder $responder;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->logHandler = new TestHandler();
        $this->responder = new AudioFileResponder(new Logger('test', [$this->logHandler]));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function extensions(): iterable
    {
        yield 'ogg' => ['ogg', 'audio/ogg'];
        yield 'm4a' => ['m4a', 'audio/mp4'];
        yield 'mp3' => ['mp3', 'audio/mpeg'];
        yield 'wav' => ['wav', 'audio/wav'];
        yield 'mayúsculas' => ['OGG', 'audio/ogg'];
        yield 'desconocida' => ['bin', 'application/octet-stream'];
    }

    #[DataProvider('extensions')]
    public function testContentTypeFollowsTheExtension(string $extension, string $contentType): void
    {
        $response = $this->responder->respond($this->audio($this->file($extension)));

        self::assertSame($contentType, $response->headers->get('Content-Type'));
    }

    public function testResponseIsInlinePrivateAndHidesTheStorageKey(): void
    {
        $path = $this->file('ogg');
        $response = $this->responder->respond($this->audio($path, 42));

        self::assertSame($path, $response->getFile()->getPathname());
        self::assertSame('inline; filename=audio-42.ogg', $response->headers->get('Content-Disposition'));
        self::assertStringNotContainsString(basename($path, '.ogg'), (string) $response->headers->get('Content-Disposition'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertSame(86400, $response->getMaxAge());
        self::assertNotNull($response->getLastModified());
    }

    public function testMissingFileThrowsAndIsLogged(): void
    {
        $audioRecording = $this->audio('/data/audio/no-existe.ogg', 7)->setSource(AudioSource::TELEGRAM);

        try {
            $this->responder->respond($audioRecording);
            self::fail('Se esperaba AudioFileMissingException.');
        } catch (AudioFileMissingException) {
        }

        $records = $this->logHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('WARNING', $records[0]->level->getName());
        self::assertSame([
            'event' => 'audio.file_missing',
            'audio_recording_id' => 7,
            'audio_source' => 'telegram',
        ], $records[0]->context);
    }

    private function file(string $extension): string
    {
        $path = sprintf('%s/audio-responder-%s.%s', sys_get_temp_dir(), bin2hex(random_bytes(6)), $extension);
        file_put_contents($path, 'contenido de prueba');
        $this->tempFiles[] = $path;

        return $path;
    }

    private function audio(string $path, int $id = 1): AudioRecording
    {
        $audioRecording = (new AudioRecording())->setSource(AudioSource::APP)->setFilePath($path);
        (new \ReflectionProperty(AudioRecording::class, 'id'))->setValue($audioRecording, $id);

        return $audioRecording;
    }
}
