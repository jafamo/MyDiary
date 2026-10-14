<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecording;
use Monolog\Handler\TestHandler;

class AudioFileControllerTest extends ApiTestCase
{
    private const CONTENT = 'OggS0123456789abcdefghijklmnopqrstuvwxyz';

    private string $token;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function testFileRequiresToken(): void
    {
        $audioRecording = $this->audioWithFile();

        $this->api('GET', $this->uri($audioRecording));

        $this->assertApiError(401, 'unauthorized');
    }

    public function testWholeFileIsServed(): void
    {
        $audioRecording = $this->audioWithFile();

        $this->api('GET', $this->uri($audioRecording), $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'audio/ogg');
        self::assertResponseHeaderSame('Accept-Ranges', 'bytes');
        self::assertResponseHeaderSame('Content-Length', (string) \strlen(self::CONTENT));
        self::assertSame(self::CONTENT, $this->content());
    }

    public function testRangeReturnsPartialContent(): void
    {
        $audioRecording = $this->audioWithFile();

        $this->client->request('GET', $this->uri($audioRecording), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'HTTP_RANGE' => 'bytes=0-9',
        ]);

        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Range', sprintf('bytes 0-9/%d', \strlen(self::CONTENT)));
        self::assertSame(substr(self::CONTENT, 0, 10), $this->content());
    }

    public function testUnknownAudioIsNotFound(): void
    {
        $this->api('GET', '/api/v1/audios/999999999/fichero', $this->token);

        $this->assertApiError(404, 'not_found');
    }

    public function testMissingFileIsNotFoundAndLogged(): void
    {
        $audioRecording = $this->createAudio('2026-10-10 08:00:00');

        $this->api('GET', $this->uri($audioRecording), $this->token);

        $this->assertApiError(404, 'not_found');
        self::assertStringNotContainsString('/data/audio', (string) $this->client->getResponse()->getContent());

        /** @var TestHandler $logHandler */
        // Handler "test" definido solo en when@test (PHPStan analiza el contenedor de dev)
        $logHandler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $records = array_values(array_filter(
            $logHandler->getRecords(),
            static fn ($record): bool => 'audio.file_missing' === ($record->context['event'] ?? null),
        ));
        self::assertCount(1, $records);
        self::assertSame($audioRecording->getId(), $records[0]->context['audio_recording_id']);
        self::assertSame('app', $records[0]->context['audio_source']);
    }

    private function audioWithFile(): AudioRecording
    {
        $path = sprintf('%s/api-audio-file-%s.ogg', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        file_put_contents($path, self::CONTENT);
        $this->tempFiles[] = $path;

        $audioRecording = $this->createAudio('2026-10-10 08:00:00');
        $audioRecording->setFilePath($path);
        $this->entityManager->flush();

        return $audioRecording;
    }

    private function uri(AudioRecording $audioRecording): string
    {
        return sprintf('/api/v1/audios/%d/fichero', $audioRecording->getId());
    }

    /**
     * BinaryFileResponse envía el fichero directamente: se captura su salida.
     */
    private function content(): string
    {
        return (string) $this->client->getInternalResponse()->getContent();
    }
}
