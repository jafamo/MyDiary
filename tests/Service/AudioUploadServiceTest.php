<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioUploadService;
use App\Service\InvalidAudioUploadException;
use App\Tests\Double\FakeAudioProbe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AudioUploadServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private AudioUploadService $service;
    private FakeAudioProbe $audioProbe;
    private string $audioStorageDir;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->service = $container->get(AudioUploadService::class);
        // Doble definido solo en when@test (PHPStan analiza el contenedor de dev)
        $this->audioProbe = $container->get(FakeAudioProbe::class); // @phpstan-ignore symfonyContainer.serviceNotFound
        $this->audioStorageDir = $container->getParameter('app.audio_storage_dir');

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function testNewAudioIsProbedStoredByHashAndCreated(): void
    {
        $path = $this->tempFile('RIFF'.random_bytes(32));
        $contentHash = hash_file('sha256', $path);
        $this->audioProbe->durationSeconds = 12;

        $upload = $this->service->upload($path);

        self::assertSame(AudioRecordingReceiveResult::CREATED, $upload->result);
        self::assertSame(AudioSource::APP, $upload->audioRecording->getSource());
        self::assertSame($contentHash, $upload->audioRecording->getContentHash());
        self::assertSame(12, $upload->audioRecording->getDurationSeconds());
        self::assertSame(AudioRecordingStatus::PENDING, $upload->audioRecording->getStatus());
        self::assertSame($this->audioStorageDir.'/'.$contentHash.'.wav', $upload->audioRecording->getFilePath());
        self::assertFileEquals($path, $upload->audioRecording->getFilePath());
        self::assertFileExists($path, 'El fichero subido no se mueve ni se borra');
    }

    public function testDuplicateIsNotProbedNorStoredAgain(): void
    {
        $path = $this->tempFile('ID3'.random_bytes(32));
        $first = $this->service->upload($path);
        $callsAfterFirst = $this->audioProbe->calls;

        $second = $this->service->upload($path);

        self::assertSame(AudioRecordingReceiveResult::DUPLICATE_FILE, $second->result);
        self::assertSame($first->audioRecording->getId(), $second->audioRecording->getId());
        self::assertSame($callsAfterFirst, $this->audioProbe->calls);
    }

    public function testFileThatIsNotAudioIsRejectedWithoutLeavingAnything(): void
    {
        $path = $this->tempFile('texto plano');
        $contentHash = hash_file('sha256', $path);

        try {
            $this->service->upload($path);
            self::fail('Se esperaba InvalidAudioUploadException');
        } catch (InvalidAudioUploadException $exception) {
            self::assertStringContainsString('m4a, mp3, ogg o wav', $exception->getMessage());
        }

        self::assertNull($this->audioRecordingRepository->findOneByContentHash($contentHash));
        self::assertSame([], glob($this->audioStorageDir.'/'.$contentHash.'.*'));
    }

    public function testFileOverTheSizeLimitIsRejectedBeforeProbing(): void
    {
        $path = $this->tempFile('RIFF');
        $handle = fopen($path, 'r+');
        ftruncate($handle, AudioUploadService::MAX_BYTES + 1);
        fclose($handle);
        $calls = $this->audioProbe->calls;

        try {
            $this->service->upload($path);
            self::fail('Se esperaba InvalidAudioUploadException');
        } catch (InvalidAudioUploadException $exception) {
            self::assertStringContainsString('25 MB', $exception->getMessage());
        }

        self::assertSame($calls, $this->audioProbe->calls);
    }

    public function testFileOfExactlyTheSizeLimitIsAccepted(): void
    {
        $path = $this->tempFile('RIFF'.random_bytes(32));
        $handle = fopen($path, 'r+');
        ftruncate($handle, AudioUploadService::MAX_BYTES);
        fclose($handle);

        $upload = $this->service->upload($path);

        self::assertSame(AudioRecordingReceiveResult::CREATED, $upload->result);
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'audio-upload-');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function cleanUp(): void
    {
        $this->entityManager->clear();
        foreach ($this->audioRecordingRepository->findBy(['source' => AudioSource::APP]) as $audioRecording) {
            if (file_exists($audioRecording->getFilePath())) {
                unlink($audioRecording->getFilePath());
            }
            $this->entityManager->remove($audioRecording);
        }
        $this->entityManager->flush();
    }
}
