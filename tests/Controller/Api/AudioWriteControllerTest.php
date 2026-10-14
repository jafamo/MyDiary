<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Message\TranscribeAudioMessage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AudioWriteControllerTest extends ApiTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function testEditTranscription(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioRecording = $this->createTranscribedAudioWithExportFile('texto original');
        $embedding = array_fill(0, 768, 0.0);
        $embedding[0] = 1.0;
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse(json_encode(['embedding' => $embedding], \JSON_THROW_ON_ERROR))));

        $this->api('PATCH', sprintf('/api/v1/audios/%d/transcripcion', $audioRecording->getId()), $token, ['content' => 'Texto corregido']);

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertSame($audioRecording->getId(), $json['id']);
        self::assertSame('Texto corregido', $json['transcription']['content']);
        self::assertTrue($json['transcription']['edited_manually']);

        $this->entityManager->clear();
        $transcription = $this->entityManager->find(AudioRecording::class, $audioRecording->getId())?->getTranscription();
        self::assertNotNull($transcription);
        self::assertSame('Texto corregido', $transcription->getContent());
        self::assertSame('Texto corregido', file_get_contents($transcription->getFilePath()));
    }

    public function testEditTranscriptionOfAudioNotTranscribedIsAConflict(): void
    {
        $token = $this->issueToken($this->createUser());

        foreach ([AudioRecordingStatus::PENDING, AudioRecordingStatus::ERROR] as $status) {
            $audioRecording = $this->createAudio('2026-10-05 10:00:00', $status);

            $this->api('PATCH', sprintf('/api/v1/audios/%d/transcripcion', $audioRecording->getId()), $token, ['content' => 'Texto']);

            $this->assertApiError(409, 'conflict');
        }
    }

    public function testEditTranscriptionWithBlankContentIsRejected(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioRecording = $this->createTranscribedAudioWithExportFile('texto original');

        $this->api('PATCH', sprintf('/api/v1/audios/%d/transcripcion', $audioRecording->getId()), $token, ['content' => '   ']);

        $this->assertApiError(422, 'validation_failed');
        $this->entityManager->clear();
        self::assertSame('texto original', $this->entityManager->find(Transcription::class, $audioRecording->getTranscription()?->getId())?->getContent());
    }

    public function testDeleteTranscribedAudio(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioRecording = $this->createTranscribedAudioWithExportFile('texto');
        $audioId = $audioRecording->getId();
        $transcriptionId = $audioRecording->getTranscription()?->getId();
        $exportFile = (string) $audioRecording->getTranscription()?->getFilePath();

        $this->api('DELETE', sprintf('/api/v1/audios/%d', $audioId), $token);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(AudioRecording::class, $audioId));
        self::assertNull($this->entityManager->find(Transcription::class, $transcriptionId));
        self::assertFileDoesNotExist($exportFile);
    }

    public function testDeleteAudioInError(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioId = $this->createAudio('2026-10-05 10:00:00', AudioRecordingStatus::ERROR)->getId();

        $this->api('DELETE', sprintf('/api/v1/audios/%d', $audioId), $token);

        self::assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(AudioRecording::class, $audioId));
    }

    public function testDeletePendingAudioIsAConflict(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioId = $this->createAudio('2026-10-05 10:00:00')->getId();

        $this->api('DELETE', sprintf('/api/v1/audios/%d', $audioId), $token);

        $this->assertApiError(409, 'conflict');
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(AudioRecording::class, $audioId));
    }

    public function testRetryAudioInError(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioRecording = $this->createAudio('2026-10-05 10:00:00', AudioRecordingStatus::ERROR);

        $this->api('POST', sprintf('/api/v1/audios/%d/reintentar', $audioRecording->getId()), $token);

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertSame('PENDING', $json['status']);
        self::assertNull($json['error_code']);
        self::assertNull($json['error_message']);
        self::assertSame([$audioRecording->getId()], $this->dispatchedAudioIds());
    }

    public function testRetryAudioNotInErrorIsAConflict(): void
    {
        $token = $this->issueToken($this->createUser());
        $audioRecording = $this->createAudio('2026-10-05 10:00:00', content: 'Texto');

        $this->api('POST', sprintf('/api/v1/audios/%d/reintentar', $audioRecording->getId()), $token);

        $this->assertApiError(409, 'conflict');
        self::assertSame([], $this->dispatchedAudioIds());
    }

    public function testUnknownAudioIsNotFound(): void
    {
        $token = $this->issueToken($this->createUser());

        foreach ([['PATCH', '/api/v1/audios/999999999/transcripcion', ['content' => 'x']], ['DELETE', '/api/v1/audios/999999999', null], ['POST', '/api/v1/audios/999999999/reintentar', null]] as [$method, $uri, $body]) {
            $this->api($method, $uri, $token, $body);

            $this->assertApiError(404, 'not_found');
        }
    }

    public function testWritesRequireToken(): void
    {
        $audioId = $this->createAudio('2026-10-05 10:00:00', AudioRecordingStatus::ERROR)->getId();

        foreach ([['PATCH', '/api/v1/audios/%d/transcripcion'], ['DELETE', '/api/v1/audios/%d'], ['POST', '/api/v1/audios/%d/reintentar']] as [$method, $uri]) {
            $this->api($method, sprintf($uri, $audioId));

            $this->assertApiError(401, 'unauthorized');
        }
        $this->entityManager->clear();
        self::assertSame(AudioRecordingStatus::ERROR, $this->entityManager->find(AudioRecording::class, $audioId)?->getStatus());
    }

    /**
     * Audio transcrito cuyo fichero de export está en un directorio temporal, para que la edición pueda reescribirlo.
     */
    private function createTranscribedAudioWithExportFile(string $content): AudioRecording
    {
        $audioRecording = $this->createAudio('2026-10-05 10:00:00', content: $content);
        $exportFile = sys_get_temp_dir().'/'.uniqid('api-transcription-', true).'.txt';
        file_put_contents($exportFile, $content);
        $this->tempFiles[] = $exportFile;
        $audioRecording->getTranscription()?->setFilePath($exportFile);
        $this->entityManager->flush();

        return $audioRecording;
    }

    /**
     * @return list<int>
     */
    private function dispatchedAudioIds(): array
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
}
