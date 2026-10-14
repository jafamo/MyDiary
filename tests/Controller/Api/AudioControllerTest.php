<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioUploadService;
use App\Tests\Double\FakeAudioProbe;
use Monolog\Handler\TestHandler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class AudioControllerTest extends ApiTestCase
{
    private const M4A_HEADER = "\x00\x00\x00\x20ftypM4A ";
    private const OGG_HEADER = 'OggS';

    private string $token;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
        $this->removeAppAudios();
    }

    protected function tearDown(): void
    {
        $this->removeAppAudios();
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function testUploadRequiresToken(): void
    {
        $this->upload($this->audioFile(self::M4A_HEADER), token: null);

        $this->assertApiError(401, 'unauthorized');
        self::assertSame([], $this->appAudios());
    }

    public function testNewAudioIsStoredAndQueuedForTranscription(): void
    {
        $path = $this->audioFile(self::M4A_HEADER);
        $contentHash = hash_file('sha256', $path);
        // Doble definido solo en when@test (PHPStan analiza el contenedor de dev)
        self::getContainer()->get(FakeAudioProbe::class)->durationSeconds = 37; // @phpstan-ignore symfonyContainer.serviceNotFound

        $this->upload($path);

        self::assertResponseStatusCodeSame(201);
        $json = $this->json();
        self::assertSame(['id', 'status', 'source', 'duration_seconds', 'received_at', 'result'], array_keys($json));
        self::assertSame('created', $json['result']);
        self::assertSame('PENDING', $json['status']);
        self::assertSame('app', $json['source']);
        self::assertSame(37, $json['duration_seconds']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $json['received_at']);

        $audioRecording = $this->audioRecordingRepository()->find($json['id']);
        self::assertNotNull($audioRecording);
        self::assertSame(AudioSource::APP, $audioRecording->getSource());
        self::assertSame($contentHash, $audioRecording->getContentHash());
        self::assertNull($audioRecording->getTelegramMessageId());
        self::assertSame(37, $audioRecording->getDurationSeconds());
        self::assertStringEndsWith('/'.$contentHash.'.m4a', $audioRecording->getFilePath());
        self::assertFileEquals($path, $audioRecording->getFilePath());

        self::assertSame([$json['id']], $this->dispatchedAudioRecordingIds());
    }

    public function testFormatComesFromTheContentNotFromTheFileName(): void
    {
        $this->upload($this->audioFile(self::OGG_HEADER), clientName: 'nota.m4a', clientMimeType: 'audio/mp4');

        self::assertResponseStatusCodeSame(201);
        $audioRecording = $this->audioRecordingRepository()->find($this->json()['id']);
        self::assertStringEndsWith('.ogg', $audioRecording->getFilePath());
    }

    public function testSameFileTwiceIsADuplicate(): void
    {
        $path = $this->audioFile(self::M4A_HEADER);

        $this->upload($path);
        $first = $this->json();
        self::assertSame([$first['id']], $this->dispatchedAudioRecordingIds());

        $this->upload($path);

        self::assertResponseStatusCodeSame(200);
        $second = $this->json();
        self::assertSame('duplicate', $second['result']);
        self::assertSame($first['id'], $second['id']);
        self::assertCount(1, $this->appAudios());
        self::assertSame([], $this->dispatchedAudioRecordingIds(), 'La segunda subida no despacha ningún mensaje');
    }

    public function testSameFileAfterAnErrorIsRetried(): void
    {
        $path = $this->audioFile(self::M4A_HEADER);
        $this->upload($path);
        $id = $this->json()['id'];

        $audioRecording = $this->audioRecordingRepository()->find($id);
        $audioRecording
            ->setStatus(AudioRecordingStatus::ERROR)
            ->setErrorCode('TIMEOUT')
            ->setErrorMessage('algo falló')
        ;
        $this->entityManager->flush();

        $this->upload($path);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame('retrying', $json['result']);
        self::assertSame($id, $json['id']);
        self::assertSame('PENDING', $json['status']);

        $this->entityManager->clear();
        $audioRecording = $this->audioRecordingRepository()->find($id);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
        self::assertNull($audioRecording->getErrorMessage());
        self::assertSame([$id], $this->dispatchedAudioRecordingIds(), 'El reintento despacha un mensaje nuevo');
    }

    public function testMissingFileIsRejected(): void
    {
        $this->client->request('POST', '/api/v1/audios', [], [], $this->server($this->token));

        $this->assertApiError(422, 'validation_failed');
    }

    public function testIncompleteUploadIsRejected(): void
    {
        $file = new UploadedFile($this->audioFile(self::M4A_HEADER), 'nota.m4a', 'audio/mp4', \UPLOAD_ERR_PARTIAL, true);

        $this->client->request('POST', '/api/v1/audios', [], ['file' => $file], $this->server($this->token));

        $this->assertApiError(422, 'validation_failed');
        self::assertSame([], $this->appAudios());
    }

    public function testFileThatIsNotAudioIsRejectedAndNotStored(): void
    {
        $path = $this->audioFile('esto es un fichero de texto');

        $this->upload($path, clientName: 'nota.m4a', clientMimeType: 'audio/mp4');

        $this->assertApiError(422, 'validation_failed');
        self::assertSame([], $this->appAudios());
        self::assertSame([], glob($this->audioStorageDir().'/'.hash_file('sha256', $path).'.*'));
        self::assertSame([], $this->dispatchedAudioRecordingIds());
    }

    public function testFileOverTheSizeLimitIsRejected(): void
    {
        $path = $this->audioFile(self::M4A_HEADER);
        $handle = fopen($path, 'r+');
        ftruncate($handle, AudioUploadService::MAX_BYTES + 1);
        fclose($handle);

        $this->upload($path);

        $this->assertApiError(422, 'validation_failed');
        self::assertStringContainsString('25 MB', $this->json()['message']);
        self::assertSame([], $this->appAudios());
    }

    public function testUploadIsLogged(): void
    {
        $path = $this->audioFile(self::M4A_HEADER);

        // Los logs y la cola en memoria se reinician en cada petición: se comprueban tras cada una.
        $this->upload($path);
        $id = $this->json()['id'];

        $records = $this->logRecordsWithEvent('audio.uploaded');
        self::assertCount(1, $records);
        self::assertSame($id, $records[0]->context['audio_recording_id']);
        self::assertSame('app', $records[0]->context['audio_source']);
        self::assertSame(hash_file('sha256', $path), $records[0]->context['audio_content_hash']);
        self::assertSame('created', $records[0]->context['audio_upload_result']);
        self::assertArrayNotHasKey('result', $records[0]->context);
        self::assertArrayNotHasKey('status', $records[0]->context);

        $this->upload($path);

        $records = $this->logRecordsWithEvent('audio.uploaded');
        self::assertCount(1, $records);
        self::assertSame('duplicate', $records[0]->context['audio_upload_result']);
        self::assertSame($id, $records[0]->context['audio_recording_id']);
    }

    private function upload(string $path, ?string $clientName = 'nota.m4a', ?string $clientMimeType = 'audio/mp4', ?string $token = ''): void
    {
        $file = new UploadedFile($path, $clientName, $clientMimeType, null, true);

        $this->client->request('POST', '/api/v1/audios', [], ['file' => $file], $this->server('' === $token ? $this->token : $token));
    }

    /**
     * @return array<string, string>
     */
    private function server(?string $token): array
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        return $server;
    }

    /**
     * Fichero temporal con la cabecera indicada y contenido aleatorio (hash distinto en cada test).
     */
    private function audioFile(string $header): string
    {
        $path = tempnam(sys_get_temp_dir(), 'api-audio-');
        file_put_contents($path, $header.random_bytes(64));
        $this->tempFiles[] = $path;

        return $path;
    }

    private function audioRecordingRepository(): AudioRecordingRepository
    {
        return self::getContainer()->get(AudioRecordingRepository::class);
    }

    private function audioStorageDir(): string
    {
        return self::getContainer()->getParameter('app.audio_storage_dir');
    }

    /**
     * @return list<AudioRecording>
     */
    private function appAudios(): array
    {
        return $this->audioRecordingRepository()->findBy(['source' => AudioSource::APP]);
    }

    private function removeAppAudios(): void
    {
        $this->entityManager->clear();
        foreach ($this->appAudios() as $audioRecording) {
            if (file_exists($audioRecording->getFilePath())) {
                unlink($audioRecording->getFilePath());
            }
            $this->entityManager->remove($audioRecording);
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<int>
     */
    private function dispatchedAudioRecordingIds(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_map(
            static function ($envelope): int {
                $message = $envelope->getMessage();
                self::assertInstanceOf(TranscribeAudioMessage::class, $message);

                return $message->audioRecordingId;
            },
            array_values($transport->getSent()),
        );
    }

    /**
     * @return list<\Monolog\LogRecord>
     */
    private function logRecordsWithEvent(string $event): array
    {
        /** @var TestHandler $logHandler */
        // Handler "test" definido solo en when@test (PHPStan analiza el contenedor de dev)
        $logHandler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound

        return array_values(array_filter(
            $logHandler->getRecords(),
            static fn ($record) => $event === ($record->context['event'] ?? null),
        ));
    }
}
