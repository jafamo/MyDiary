<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Reminder;

class RecordatoriosWriteControllerTest extends ApiTestCase
{
    /** @var list<int> */
    private array $createdIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            $reminder = $this->entityManager->find(Reminder::class, $id);
            if (null !== $reminder) {
                $this->entityManager->remove($reminder);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testCreate(): void
    {
        $token = $this->issueToken($this->createUser());
        $text = $this->unique('Dentista');

        $this->api('POST', '/api/v1/recordatorios', $token, ['date' => '2026-10-20', 'time' => '10:30', 'text' => $text]);

        self::assertResponseStatusCodeSame(201);
        $json = $this->json();
        $this->createdIds[] = $json['id'];
        self::assertSame('2026-10-20', $json['date']);
        self::assertSame('10:30', $json['time']);
        self::assertSame($text, $json['text']);
        self::assertNotNull($this->entityManager->find(Reminder::class, $json['id']));
    }

    public function testCreateWithoutTimeIsAllDay(): void
    {
        $token = $this->issueToken($this->createUser());

        $this->api('POST', '/api/v1/recordatorios', $token, ['date' => '2026-10-20', 'text' => $this->unique('Todo el día')]);

        self::assertResponseStatusCodeSame(201);
        $json = $this->json();
        $this->createdIds[] = $json['id'];
        self::assertNull($json['time']);
    }

    public function testCreateWithInvalidDataIsRejected(): void
    {
        $token = $this->issueToken($this->createUser());
        $text = $this->unique('invalido');

        foreach ([['date' => '2026-10-20', 'text' => ' '], ['text' => $text], ['date' => '2026-02-30', 'text' => $text], ['date' => '2026-10-20', 'time' => '25:00', 'text' => $text]] as $body) {
            $this->api('POST', '/api/v1/recordatorios', $token, $body);

            $this->assertApiError(422, 'validation_failed');
        }
        self::assertNull($this->entityManager->getRepository(Reminder::class)->findOneBy(['text' => $text]));
    }

    public function testCreateWithBodyThatIsNotJsonIsABadRequest(): void
    {
        $token = $this->issueToken($this->createUser());

        $this->api('POST', '/api/v1/recordatorios', $token, rawBody: '{no es json');

        $this->assertApiError(400, 'bad_request');
    }

    public function testPartialEdit(): void
    {
        $token = $this->issueToken($this->createUser());
        $reminder = $this->createReminder('2026-10-20', 'Antes', '10:30');

        $this->api('PATCH', sprintf('/api/v1/recordatorios/%d', $reminder->getId()), $token, ['text' => 'Nuevo texto']);

        self::assertResponseIsSuccessful();
        $json = $this->json();
        self::assertSame('Nuevo texto', $json['text']);
        self::assertSame('2026-10-20', $json['date']);
        self::assertSame('10:30', $json['time']);
    }

    public function testEditCanRemoveTheTime(): void
    {
        $token = $this->issueToken($this->createUser());
        $reminder = $this->createReminder('2026-10-20', 'Con hora', '10:30');

        $this->api('PATCH', sprintf('/api/v1/recordatorios/%d', $reminder->getId()), $token, ['time' => null]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['time']);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Reminder::class, $reminder->getId())?->getTime());
    }

    public function testEditWithUnknownFieldChangesNothing(): void
    {
        $token = $this->issueToken($this->createUser());
        $reminder = $this->createReminder('2026-10-20', 'Sin cambios');

        $this->api('PATCH', sprintf('/api/v1/recordatorios/%d', $reminder->getId()), $token, ['titulo' => 'x']);

        $this->assertApiError(422, 'validation_failed');
        $this->entityManager->clear();
        self::assertSame('Sin cambios', $this->entityManager->find(Reminder::class, $reminder->getId())?->getText());
    }

    public function testDelete(): void
    {
        $token = $this->issueToken($this->createUser());
        $reminderId = $this->createReminder('2026-10-20', 'Borrar')->getId();

        $this->api('DELETE', sprintf('/api/v1/recordatorios/%d', $reminderId), $token);

        self::assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Reminder::class, $reminderId));
    }

    public function testUnknownReminderIsNotFound(): void
    {
        $token = $this->issueToken($this->createUser());

        foreach ([['PATCH', ['text' => 'x']], ['DELETE', null]] as [$method, $body]) {
            $this->api($method, '/api/v1/recordatorios/999999999', $token, $body);

            $this->assertApiError(404, 'not_found');
        }
    }

    public function testWritesRequireToken(): void
    {
        $reminderId = $this->createReminder('2026-10-20', 'Protegido')->getId();

        $this->api('POST', '/api/v1/recordatorios', body: ['date' => '2026-10-20', 'text' => 'x']);
        $this->assertApiError(401, 'unauthorized');

        $this->api('PATCH', sprintf('/api/v1/recordatorios/%d', $reminderId), body: ['text' => 'x']);
        $this->assertApiError(401, 'unauthorized');

        $this->api('DELETE', sprintf('/api/v1/recordatorios/%d', $reminderId));
        $this->assertApiError(401, 'unauthorized');

        $this->entityManager->clear();
        self::assertSame('Protegido', $this->entityManager->find(Reminder::class, $reminderId)?->getText());
    }
}
