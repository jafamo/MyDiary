<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\DailySummary;
use App\Entity\Topic;

class TopicsWriteControllerTest extends ApiTestCase
{
    public function testRename(): void
    {
        $token = $this->issueToken($this->createUser());
        $topic = $this->createTopic($this->unique('curro'));
        $newName = $this->unique('trabajo');

        $this->api('PATCH', sprintf('/api/v1/topics/%d', $topic->getId()), $token, ['name' => $newName]);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $topic->getId(), 'name' => $newName], $this->json());
        $this->entityManager->clear();
        self::assertSame($newName, $this->entityManager->find(Topic::class, $topic->getId())?->getName());
    }

    public function testRenameToANameAlreadyUsedIsAConflict(): void
    {
        $token = $this->issueToken($this->createUser());
        $topic = $this->createTopic($this->unique('curro'));
        $originalName = $topic->getName();
        $existing = $this->createTopic($this->unique('trabajo'));

        $this->api('PATCH', sprintf('/api/v1/topics/%d', $topic->getId()), $token, ['name' => strtoupper($existing->getName())]);

        $this->assertApiError(409, 'conflict');
        $this->entityManager->clear();
        self::assertSame($originalName, $this->entityManager->find(Topic::class, $topic->getId())?->getName());
    }

    public function testRenameKeepingTheSameNameWithOtherCase(): void
    {
        $token = $this->issueToken($this->createUser());
        $topic = $this->createTopic($this->unique('curro'));

        $this->api('PATCH', sprintf('/api/v1/topics/%d', $topic->getId()), $token, ['name' => strtoupper($topic->getName())]);

        self::assertResponseIsSuccessful();
    }

    public function testRenameWithInvalidNameIsRejected(): void
    {
        $token = $this->issueToken($this->createUser());
        $topic = $this->createTopic($this->unique('curro'));

        foreach ([['name' => '  '], [], ['name' => str_repeat('a', 256)]] as $body) {
            $this->api('PATCH', sprintf('/api/v1/topics/%d', $topic->getId()), $token, $body);

            $this->assertApiError(422, 'validation_failed');
        }
    }

    public function testRenameUnknownTopicIsNotFound(): void
    {
        $token = $this->issueToken($this->createUser());

        $this->api('PATCH', '/api/v1/topics/999999999', $token, ['name' => 'x']);

        $this->assertApiError(404, 'not_found');
    }

    public function testMerge(): void
    {
        $token = $this->issueToken($this->createUser());
        $originName = $this->unique('curro');
        $destinationName = $this->unique('trabajo');
        $summary = $this->createSummary('2019-09-01', 'Resumen', [$originName]);
        $originId = $this->topicByName($originName)->getId();
        $destinationId = $this->createTopic($destinationName)->getId();
        // Que la petición cargue los temas con sus resúmenes desde la BD, como en producción.
        $this->entityManager->clear();

        $this->api('POST', '/api/v1/topics/fusionar', $token, ['origins' => [$originId], 'destination' => $destinationId]);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $destinationId, 'name' => $destinationName], $this->json());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Topic::class, $originId));
        $names = array_map(static fn (Topic $topic): string => $topic->getName(), $this->entityManager->find(DailySummary::class, $summary->getId())?->getTopics()->toArray() ?? []);
        self::assertSame([$destinationName], array_values($names));
    }

    public function testMergeIntoItselfIsRejected(): void
    {
        $token = $this->issueToken($this->createUser());
        $topic = $this->createTopic($this->unique('trabajo'));

        $this->api('POST', '/api/v1/topics/fusionar', $token, ['origins' => [$topic->getId()], 'destination' => $topic->getId()]);

        $this->assertApiError(422, 'validation_failed');
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Topic::class, $topic->getId()));
    }

    public function testMergeWithUnknownTopicIsRejected(): void
    {
        $token = $this->issueToken($this->createUser());
        $origin = $this->createTopic($this->unique('curro'));
        $destination = $this->createTopic($this->unique('trabajo'));

        foreach ([['origins' => [$origin->getId(), 999999999], 'destination' => $destination->getId()], ['origins' => [$origin->getId()], 'destination' => 999999999], ['origins' => [], 'destination' => $destination->getId()]] as $body) {
            $this->api('POST', '/api/v1/topics/fusionar', $token, $body);

            $this->assertApiError(422, 'validation_failed');
        }
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Topic::class, $origin->getId()));
    }

    public function testWritesRequireToken(): void
    {
        $topic = $this->createTopic($this->unique('curro'));

        $this->api('PATCH', sprintf('/api/v1/topics/%d', $topic->getId()), body: ['name' => 'x']);
        $this->assertApiError(401, 'unauthorized');

        $this->api('POST', '/api/v1/topics/fusionar', body: ['origins' => [$topic->getId()], 'destination' => $topic->getId()]);
        $this->assertApiError(401, 'unauthorized');
    }

    private function topicByName(string $name): Topic
    {
        $topic = $this->entityManager->getRepository(Topic::class)->findOneBy(['name' => $name]);
        self::assertNotNull($topic);

        return $topic;
    }
}
