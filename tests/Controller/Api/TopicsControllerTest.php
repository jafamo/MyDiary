<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Topic;

class TopicsControllerTest extends ApiTestCase
{
    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/topics');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testListsEveryTopicWithItsUsage(): void
    {
        $token = $this->issueToken($this->createUser());
        $usedName = $this->unique('usado');
        $this->createSummary('2019-08-01', 'Uno', [$usedName]);
        $used = $this->entityManager->getRepository(Topic::class)->findOneBy(['name' => $usedName]);
        $second = $this->createSummary('2019-08-02', 'Dos');
        $second->addTopic($used);
        $this->entityManager->flush();
        $unused = $this->createTopic($this->unique('sin-usar'));

        $this->api('GET', '/api/v1/topics', $token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['items', 'total'], array_keys($json));
        self::assertCount($json['total'], $json['items']);
        self::assertSame(['id', 'name', 'usage_count', 'last_used'], array_keys($json['items'][0]));

        $positions = array_flip($this->ids($json['items']));
        $usedItem = $json['items'][$positions[$used->getId()]];
        $unusedItem = $json['items'][$positions[$unused->getId()]];

        self::assertSame(['id' => $used->getId(), 'name' => $usedName, 'usage_count' => 2, 'last_used' => '2019-08-02'], $usedItem);
        self::assertSame(['id' => $unused->getId(), 'name' => $unused->getName(), 'usage_count' => 0, 'last_used' => null], $unusedItem);
        self::assertLessThan($positions[$unused->getId()], $positions[$used->getId()]);

        $counts = array_column($json['items'], 'usage_count');
        $sorted = $counts;
        rsort($sorted);
        self::assertSame($sorted, $counts);
    }
}
