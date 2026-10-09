<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;

class HistorialControllerTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
    }

    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/historial');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testMonthListsOnlyDaysWithAudiosOrSummary(): void
    {
        $this->createAudio('2019-05-10 08:00:00', content: 'uno');
        $this->createAudio('2019-05-10 12:00:00', content: 'dos');
        $this->createAudio('2019-05-10 18:00:00');
        $this->createSummary('2019-05-10');
        $this->createSummary('2019-05-12');
        $this->createAudio('2019-05-20 12:00:00');
        $this->createAudio('2019-06-01 12:00:00');

        $this->api('GET', '/api/v1/historial?month=2019-05', $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'month' => '2019-05',
            'previous_month' => '2019-04',
            'next_month' => '2019-06',
            'days' => [
                ['date' => '2019-05-10', 'audio_count' => 3, 'has_summary' => true],
                ['date' => '2019-05-12', 'audio_count' => 0, 'has_summary' => true],
                ['date' => '2019-05-20', 'audio_count' => 1, 'has_summary' => false],
            ],
        ], $this->json());
    }

    public function testAdjacentMonthsCrossTheYear(): void
    {
        $this->api('GET', '/api/v1/historial?month=2018-01', $this->token);

        $json = $this->json();
        self::assertSame('2017-12', $json['previous_month']);
        self::assertSame('2018-02', $json['next_month']);
        self::assertSame([], $json['days']);
    }

    public function testDefaultsToTheCurrentMonth(): void
    {
        $this->api('GET', '/api/v1/historial', $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(DateRange::nowInMadrid()->format('Y-m'), $this->json()['month']);
    }

    public function testInvalidMonthIsRejected(): void
    {
        foreach (['2026-13', 'octubre', '2026-10-05'] as $month) {
            $this->api('GET', '/api/v1/historial?month='.$month, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }

    public function testDayReturnsItsAudiosAndSummary(): void
    {
        $first = $this->createAudio('2019-05-10 08:00:00', content: 'uno');
        $second = $this->createAudio('2019-05-10 12:00:00', AudioRecordingStatus::ERROR);
        $otherDay = $this->createAudio('2019-05-11 12:00:00', content: 'otro día');
        $summary = $this->createSummary('2019-05-10', 'Resumen del 10');

        $this->api('GET', '/api/v1/historial/2019-05-10', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['date', 'entries', 'summary'], array_keys($json));
        self::assertSame('2019-05-10', $json['date']);
        self::assertSame([$first->getId(), $second->getId()], $this->ids($json['entries']));
        self::assertNotContains($otherDay->getId(), $this->ids($json['entries']));
        self::assertSame('uno', $json['entries'][0]['transcription']['content']);
        self::assertSame($summary->getId(), $json['summary']['id']);
        self::assertSame('Resumen del 10', $json['summary']['summary_text']);
    }

    public function testDayStatusFilter(): void
    {
        $this->createAudio('2019-05-10 08:00:00', content: 'uno');
        $failed = $this->createAudio('2019-05-10 12:00:00', AudioRecordingStatus::ERROR);

        $this->api('GET', '/api/v1/historial/2019-05-10?status=ERROR', $this->token);

        self::assertSame([$failed->getId()], $this->ids($this->json()['entries']));
    }

    public function testDayWithNothingIsAnEmptyDayNotAnError(): void
    {
        $this->api('GET', '/api/v1/historial/2018-01-01', $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['date' => '2018-01-01', 'entries' => [], 'summary' => null], $this->json());
    }

    public function testImpossibleDateIsRejected(): void
    {
        $this->api('GET', '/api/v1/historial/2026-02-30', $this->token);

        $this->assertApiError(422, 'validation_failed');
    }

    public function testDayWithUnknownStatusIsRejected(): void
    {
        $this->api('GET', '/api/v1/historial/2019-05-10?status=HECHO', $this->token);

        $this->assertApiError(422, 'validation_failed');
    }

    public function testSomethingThatIsNotADateIsNotFound(): void
    {
        $this->api('GET', '/api/v1/historial/ayer', $this->token);

        $this->assertApiError(404, 'not_found');
    }
}
