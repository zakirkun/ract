<?php

declare(strict_types=1);

namespace Tests;

use App\Console\Kernel;
use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Console\BufferedOutput;
use Ract\Database\DatabaseManager;
use Ract\Http\Request;

final class SampleApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = require dirname(__DIR__) . '/bootstrap/app.php';
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

    public function testBootstrapRegistersTheDatabaseProviderAsASingleton(): void
    {
        $first = $this->app->container()->make(DatabaseManager::class);
        $second = $this->app->container()->make(DatabaseManager::class);

        self::assertSame('sqlite', $this->app->config()->get('database.default'));
        self::assertSame($first, $second);
    }

    public function testApplicationConsoleKernelExposesFrameworkCommands(): void
    {
        $output = new BufferedOutput();
        $kernel = new Kernel($this->app);

        self::assertSame(0, $kernel->handle(['ract', 'help'], $output));
        self::assertStringContainsString('make:crud', $output->contents());
        self::assertStringContainsString('schedule:run', $output->contents());
        self::assertSame($kernel, $this->app->container()->make(Kernel::class));
    }
}
