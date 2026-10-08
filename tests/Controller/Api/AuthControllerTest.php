<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

class AuthControllerTest extends ApiTestCase
{
    public function testRequestWithoutTokenIsUnauthorized(): void
    {
        $this->api('GET', '/api/v1/me');

        $this->assertApiError(401, 'unauthorized');
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }

    public function testInvalidTokenIsUnauthorized(): void
    {
        $this->api('GET', '/api/v1/me', str_repeat('a', 64));

        $this->assertApiError(401, 'unauthorized');
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }

    public function testWebSessionDoesNotAuthenticateTheApi(): void
    {
        $this->client->loginUser($this->createUser());

        $this->api('GET', '/api/v1/me');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testWebStillRedirectsToLoginForm(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginIssuesTokenStoredOnlyAsHash(): void
    {
        $user = $this->createUser();

        $this->api('POST', '/api/v1/login', null, ['username' => $user->getUsername(), 'password' => self::PASSWORD, 'device_name' => ' iPhone ']);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['token', 'token_id', 'device_name', 'expires_at'], array_keys($json));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $json['token']);
        self::assertSame('iPhone', $json['device_name']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $json['expires_at']);

        $stored = $this->tokenRepository()->find($json['token_id']);
        self::assertNotNull($stored);
        self::assertSame('iPhone', $stored->getName());
        self::assertSame(hash('sha256', $json['token']), $stored->getTokenHash());
        self::assertNotSame($json['token'], $stored->getTokenHash());

        $this->api('GET', '/api/v1/me', $json['token']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testLoginWithWrongPassword(): void
    {
        $user = $this->createUser();

        $this->api('POST', '/api/v1/login', null, ['username' => $user->getUsername(), 'password' => 'wrong', 'device_name' => 'iPhone']);

        $this->assertApiError(401, 'unauthorized');
        self::assertSame([], $this->tokenRepository()->findByUser($user));
    }

    public function testLoginWithUnknownUserAnswersLikeWrongPassword(): void
    {
        $user = $this->createUser();

        $this->api('POST', '/api/v1/login', null, ['username' => $user->getUsername(), 'password' => 'wrong', 'device_name' => 'iPhone']);
        $wrongPassword = $this->client->getResponse()->getContent();

        $this->api('POST', '/api/v1/login', null, ['username' => 'no_existe_'.bin2hex(random_bytes(4)), 'password' => 'wrong', 'device_name' => 'iPhone']);

        $this->assertApiError(401, 'unauthorized');
        self::assertSame($wrongPassword, $this->client->getResponse()->getContent());
    }

    public function testLoginWithMissingOrInvalidFields(): void
    {
        $user = $this->createUser();
        $valid = ['username' => $user->getUsername(), 'password' => self::PASSWORD, 'device_name' => 'iPhone'];

        foreach ([
            array_diff_key($valid, ['device_name' => true]),
            array_diff_key($valid, ['password' => true]),
            ['username' => '  '] + $valid,
            ['device_name' => 42] + $valid,
            ['device_name' => str_repeat('x', 101)] + $valid,
        ] as $body) {
            $this->api('POST', '/api/v1/login', null, $body);

            $this->assertApiError(422, 'validation_failed');
        }

        self::assertSame([], $this->tokenRepository()->findByUser($user));
    }

    public function testLoginWithMalformedJson(): void
    {
        $this->api('POST', '/api/v1/login', null, null, '{"username": ');
        $this->assertApiError(400, 'bad_request');

        $this->api('POST', '/api/v1/login', null, null, '"texto"');
        $this->assertApiError(400, 'bad_request');
    }

    public function testLoginIsThrottledAfterFiveFailedAttempts(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $wrong = ['username' => $user->getUsername(), 'password' => 'wrong', 'device_name' => 'iPhone'];

        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/v1/login', null, $wrong);
            self::assertResponseStatusCodeSame(401);
        }

        // El sexto intento se bloquea aunque la contraseña sea correcta, y sin distinguir mayúsculas en el usuario.
        $this->api('POST', '/api/v1/login', null, ['username' => strtoupper($user->getUsername()), 'password' => self::PASSWORD, 'device_name' => 'iPhone']);
        $this->assertApiError(429, 'too_many_requests');
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));
        self::assertSame([], $this->tokenRepository()->findByUser($user));

