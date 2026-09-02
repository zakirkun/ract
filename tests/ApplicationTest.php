<?php

declare(strict_types=1);

namespace Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Config\Config;
use Ract\Exception\HttpException;
use Ract\Http\Request;
use Ract\Routing\Router;
use Ract\View\View;
use RuntimeException;
use Tests\Fixtures\TestController;

final class ApplicationTest extends TestCase
{
    private Router $router;

    private Application $app;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->app = new Application(
            dirname(__DIR__),
            new Config(['app' => ['debug' => false, 'timezone' => 'UTC']]),
            $this->router,
            new View(__DIR__ . '/Fixtures/views'),
        );
    }

    public function testItInvokesControllersWithRouteParameters(): void
    {
        $this->router->get('/hello/{name}', [TestController::class, 'show']);

        $response = $this->app->handle(new Request('GET', '/hello/Ract'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('Hello, Ract!', trim($response->body()));
    }

    public function testItNormalizesArrayResultsToJson(): void
    {
        $this->router->get('/status', static fn (): array => ['status' => 'ok']);

        $response = $this->app->handle(new Request('GET', '/status'));

        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('{"status":"ok"}', $response->body());
    }

    public function testItInjectsRouteHandlersThroughTheContainer(): void
    {
        $this->router->get(
            '/hello/{name}',
            static fn (Request $request, string $name): array => [
                'method' => $request->method(),
                'name' => $name,
            ],
        );

        $response = $this->app->handle(new Request('GET', '/hello/Ract'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('{"method":"GET","name":"Ract"}', $response->body());
    }

    public function testItReturns400ForMalformedJsonInput(): void
    {
        $this->router->post('/payload', static fn (Request $request): array => [
            'name' => $request->input('name'),
        ]);
        $request = new Request(
            'POST',
            '/payload',
            headers: ['Content-Type' => 'application/json'],
            body: '{invalid',
        );

        $response = $this->app->handle($request);

        self::assertSame(400, $response->statusCode());
        self::assertStringContainsString('Malformed JSON request body', $response->body());
    }

    public function testItReturns404ForAnUnknownRoute(): void
    {
        $response = $this->app->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString('No route found', $response->body());
    }

    public function testItReturns405WithAnAllowHeader(): void
    {
        $this->router->post('/articles', static fn () => null);

        $response = $this->app->handle(new Request('GET', '/articles'));

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->header('Allow'));
    }

    public function testProductionErrorsDoNotExposeExceptionDetails(): void
    {
        $this->router->get('/failure', static function (): never {
            throw new RuntimeException('sensitive detail');
        });

        $response = $this->app->handle(new Request('GET', '/failure'));

        self::assertSame(500, $response->statusCode());
        self::assertStringNotContainsString('sensitive detail', $response->body());
        self::assertStringContainsString('unexpected error', strtolower($response->body()));
    }

    public function testItRejectsInvalidApplicationTimezones(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid application timezone');

        new Application(
            dirname(__DIR__),
            new Config(['app' => ['timezone' => 'Not/A-Timezone']]),
            new Router(),
            new View(__DIR__ . '/Fixtures/views'),
        );
    }

    public function testInvalidErrorResponsesFallBackToASafe500Response(): void
    {
        $this->router->get('/broken-error', static function (): never {
            throw new class () extends HttpException {
                public function __construct()
                {
                    parent::__construct(418, 'Teapot');
                }

                public function headers(): array
                {
                    return ['Bad:Name' => 'value'];
                }
            };
        });

        $response = $this->app->handle(new Request('GET', '/broken-error'));

        self::assertSame(500, $response->statusCode());
        self::assertStringContainsString('unexpected error', strtolower($response->body()));
    }
}
