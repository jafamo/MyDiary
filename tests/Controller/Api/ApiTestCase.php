<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Entity\DailySummary;
use App\Entity\Reminder;
use App\Entity\Topic;
use App\Entity\Transcription;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base de los tests funcionales de la API: crea usuarios de prueba (se borran al terminar,
 * y con ellos sus tokens) y emite tokens sin pasar por el login.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'a-strong-password';

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    /** @var list<string> */
    private array $usernames = [];

    /** @var list<array{class-string, int}> entidades de prueba creadas con los métodos create*, para borrarlas al terminar */
    private array $fixtures = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Mismo contenedor en todas las peticiones del test, para que el limitador de intentos
        // (en memoria en el entorno de test) cuente los logins de una petición a la siguiente.
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->usernames = [];
        $this->fixtures = [];
    }

    protected function tearDown(): void
    {
        try {
            $userRepository = self::getContainer()->get(UserRepository::class);
            $this->entityManager->clear();
            // En orden inverso: los resúmenes antes que sus temas.
            foreach (array_reverse($this->fixtures) as [$class, $id]) {
                $entity = $this->entityManager->find($class, $id);
                if (null !== $entity) {
                    $this->entityManager->remove($entity);
                    $this->entityManager->flush();
                }
            }
            foreach ($this->usernames as $username) {
                $user = $userRepository->findOneByUsername($username);
                if (null !== $user) {
                    $this->entityManager->remove($user);
                }
            }
            $this->entityManager->flush();
        } finally {
            parent::tearDown();
        }
    }

    protected function createUser(string $prefix = 'api_test'): User
    {
        $user = new User();
        $user->setUsername($prefix.'_'.bin2hex(random_bytes(4)));
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->usernames[] = $user->getUsername();

        return $user;
    }

    protected function issueToken(User $user, string $name = 'iPhone', ?\DateTimeImmutable $createdAt = null): string
    {
        return self::getContainer()->get(ApiTokenManager::class)->issue($user, $name, $createdAt ?? new \DateTimeImmutable())['token'];
    }

    /**
     * Audio de origen `app` recibido en $receivedAt (UTC). Con $content queda transcrito.
     *
     * @param list<float>|null $embedding
     */
    protected function createAudio(string $receivedAt, AudioRecordingStatus $status = AudioRecordingStatus::PENDING, ?string $content = null, ?array $embedding = null, int $durationSeconds = 30): AudioRecording
    {
        $contentHash = hash('sha256', random_bytes(16));
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setSource(AudioSource::APP)
            ->setContentHash($contentHash)
            ->setFilePath('/data/audio/'.$contentHash.'.m4a')
            ->setReceivedAt(new \DateTimeImmutable($receivedAt, new \DateTimeZone('UTC')))
            ->setDurationSeconds($durationSeconds)
            ->setStatus($status)
        ;
        if (AudioRecordingStatus::ERROR === $status) {
            $audioRecording->setErrorCode('TIMEOUT')->setErrorMessage('No se pudo contactar con el servicio de transcripción.');
        }
        $this->entityManager->persist($audioRecording);

        if (null !== $content) {
            $audioRecording->setStatus(AudioRecordingStatus::TRANSCRIBED);
            $transcription = new Transcription();
            $transcription
                ->setAudioRecording($audioRecording)
                ->setContent($content)
                ->setFilePath('/data/transcriptions/'.$contentHash.'.txt')
                ->setModel('whisper-test')
                ->setProcessingMs(1200)
                ->setCreatedAt($audioRecording->getReceivedAt())
                ->setUpdatedAt($audioRecording->getReceivedAt())
                ->setEmbedding($embedding)
            ;
            $this->entityManager->persist($transcription);
        }

        $this->entityManager->flush();
        // Carga el lado inverso (`transcription`), como lo vería una petición real.
        $this->entityManager->refresh($audioRecording);
        $this->fixtures[] = [AudioRecording::class, (int) $audioRecording->getId()];

        return $audioRecording;
    }

    /**
     * Resumen del día $date (`Y-m-d`). Si ya había uno ese día en la BD de test, lo sustituye.
     *
     * @param list<string>     $topicNames
     * @param list<float>|null $embedding
     */
    protected function createSummary(string $date, string $text = 'Resumen de prueba', array $topicNames = [], ?array $embedding = null): DailySummary
    {
        $day = new \DateTimeImmutable($date);
        $existing = $this->entityManager->getRepository(DailySummary::class)->findOneBy(['date' => $day]);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate($day)
            ->setSummaryText($text)
            ->setGeneratedAt(new \DateTimeImmutable($date.' 19:00:00', new \DateTimeZone('UTC')))
            ->setEmojiLegend([['emoji' => '🦷', 'meaning' => 'Dentista']])
            ->setModel('ollama-test')
            ->setPromptTokens(100)
            ->setCompletionTokens(50)
            ->setGenerationMs(900)
            ->setEmbedding($embedding)
        ;
        $topics = [];
        foreach ($topicNames as $topicName) {
            $topics[] = $topic = $this->createTopic($topicName);
            $dailySummary->addTopic($topic);
        }
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();
        $this->fixtures[] = [DailySummary::class, (int) $dailySummary->getId()];

        return $dailySummary;
    }

    protected function createTopic(string $name): Topic
    {
        $topic = (new Topic())->setName($name);
        $this->entityManager->persist($topic);
        $this->entityManager->flush();
        $this->fixtures[] = [Topic::class, (int) $topic->getId()];

        return $topic;
    }

    protected function createReminder(string $date, string $text, ?string $time = null): Reminder
    {
        $reminder = new Reminder();
        $reminder
            ->setDate(new \DateTimeImmutable($date))
            ->setTime(null !== $time ? new \DateTimeImmutable('1970-01-01 '.$time) : null)
            ->setText($text)
        ;
        $this->entityManager->persist($reminder);
        $this->entityManager->flush();
        $this->fixtures[] = [Reminder::class, (int) $reminder->getId()];

        return $reminder;
    }

    /**
     * Nombre único para datos de prueba que tienen restricción de unicidad o se buscan por texto.
     */
    protected function unique(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(4));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<int>
     */
    protected function ids(array $items): array
    {
        return array_map(static fn (array $item): int => $item['id'], $items);
    }

    protected function tokenRepository(): ApiTokenRepository
    {
        return self::getContainer()->get(ApiTokenRepository::class);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function api(string $method, string $uri, ?string $token = null, ?array $body = null, ?string $rawBody = null): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if (null !== $body || null !== $rawBody) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request($method, $uri, [], [], $server, $rawBody ?? (null !== $body ? json_encode($body, \JSON_THROW_ON_ERROR) : null));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function assertApiError(int $status, string $code): void
    {
        self::assertResponseStatusCodeSame($status);
        $json = $this->json();
        self::assertSame(['code', 'message'], array_keys($json));
        self::assertSame($code, $json['code']);
        self::assertNotSame('', $json['message']);
    }
}
