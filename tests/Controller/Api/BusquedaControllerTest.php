<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BusquedaControllerTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
    }

    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/busqueda?q=dentista');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testMergesTranscriptionsAndSummariesByDistanceAndSearchesReminders(): void
    {
        $this->mockEmbedding(new MockResponse(json_encode(['embedding' => $this->vector([0 => 1.0])], \JSON_THROW_ON_ERROR)));

        $word = $this->unique('dentista');
        $far = $this->createSummary('2019-09-02', 'Resumen parecido', embedding: $this->vector([0 => 0.6, 1 => 0.8]));
        $near = $this->createAudio('2019-09-01 10:00:00', content: 'Cita exacta', embedding: $this->vector([0 => 1.0]));
        $reminder = $this->createReminder('2019-09-03', 'Ir al '.$word, '10:00');
        $this->createReminder('2019-09-04', 'Comprar pan');

        $this->api('GET', '/api/v1/busqueda?q='.$word, $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['query', 'results', 'reminders'], array_keys($json));
        self::assertSame($word, $json['query']);

        self::assertCount(2, $json['results']);
        [$first, $second] = $json['results'];
        self::assertSame(['type', 'distance', 'date', 'audio', 'summary'], array_keys($first));

        self::assertSame('transcription', $first['type']);
        self::assertSame('2019-09-01', $first['date']);
        self::assertSame($near->getId(), $first['audio']['id']);
        self::assertSame('Cita exacta', $first['audio']['transcription']['content']);
        self::assertNull($first['summary']);
        self::assertEqualsWithDelta(0.0, $first['distance'], 0.001);

        self::assertSame('daily_summary', $second['type']);
        self::assertSame('2019-09-02', $second['date']);
        self::assertSame($far->getId(), $second['summary']['id']);
        self::assertNull($second['audio']);
        self::assertEqualsWithDelta(0.4, $second['distance'], 0.001);

        self::assertSame([$reminder->getId()], $this->ids($json['reminders']));
        self::assertSame('10:00', $json['reminders'][0]['time']);
        self::assertStringNotContainsString('embedding', (string) $this->client->getResponse()->getContent());
    }

    public function testEmbeddingFailureStillReturnsReminders(): void
    {
        $this->mockEmbedding(new MockResponse('', ['http_code' => 500]));

        $word = $this->unique('fontanero');
        $this->createAudio('2019-09-01 10:00:00', content: 'Cita exacta', embedding: $this->vector([0 => 1.0]));
        $reminder = $this->createReminder('2019-09-03', 'Llamar al '.$word);

        $this->api('GET', '/api/v1/busqueda?q='.$word, $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame([], $json['results']);
        self::assertSame([$reminder->getId()], $this->ids($json['reminders']));
    }

    public function testMissingOrBlankQueryIsRejected(): void
    {
        foreach (['', '?q=', '?q=%20%20'] as $query) {
            $this->api('GET', '/api/v1/busqueda'.$query, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }

    private function mockEmbedding(MockResponse $response): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient($response));
    }

    /**
     * @param array<int, float> $values
     *
     * @return list<float> vector de 768 dimensiones (una embedding de nomic-embed-text) con los valores indicados
     */
    private function vector(array $values): array
    {
        $vector = array_fill(0, 768, 0.0);
        foreach ($values as $index => $value) {
            $vector[$index] = $value;
        }

        return $vector;
    }
}
