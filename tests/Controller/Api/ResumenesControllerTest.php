<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

class ResumenesControllerTest extends ApiTestCase
{
    private const RANGE = '?from=2019-03-01&to=2019-03-31';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
    }

    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/resumenes');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testRangeIsListedFromNewestToOldestWithAudioCounts(): void
    {
        $this->createSummary('2019-03-01', 'Primero');
        $this->createSummary('2019-03-02', 'Segundo', [$this->unique('trabajo')]);
        $this->createSummary('2019-03-03', 'Tercero');
        $this->createSummary('2019-04-01', 'Fuera del rango');
        $this->createAudio('2019-03-02 09:00:00', content: 'a');
        $this->createAudio('2019-03-02 15:00:00', content: 'b');

        $this->api('GET', '/api/v1/resumenes'.self::RANGE, $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['items', 'page', 'per_page', 'total'], array_keys($json));
        self::assertSame(1, $json['page']);
        self::assertSame(20, $json['per_page']);
        self::assertSame(3, $json['total']);
        self::assertSame(['2019-03-03', '2019-03-02', '2019-03-01'], array_column($json['items'], 'date'));
        self::assertSame([0, 2, 0], array_column($json['items'], 'audio_count'));
        self::assertSame(
            ['id', 'date', 'summary_text', 'generated_at', 'emoji_legend', 'topics', 'model', 'prompt_tokens', 'completion_tokens', 'generation_ms', 'audio_count'],
            array_keys($json['items'][1]),
        );
        self::assertSame('Segundo', $json['items'][1]['summary_text']);
        self::assertCount(1, $json['items'][1]['topics']);
        self::assertSame(100, $json['items'][1]['prompt_tokens']);
    }

    public function testPagination(): void
    {
        $this->createSummary('2019-03-01');
        $this->createSummary('2019-03-02');
        $this->createSummary('2019-03-03');

        $this->api('GET', '/api/v1/resumenes'.self::RANGE.'&per_page=2&page=2', $this->token);

        $json = $this->json();
        self::assertSame(['2019-03-01'], array_column($json['items'], 'date'));
        self::assertSame(2, $json['page']);
        self::assertSame(2, $json['per_page']);
        self::assertSame(3, $json['total']);
    }

    public function testPageBeyondTheLastIsEmptyWithTheRealTotal(): void
    {
        $this->createSummary('2019-03-01');
        $this->createSummary('2019-03-02');
        $this->createSummary('2019-03-03');

        $this->api('GET', '/api/v1/resumenes'.self::RANGE.'&page=999', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame([], $json['items']);
        self::assertSame(3, $json['total']);
    }

    public function testWithoutRangeListsEverythingNewestFirst(): void
    {
        $this->createSummary('2019-03-01');
        $this->createSummary('2019-03-02');

        $this->api('GET', '/api/v1/resumenes?per_page=100', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertGreaterThanOrEqual(2, $json['total']);
        $dates = array_column($json['items'], 'date');
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates);
    }

    public function testInvalidPaginationIsRejected(): void
    {
        foreach (['per_page=500', 'per_page=0', 'page=0', 'page=dos', 'per_page[]=5'] as $query) {
            $this->api('GET', '/api/v1/resumenes?'.$query, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }

    public function testIncompleteInvertedOrMalformedRangeIsRejected(): void
    {
        foreach (['from=2019-03-07', 'to=2019-03-07', 'from=2019-03-07&to=2019-03-01', 'from=2019-02-30&to=2019-03-01', 'from=ayer&to=hoy'] as $query) {
            $this->api('GET', '/api/v1/resumenes?'.$query, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }
}
