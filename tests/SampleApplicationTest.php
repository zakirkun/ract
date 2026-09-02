<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Http\Request;

final class SampleApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $rootPath = dirname(__DIR__);
        $this->app = Application::create($rootPath);
        $routes = require $rootPath . '/app/routes.php';
        $routes($this->app->router());
    }

    public function testHomePageIsAvailable(): void
    {
        $response = $this->app->handle(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Your MVC application is running', $response->body());
    }

    public function testDynamicRouteEscapesItsParameter(): void
    {
        $response = $this->app->handle(new Request('GET', '/hello/%3CRact%3E'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Hello, &lt;Ract&gt;!', $response->body());
        self::assertStringNotContainsString('Hello, <Ract>!', $response->body());
    }

    public function testStatusEndpointReturnsJson(): void
    {
        $response = $this->app->handle(new Request('GET', '/api/status'));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame('Ract', $payload['framework']);
        self::assertSame('ok', $payload['status']);
    }
}
