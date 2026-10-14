<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiException;
use App\Controller\Api\ApiForm;
use App\Entity\Reminder;
use App\Entity\Topic;
use App\Form\ReminderType;
use App\Form\TopicMergeType;
use App\Form\TopicRenameType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiFormTest extends KernelTestCase
{
    private ApiForm $apiForm;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->apiForm = self::getContainer()->get(ApiForm::class);
    }

    public function testValidBodyIsMappedToTheEntity(): void
    {
        $reminder = new Reminder();

        $this->apiForm->submit(ReminderType::class, $reminder, $this->request(['date' => '2026-10-20', 'time' => '10:30', 'text' => '  Dentista  ']));

        self::assertSame('2026-10-20', $reminder->getDate()->format('Y-m-d'));
        self::assertSame('10:30', $reminder->getTime()?->format('H:i'));
        self::assertSame('Dentista', $reminder->getText());
    }

    public function testBodyThatIsNotAJsonObjectIsABadRequest(): void
    {
        foreach (['no es json', '"texto"', '[1, 2]'] as $body) {
            $this->assertApiException(400, ApiException::BAD_REQUEST, fn () => $this->apiForm->submit(ReminderType::class, new Reminder(), $this->rawRequest($body)));
        }
    }

    public function testUnknownFieldIsRejected(): void
    {
        $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(ReminderType::class, new Reminder(), $this->request(['date' => '2026-10-20', 'text' => 'x', 'titulo' => 'x'])), '"titulo"');
    }

    public function testRequiredFieldMissingBlankOrNullIsRejected(): void
    {
        foreach ([['date' => '2026-10-20'], ['date' => '2026-10-20', 'text' => '   '], ['date' => null, 'text' => 'x']] as $body) {
            $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(ReminderType::class, new Reminder(), $this->request($body)));
        }
    }

    public function testOptionalFieldCanBeNull(): void
    {
        $reminder = new Reminder();

        $this->apiForm->submit(ReminderType::class, $reminder, $this->request(['date' => '2026-10-20', 'time' => null, 'text' => 'x']));

        self::assertNull($reminder->getTime());
    }

    public function testPartialSubmitKeepsMissingFields(): void
    {
        $reminder = (new Reminder())
            ->setDate(new \DateTimeImmutable('2026-10-20'))
            ->setTime(new \DateTimeImmutable('1970-01-01 10:30'))
            ->setText('Antes')
        ;

        $this->apiForm->submit(ReminderType::class, $reminder, $this->request(['text' => 'Después']), partial: true);

        self::assertSame('Después', $reminder->getText());
        self::assertSame('2026-10-20', $reminder->getDate()->format('Y-m-d'));
        self::assertSame('10:30', $reminder->getTime()?->format('H:i'));
    }

    public function testPartialSubmitStillRejectsBlankRequiredFields(): void
    {
        $reminder = (new Reminder())->setDate(new \DateTimeImmutable('2026-10-20'))->setText('Antes');

        $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(ReminderType::class, $reminder, $this->request(['text' => '']), partial: true));
        self::assertSame('Antes', $reminder->getText());
    }

    public function testValueThatCannotBeTransformedIsRejected(): void
    {
        foreach ([['date' => '2026-02-30', 'text' => 'x'], ['date' => '2026-10-20', 'time' => '25:00', 'text' => 'x'], ['date' => '2026-10-20', 'text' => ['x']]] as $body) {
            $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(ReminderType::class, new Reminder(), $this->request($body)));
        }
    }

    public function testUnknownChoiceInAMultipleFieldIsRejected(): void
    {
        $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(TopicMergeType::class, null, $this->request(['origins' => [999999999], 'destination' => 999999998])), '"origins"');
    }

    public function testMaxLengthIsEnforced(): void
    {
        $topic = (new Topic())->setName('curro');

        $this->assertApiException(422, ApiException::VALIDATION_FAILED, fn () => $this->apiForm->submit(TopicRenameType::class, $topic, $this->request(['name' => str_repeat('a', 256)])), '255');
        self::assertSame('curro', $topic->getName());

        $this->apiForm->submit(TopicRenameType::class, $topic, $this->request(['name' => str_repeat('a', 255)]));
        self::assertSame(str_repeat('a', 255), $topic->getName());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return $this->rawRequest(json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function rawRequest(string $body): Request
    {
        return Request::create('/api/v1/test', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
    }

    private function assertApiException(int $status, string $code, callable $callback, ?string $messageContains = null): void
    {
        try {
            $callback();
            self::fail('Se esperaba una ApiException.');
        } catch (ApiException $exception) {
            self::assertSame($status, $exception->getStatusCode());
            self::assertSame($code, $exception->getErrorCode());
            if (null !== $messageContains) {
                self::assertStringContainsString($messageContains, $exception->getMessage());
            }
        }
    }
}
