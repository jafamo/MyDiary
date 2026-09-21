<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DailySummary;
use App\Entity\Topic;
use App\Entity\User;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TopicControllerTest extends WebTestCase
{
    private const DATES = ['2021-04-01', '2021-04-02'];
    private const NAMES = ['topic-ctrl-trabajo', 'topic-ctrl-curro', 'topic-ctrl-ocio'];

    private EntityManagerInterface $entityManager;
    private TopicRepository $topicRepository;
    private DailySummaryRepository $dailySummaryRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }

        parent::tearDown();
    }

    public function testIndexListsTopicsWithUsageCount(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $topic = $this->createTopic('topic-ctrl-trabajo');
        $this->createDailySummary('2021-04-01', [$topic]);

        $client->loginUser($user);
        $client->request('GET', '/topics');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('topic-ctrl-trabajo', $client->getResponse()->getContent());
    }

    public function testRenameUpdatesTopicName(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $topic = $this->createTopic('topic-ctrl-curro');
        $topicId = $topic->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/topics/%d/renombrar', $topicId), [
            'topic_rename' => [
                'name' => 'topic-ctrl-trabajo',
                '_token' => $this->csrfToken($client, 'topic_rename'),
            ],
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertSame('topic-ctrl-trabajo', $this->topicRepository->find($topicId)->getName());
    }

    public function testRenameToDuplicateNameIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createTopic('topic-ctrl-trabajo');
        $curro = $this->createTopic('topic-ctrl-curro');
        $curroId = $curro->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/topics/%d/renombrar', $curroId), [
            'topic_rename' => [
                'name' => 'TOPIC-CTRL-TRABAJO',
                '_token' => $this->csrfToken($client, 'topic_rename'),
            ],
        ]);

        self::assertSame(422, $client->getResponse()->getStatusCode());

        $this->entityManager->clear();
        self::assertSame('topic-ctrl-curro', $this->topicRepository->find($curroId)->getName());
    }

    public function testMergeReassignsDailySummariesAndDeletesOrigin(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $trabajo = $this->createTopic('topic-ctrl-trabajo');
        $curro = $this->createTopic('topic-ctrl-curro');
        $this->createDailySummary('2021-04-01', [$curro]);
        $trabajoId = $trabajo->getId();
        $curroId = $curro->getId();

        $client->loginUser($user);
        $client->request('POST', '/topics/fusionar', [
            'origins' => [(string) $curroId],
            'destination' => (string) $trabajoId,
            '_token' => $this->csrfToken($client, 'topic_merge_confirm'),
        ]);

        self::assertResponseRedirects('/topics');

        $this->entityManager->clear();
        self::assertNull($this->topicRepository->find($curroId));
        $trabajo = $this->topicRepository->find($trabajoId);
        self::assertCount(1, $trabajo->getDailySummaries());
    }

    private function csrfToken(AbstractBrowser $client, string $tokenId): string
    {
        $client->request('GET', '/');
        $session = $client->getRequest()->getSession();

        $token = bin2hex(random_bytes(20));
        $session->set('_csrf/'.$tokenId, $token);
        $session->save();

        return $token;
    }

    private function createTopic(string $name): Topic
    {
        $topic = new Topic();
        $topic->setName($name);
        $this->entityManager->persist($topic);
        $this->entityManager->flush();

        return $topic;
    }

    /**
     * @param list<Topic> $topics
     */
    private function createDailySummary(string $date, array $topics): DailySummary
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText('Resumen de '.$date)
            ->setGeneratedAt(new \DateTimeImmutable())
        ;
        foreach ($topics as $topic) {
            $dailySummary->addTopic($topic);
        }
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();

        return $dailySummary;
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->topicRepository = self::getContainer()->get(TopicRepository::class);
        $this->dailySummaryRepository = self::getContainer()->get(DailySummaryRepository::class);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_topic_ctrl_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        foreach (self::DATES as $date) {
            $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }
        $this->entityManager->flush();

        foreach (self::NAMES as $name) {
            $topic = $this->topicRepository->findOneByName($name);
            if (null !== $topic) {
                $this->entityManager->remove($topic);
            }
        }
        $this->entityManager->flush();

        $userRepository = self::getContainer()->get(\App\Repository\UserRepository::class);
        $user = $userRepository->findOneByUsername('test_topic_ctrl_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }
}