        // El límite es por usuario: otro usuario desde la misma IP entra con normalidad.
        $this->api('POST', '/api/v1/login', null, ['username' => $other->getUsername(), 'password' => self::PASSWORD, 'device_name' => 'iPhone']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testSuccessfulLoginResetsTheAttemptCounter(): void
    {
        $user = $this->createUser();
        $wrong = ['username' => $user->getUsername(), 'password' => 'wrong', 'device_name' => 'iPhone'];
        $right = ['password' => self::PASSWORD] + $wrong;

        for ($round = 0; $round < 2; ++$round) {
            for ($i = 0; $i < 4; ++$i) {
                $this->api('POST', '/api/v1/login', null, $wrong);
                self::assertResponseStatusCodeSame(401);
            }
            $this->api('POST', '/api/v1/login', null, $right);
            self::assertResponseStatusCodeSame(200);
        }
    }

    public function testMeReturnsUserAndTokenData(): void
    {
        $user = $this->createUser();
        $token = $this->issueToken($user, 'iPhone de pruebas');

        $this->api('GET', '/api/v1/me', $token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['username', 'token_id', 'device_name', 'created_at', 'last_used_at', 'expires_at'], array_keys($json));
        self::assertSame($user->getUsername(), $json['username']);
        self::assertSame('iPhone de pruebas', $json['device_name']);
        foreach (['created_at', 'last_used_at', 'expires_at'] as $field) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $json[$field]);
        }
        self::assertSame(
            (new \DateTimeImmutable($json['last_used_at']))->modify('+90 days')->format(\DATE_ATOM),
            (new \DateTimeImmutable($json['expires_at']))->format(\DATE_ATOM),
        );
        self::assertStringNotContainsString($token, (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString(hash('sha256', $token), (string) $this->client->getResponse()->getContent());
    }

    public function testExpiredTokenIsRejectedAndDeleted(): void
    {
        $user = $this->createUser();
        $token = $this->issueToken($user, 'Viejo', new \DateTimeImmutable('-91 days'));

        $this->api('GET', '/api/v1/me', $token);

        $this->assertApiError(401, 'unauthorized');
        $this->entityManager->clear();
        self::assertSame([], $this->tokenRepository()->findByUser($user));
    }

    public function testUsingATokenRenewsIt(): void
    {
        $user = $this->createUser();
        $token = $this->issueToken($user, 'Casi caducado', new \DateTimeImmutable('-89 days'));

        $this->api('GET', '/api/v1/me', $token);

        self::assertResponseStatusCodeSame(200);
        $this->entityManager->clear();
        $stored = $this->tokenRepository()->findByUser($user)[0];
        self::assertNotNull($stored->getLastUsedAt());
        self::assertEqualsWithDelta(time(), $stored->getLastUsedAt()->getTimestamp(), 5);
    }

    public function testLogoutRevokesOnlyTheTokenInUse(): void
    {
        $user = $this->createUser();
        $phone = $this->issueToken($user, 'iPhone');
        $tablet = $this->issueToken($user, 'iPad');

        $this->api('POST', '/api/v1/logout', $phone);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->api('GET', '/api/v1/me', $phone);
        $this->assertApiError(401, 'unauthorized');

        $this->api('GET', '/api/v1/me', $tablet);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('iPad', $this->json()['device_name']);
    }

    public function testLogoutWithoutToken(): void
    {
        $this->api('POST', '/api/v1/logout');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testUnknownApiRouteIsJsonNotFound(): void
    {
        $this->api('GET', '/api/v1/no-existe', $this->issueToken($this->createUser()));

        $this->assertApiError(404, 'not_found');
    }

    public function testWrongMethodIsJsonMethodNotAllowed(): void
    {
        $this->api('GET', '/api/v1/login');

        $this->assertApiError(405, 'method_not_allowed');
    }

    public function testUnknownWebRouteKeepsHtmlErrorPage(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/no-existe');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('text/html', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testLoginLogsNeverContainSecrets(): void
    {
        $user = $this->createUser();

        $this->api('POST', '/api/v1/login', null, ['username' => $user->getUsername(), 'password' => 'wrong-secret', 'device_name' => 'iPhone']);
        $failed = $this->loginRecords('api.login_failed');
        self::assertCount(1, $failed);
        self::assertSame('WARNING', $failed[0]->level->getName());
        self::assertSame($user->getUsername(), $failed[0]->context['api_username']);

        $this->api('POST', '/api/v1/login', null, ['username' => $user->getUsername(), 'password' => self::PASSWORD, 'device_name' => 'iPhone']);
        $token = $this->json()['token'];
        $succeeded = $this->loginRecords('api.login_succeeded');
        self::assertCount(1, $succeeded);
        self::assertSame('INFO', $succeeded[0]->level->getName());
        self::assertSame(['event', 'api_token_id', 'api_device_name'], array_keys($succeeded[0]->context));
        self::assertSame('iPhone', $succeeded[0]->context['api_device_name']);

        foreach ([...$failed, ...$succeeded] as $record) {
            $line = json_encode([$record->message, $record->context], \JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('wrong-secret', $line);
            self::assertStringNotContainsString(self::PASSWORD, $line);
            self::assertStringNotContainsString($token, $line);
            self::assertStringNotContainsString(hash('sha256', $token), $line);
        }
    }

    public function testThrottledLoginIsLogged(): void
    {
        $user = $this->createUser();
        $wrong = ['username' => $user->getUsername(), 'password' => 'wrong', 'device_name' => 'iPhone'];
        for ($i = 0; $i < 5; ++$i) {
            $this->api('POST', '/api/v1/login', null, $wrong);
        }

        $this->api('POST', '/api/v1/login', null, $wrong);

        $throttled = $this->loginRecords('api.login_throttled');
        self::assertCount(1, $throttled);
        self::assertSame($user->getUsername(), $throttled[0]->context['api_username']);
    }

    public function testRequestLogCarriesTheTokenId(): void
    {
        $user = $this->createUser();
        $token = $this->issueToken($user);
        $tokenId = $this->tokenRepository()->findByUser($user)[0]->getId();

        $this->api('GET', '/api/v1/me', $token);

        $records = array_values(array_filter(
            $this->testHandler()->getRecords(),
            static fn (LogRecord $record) => 'http.request' === ($record->context['event'] ?? null),
        ));
        self::assertCount(1, $records);
        self::assertSame($tokenId, $records[0]->context['api_token_id']);
        self::assertSame('api_me', $records[0]->context['route']);
    }

    /**
     * Registros del último request con ese `event`.
     *
     * @return list<LogRecord>
     */
    private function loginRecords(string $event): array
    {
        return array_values(array_filter(
            $this->testHandler()->getRecords(),
            static fn (LogRecord $record) => $event === ($record->context['event'] ?? null),
        ));
    }

    private function testHandler(): TestHandler
    {
        // El handler `test` solo existe en el entorno de test: se localiza a través del logger.
        foreach (self::getContainer()->get('monolog.logger')->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return $handler;
            }
        }

        self::fail('No hay TestHandler de Monolog en el entorno de test.');
    }
}
