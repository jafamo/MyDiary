<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecording;
use App\Entity\DailySummaryGeneration;
use App\Message\GenerateDailySummaryMessage;
use App\Repository\DailySummaryGenerationRepository;
use App\LocalTimezone;
use App\Service\DateRange;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class DiarioSummaryRequestTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->removeTodaysGeneration();
    }

    protected function tearDown(): void
    {
        $this->removeTodaysGeneration();
        parent::tearDown();
    }

    public function testRequestIsAccepted(): void
    {
        $token = $this->issueToken($this->createUser());
        $this->createAudio(gmdate('Y-m-d H:i:s'), content: 'Texto de hoy');

        $this->api('POST', '/api/v1/diario/resumen', $token);

        self::assertResponseStatusCodeSame(202);
        $json = $this->json();
        self::assertSame(['status', 'requested_at', 'started_at', 'finished_at', 'error_code', 'error_message'], array_keys($json));
        self::assertSame('QUEUED', $json['status']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $json['requested_at']);
        self::assertNull($json['started_at']);
        self::assertSame([DateRange::nowInMadrid()->format('Y-m-d')], $this->dispatchedDates());

        $this->api('GET', '/api/v1/diario', $token);
        self::assertSame('QUEUED', $this->json()['summary_generation']['status']);
    }

    public function testRepeatedRequestReturnsTheSameGeneration(): void
    {
        $token = $this->issueToken($this->createUser());
        $this->createAudio(gmdate('Y-m-d H:i:s'), content: 'Texto de hoy');

        $this->api('POST', '/api/v1/diario/resumen', $token);
        $first = $this->json();
        self::assertCount(1, $this->dispatchedDates());
        // El cliente de test vacía el transporte en memoria entre peticiones: lo que quede es de la segunda.
        $this->api('POST', '/api/v1/diario/resumen', $token);

        self::assertResponseStatusCodeSame(202);
        self::assertSame($first['requested_at'], $this->json()['requested_at']);
        self::assertSame([], $this->dispatchedDates());
    }

    public function testRequestWithoutTranscriptionsIsAConflict(): void
    {
        $token = $this->issueToken($this->createUser());
        $this->deleteTodaysTranscribedAudios();

        $this->api('POST', '/api/v1/diario/resumen', $token);

        $this->assertApiError(409, 'conflict');
        self::assertSame([], $this->dispatchedDates());
    }

    public function testRequestRequiresToken(): void
    {
        $this->api('POST', '/api/v1/diario/resumen');

        $this->assertApiError(401, 'unauthorized');
        self::assertSame([], $this->dispatchedDates());
    }

    public function testDiarioWithoutGenerationsHasNullState(): void
    {
        $token = $this->issueToken($this->createUser());

        $this->api('GET', '/api/v1/diario', $token);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('summary_generation', $json = $this->json());
        self::assertNull($json['summary_generation']);
    }

    public function testDiarioShowsAFailedGeneration(): void
    {
        $token = $this->issueToken($this->createUser());
        $generation = $this->todaysGeneration();
        $generation->markRunning(new \DateTimeImmutable());
        $generation->markFailed(new \DateTimeImmutable(), 'TIMEOUT', 'No se pudo contactar con Ollama.');
        $this->entityManager->flush();

        $this->api('GET', '/api/v1/diario', $token);

        $state = $this->json()['summary_generation'];
        self::assertSame('FAILED', $state['status']);
        self::assertSame('TIMEOUT', $state['error_code']);
        self::assertSame('No se pudo contactar con Ollama.', $state['error_message']);
        self::assertNotNull($state['finished_at']);
    }

    public function testDiarioShowsAStalledGenerationAsFailed(): void
    {
        $token = $this->issueToken($this->createUser());
        $this->todaysGeneration()->markQueued(new \DateTimeImmutable(sprintf('-%d minutes', DailySummaryGeneration::STALL_AFTER_MINUTES + 5)));
        $this->entityManager->flush();

        $this->api('GET', '/api/v1/diario', $token);

        $state = $this->json()['summary_generation'];
        self::assertSame('FAILED', $state['status']);
        self::assertSame('STALLED', $state['error_code']);
    }

    private function todaysGeneration(): DailySummaryGeneration
    {
        $generation = new DailySummaryGeneration(DateRange::nowInMadrid()->setTime(0, 0, 0));
        $this->entityManager->persist($generation);

        return $generation;
    }

    /**
     * Otros tests pueden dejar audios transcritos de hoy en la BD de test; este necesita un día sin ninguno.
     */
    private function deleteTodaysTranscribedAudios(): void
    {
        foreach ($this->entityManager->getRepository(AudioRecording::class)->findAll() as $audioRecording) {
            if ($audioRecording->getReceivedAt()->setTimezone(new \DateTimeZone(LocalTimezone::NAME))->format('Y-m-d') === DateRange::nowInMadrid()->format('Y-m-d')) {
                $this->entityManager->remove($audioRecording);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    private function dispatchedDates(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_values(array_map(
            static function ($envelope): string {
                $message = $envelope->getMessage();
                self::assertInstanceOf(GenerateDailySummaryMessage::class, $message);

                return $message->date;
            },
            $transport->getSent(),
        ));
    }

    private function removeTodaysGeneration(): void
    {
        $generation = self::getContainer()->get(DailySummaryGenerationRepository::class)->findOneByDate(DateRange::nowInMadrid());
        if (null !== $generation) {
            $this->entityManager->remove($generation);
            $this->entityManager->flush();
        }
    }
}
