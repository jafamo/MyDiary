<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Reminder;
use App\Service\DateRange;

class RecordatoriosControllerTest extends ApiTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());

        // El aviso de próximos cuenta todos los recordatorios cercanos: se parte de una ventana vacía.
        $this->entityManager->createQuery('DELETE FROM '.Reminder::class.' r WHERE r.date >= :from')
            ->setParameter('from', new \DateTimeImmutable($this->day('-1 day')))
            ->execute();
    }

    public function testRequiresToken(): void
    {
        foreach (['/api/v1/recordatorios', '/api/v1/recordatorios/proximos'] as $uri) {
            $this->api('GET', $uri);

            $this->assertApiError(401, 'unauthorized');
        }
    }

    public function testUpcomingIsTheDefaultScope(): void
    {
        $yesterday = $this->createReminder($this->day('-1 day'), 'Ayer');
        $today = $this->createReminder($this->day('today'), 'Hoy', '09:30');
        $tomorrow = $this->createReminder($this->day('+1 day'), 'Mañana');

        $this->api('GET', '/api/v1/recordatorios', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['items', 'page', 'per_page', 'total'], array_keys($json));
        self::assertSame([$today->getId(), $tomorrow->getId()], $this->ids($json['items']));
        self::assertNotContains($yesterday->getId(), $this->ids($json['items']));
        self::assertSame(2, $json['total']);
        self::assertSame(['id', 'date', 'time', 'text', 'created_at', 'updated_at'], array_keys($json['items'][0]));
        self::assertSame($this->day('today'), $json['items'][0]['date']);
        self::assertSame('09:30', $json['items'][0]['time']);
        self::assertSame('Hoy', $json['items'][0]['text']);
        self::assertNull($json['items'][1]['time']);
    }

    public function testHistoryListsPastRemindersNewestFirst(): void
    {
        $old = $this->createReminder('2019-07-01', 'Antiguo');
        $yesterday = $this->createReminder($this->day('-1 day'), 'Ayer');
        $tomorrow = $this->createReminder($this->day('+1 day'), 'Mañana');

        $this->api('GET', '/api/v1/recordatorios?scope=history&per_page=100', $this->token);

        $ids = $this->ids($this->json()['items']);
        self::assertSame($yesterday->getId(), $ids[0]);
        self::assertContains($old->getId(), $ids);
        self::assertNotContains($tomorrow->getId(), $ids);
    }

    public function testMonthFilter(): void
    {
        $second = $this->createReminder('2019-07-20', 'Segundo');
        $first = $this->createReminder('2019-07-05', 'Primero');
        $this->createReminder('2019-08-01', 'Agosto');

        $this->api('GET', '/api/v1/recordatorios?month=2019-07', $this->token);

        $json = $this->json();
        self::assertSame([$first->getId(), $second->getId()], $this->ids($json['items']));
        self::assertSame(2, $json['total']);
    }

    public function testMonthFilterIsPaginated(): void
    {
        $this->createReminder('2019-07-05', 'Primero');
        $second = $this->createReminder('2019-07-20', 'Segundo');

        $this->api('GET', '/api/v1/recordatorios?month=2019-07&per_page=1&page=2', $this->token);

        $json = $this->json();
        self::assertSame([$second->getId()], $this->ids($json['items']));
        self::assertSame(2, $json['total']);
        self::assertSame(2, $json['page']);
        self::assertSame(1, $json['per_page']);
    }

    public function testDateFilter(): void
    {
        $this->createReminder('2019-07-05', 'Otro día');
        $reminder = $this->createReminder('2019-07-06', 'Ese día');

        $this->api('GET', '/api/v1/recordatorios?date=2019-07-06', $this->token);

        $json = $this->json();
        self::assertSame([$reminder->getId()], $this->ids($json['items']));
        self::assertSame(1, $json['total']);
    }

    public function testInvalidOrCombinedFiltersAreRejected(): void
    {
        $queries = [
            'scope=history&month=2019-07',
            'month=2019-07&date=2019-07-05',
            'scope=upcoming&date=2019-07-05',
            'scope=todos',
            'month=2019-13',
            'date=2019-02-30',
            'per_page=101',
        ];

        foreach ($queries as $query) {
            $this->api('GET', '/api/v1/recordatorios?'.$query, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }

    public function testUpcomingAlertIsUrgentWhenTheNearestIsTomorrow(): void
    {
        $first = $this->createReminder($this->day('+1 day'), 'Mañana uno');
        $second = $this->createReminder($this->day('+1 day'), 'Mañana dos');
        $this->createReminder($this->day('+4 days'), 'En cuatro días');
        $this->createReminder($this->day('+9 days'), 'Fuera de la ventana');

        $this->api('GET', '/api/v1/recordatorios/proximos', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['count', 'level', 'nearest_date', 'reminders'], array_keys($json));
        self::assertSame(3, $json['count']);
        self::assertSame('urgent', $json['level']);
        self::assertSame($this->day('+1 day'), $json['nearest_date']);
        self::assertSame([$first->getId(), $second->getId()], $this->ids($json['reminders']));
    }

    public function testUpcomingAlertIsNotUrgentWhenTheNearestIsDaysAway(): void
    {
        $reminder = $this->createReminder($this->day('+3 days'), 'En tres días');

        $this->api('GET', '/api/v1/recordatorios/proximos', $this->token);

        $json = $this->json();
        self::assertSame(1, $json['count']);
        self::assertSame('upcoming', $json['level']);
        self::assertSame($this->day('+3 days'), $json['nearest_date']);
        self::assertSame([$reminder->getId()], $this->ids($json['reminders']));
    }

    public function testUpcomingAlertWithoutNearReminders(): void
    {
        $this->createReminder($this->day('+9 days'), 'Lejano');

        $this->api('GET', '/api/v1/recordatorios/proximos', $this->token);

        self::assertSame(['count' => 0, 'level' => null, 'nearest_date' => null, 'reminders' => []], $this->json());
    }

    private function day(string $modifier): string
    {
        return DateRange::nowInMadrid()->modify($modifier)->format('Y-m-d');
    }
}
