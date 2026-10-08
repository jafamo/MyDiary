<?php

declare(strict_types=1);

namespace App\Tests\Doc;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\RouterInterface;

/**
 * Guardas de la documentación de la API: el esquema OpenAPI versionado y las dos colecciones
 * de peticiones tienen que estar al día con las rutas `/api/v1/*` del código.
 */
class ApiDocumentationTest extends WebTestCase
{
    private const DOC_DIR = __DIR__.'/../../doc';

    public function testCommittedOpenApiSchemaMatchesTheCode(): void
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('nelmio:apidoc:dump'));
        $tester->execute(['--format' => 'json']);

        self::assertSame(
            json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR),
            json_decode((string) file_get_contents(self::DOC_DIR.'/openapi.json'), true, 512, \JSON_THROW_ON_ERROR),
            'doc/openapi.json no está al día: ejecuta `make openapi`.',
        );
    }

    public function testEveryApiRouteIsInTheOpenApiSchema(): void
    {
        self::bootKernel();
        $schema = json_decode((string) file_get_contents(self::DOC_DIR.'/openapi.json'), true, 512, \JSON_THROW_ON_ERROR);

        foreach ($this->apiEndpoints() as [$method, $path]) {
            self::assertArrayHasKey(strtolower($method), $schema['paths'][$path] ?? [], sprintf('%s %s no está en doc/openapi.json.', $method, $path));
        }
        self::assertSame(['Bearer' => []], $schema['security'][0]);
        self::assertSame('bearer', $schema['components']['securitySchemes']['Bearer']['scheme']);
    }

    public function testEveryApiRouteIsInThePostmanCollection(): void
    {
        self::bootKernel();
        $collection = json_decode((string) file_get_contents(self::DOC_DIR.'/MyDiary.postman_collection.json'), true, 512, \JSON_THROW_ON_ERROR);
        $requests = $this->postmanRequests($collection['item']);

        foreach ($this->apiEndpoints() as [$method, $path]) {
            self::assertContains($method.' {{base_url}}'.$path, $requests, sprintf('%s %s no está en doc/MyDiary.postman_collection.json.', $method, $path));
        }
    }

    public function testEveryApiRouteIsInTheHttpFile(): void
    {
        self::bootKernel();
        $lines = array_map('trim', file(self::DOC_DIR.'/api.http', \FILE_IGNORE_NEW_LINES) ?: []);

        foreach ($this->apiEndpoints() as [$method, $path]) {
            self::assertContains($method.' {{base_url}}'.$path, $lines, sprintf('%s %s no está en doc/api.http.', $method, $path));
        }
    }

    public function testSwaggerUiAndSchemaRequireAWebSession(): void
    {
        $client = static::createClient();

        foreach (['/doc/api', '/doc/api.json'] as $path) {
            $client->request('GET', $path);

            self::assertResponseRedirects('/login');
        }
    }

    public function testSwaggerUiAndSchemaWithAWebSession(): void
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setUsername('api_doc_'.bin2hex(random_bytes(4)))->setRoles(['ROLE_USER'])->setPassword('hash');
        $entityManager->persist($user);
        $entityManager->flush();

        try {
            $client->loginUser($user);

            $client->request('GET', '/doc/api');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('swagger-ui', (string) $client->getResponse()->getContent());

            $client->request('GET', '/doc/api.json');
            self::assertResponseIsSuccessful();
            $schema = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('/api/v1/login', $schema['paths']);
        } finally {
            $entityManager->remove($entityManager->find(User::class, $user->getId()));
            $entityManager->flush();
        }
    }

    /**
     * @return list<array{string, string}> método y ruta de cada endpoint `/api/v1/*`
     */
    private function apiEndpoints(): array
    {
        $endpoints = [];
        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $route) {
            if (!str_starts_with($route->getPath(), '/api/v1/')) {
                continue;
            }
            foreach ($route->getMethods() as $method) {
                $endpoints[] = [$method, $route->getPath()];
            }
        }

        self::assertNotSame([], $endpoints);

        return $endpoints;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<string> "MÉTODO url" de cada petición, recorriendo las carpetas
     */
    private function postmanRequests(array $items): array
    {
        $requests = [];
        foreach ($items as $item) {
            if (isset($item['item'])) {
                $requests = [...$requests, ...$this->postmanRequests($item['item'])];
            } elseif (isset($item['request'])) {
                $requests[] = $item['request']['method'].' '.$item['request']['url']['raw'];
            }
        }

        return $requests;
    }
}
