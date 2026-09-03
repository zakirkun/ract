<?php

declare(strict_types=1);

namespace Tests\Http;

use Closure;
use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Config\Config;
use Ract\Http\Middleware;
use Ract\Http\Middleware\StartSession;
use Ract\Http\Request;
use Ract\Http\Response;
use Ract\Routing\Router;
use Ract\Session\FileSessionDriver;
use Ract\Session\SessionManager;
use Ract\View\View;
use Tests\Fixtures\HeaderMiddleware;

final class MiddlewareTest extends TestCase
{
    private Router $router;

    private Application $app;

    private string $sessionDirectory;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->app = new Application(
            dirname(__DIR__, 2),
            new Config(['app' => ['debug' => false, 'timezone' => 'UTC']]),
            $this->router,
            new View(dirname(__DIR__) . '/Fixtures/views'),
        );
        $this->sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-http-session-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sessionDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->sessionDirectory)) {
            rmdir($this->sessionDirectory);
        }
    }

    public function testGlobalMiddlewareWrapsSuccessfulAndErrorResponses(): void
    {
        $this->app->middleware(new class () implements Middleware {
            public function handle(Request $request, Closure $next): Response
            {
                return $next($request)->setHeader('X-Global-Middleware', 'applied');
            }
        });
        $this->router->get('/available', static fn (): string => 'ok');

        self::assertSame('applied', $this->app->handle(new Request('GET', '/available'))->header('X-Global-Middleware'));

        $missing = $this->app->handle(new Request('GET', '/missing'));
        self::assertSame(404, $missing->statusCode());
        self::assertSame('applied', $missing->header('X-Global-Middleware'));
    }

    public function testAliasesAndGroupsApplyMiddlewareOnlyToGroupedRoutes(): void
    {
        $this->app->middlewareAlias('header', HeaderMiddleware::class);
        $this->router->middleware('header', function (Router $router): void {
            $router->get('/grouped', static fn (): string => 'grouped');
        });
        $this->router->get('/outside', static fn (): string => 'outside');

        self::assertSame(
            'applied',
            $this->app->handle(new Request('GET', '/grouped'))->header('X-Alias-Middleware'),
        );
        self::assertNull($this->app->handle(new Request('GET', '/outside'))->header('X-Alias-Middleware'));
    }

    public function testMiddlewareCanShortCircuitARequest(): void
    {
        $this->router->get('/private', static function (): never {
            throw new \RuntimeException('The route must not run.');
        })->middleware(new class () implements Middleware {
            public function handle(Request $request, Closure $next): Response
            {
                return Response::json(['message' => 'Forbidden'], 403);
            }
        });

        $response = $this->app->handle(new Request('GET', '/private'));

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden"}', $response->body());
    }

    public function testSessionMiddlewarePreservesCookiesSetByTheApplication(): void
    {
        $sessions = new SessionManager(new FileSessionDriver($this->sessionDirectory));
        $this->app->middleware(new StartSession($sessions, $this->app->container()));
        $this->router->get('/cookie', static fn (): Response => (new Response('ok'))
            ->setHeader('Set-Cookie', 'theme=dark; Path=/'));

        $response = $this->app->handle(new Request('GET', '/cookie'));
        $cookies = $response->headerValues('Set-Cookie');

        self::assertCount(2, $cookies);
        self::assertSame('theme=dark; Path=/', $cookies[0]);
        self::assertStringStartsWith('ract_session=', $cookies[1]);
    }

    public function testValidationExceptionsBecomeJsonResponses(): void
    {
        $this->router->post('/users', static function (Request $request): array {
            return $request->validate([
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ]);
        });

        $response = $this->app->handle(new Request(
            'POST',
            '/users',
            data: ['email' => 'invalid', 'age' => '16'],
            headers: ['Accept' => 'application/json'],
        ));
        $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->statusCode());
        self::assertSame('The given data was invalid.', $payload['message']);
        self::assertSame(['email', 'age'], array_keys($payload['errors']));
    }

    public function testBrowserValidationRedirectsWithErrorsAndSafeOldInput(): void
    {
        $driver = new FileSessionDriver($this->sessionDirectory);
        $sessions = new SessionManager($driver);
        $this->app->middleware(new StartSession($sessions, $this->app->container()));
        $this->router->post('/register', static function (Request $request): array {
            return $request->validate([
                'email' => 'required|email',
                'password' => 'required|confirmed',
            ]);
        });

        $response = $this->app->handle(new Request(
            'POST',
            '/register',
            data: [
                'email' => 'invalid',
                'password' => 'top-secret',
                'password_confirmation' => 'different',
            ],
            headers: [
                'Host' => 'example.test',
                'Referer' => 'https://example.test/register?step=account',
            ],
        ));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/register?step=account', $response->header('Location'));
        self::assertMatchesRegularExpression('/ract_session=([a-f0-9]{64})/', (string) $response->header('Set-Cookie'));
        preg_match('/ract_session=([a-f0-9]{64})/', (string) $response->header('Set-Cookie'), $matches);
        $payload = $driver->read($matches[1]);

        self::assertSame(['email', 'password'], array_keys($payload['data']['_errors']));
        self::assertSame(['email' => 'invalid'], $payload['data']['_old_input']);
    }
}
