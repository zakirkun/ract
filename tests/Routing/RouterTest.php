<?php

declare(strict_types=1);

namespace Tests\Routing;

use PHPUnit\Framework\TestCase;
use Ract\Exception\MethodNotAllowedHttpException;
use Ract\Exception\NotFoundHttpException;
use Ract\Routing\Router;

final class RouterTest extends TestCase
{
    public function testItMatchesDynamicParametersAndCustomExpressions(): void
    {
        $router = new Router();
        $route = $router->get('/users/{id:\d+}', static fn (string $id): string => $id)->name('users.show');

        $matched = $router->dispatch('GET', '/users/42/');

        self::assertSame($route, $matched->route());
        self::assertSame(['id' => '42'], $matched->parameters());
        self::assertSame($route, $router->named('users.show'));
    }

    public function testItRejectsParametersThatDoNotMatchTheExpression(): void
    {
        $router = new Router();
        $router->get('/users/{id:\d+}', static fn () => null);

        $this->expectException(NotFoundHttpException::class);

        $router->dispatch('GET', '/users/not-a-number');
    }

    public function testItReportsAllowedMethodsForAMatchingPath(): void
    {
        $router = new Router();
        $router->get('/articles', static fn () => null);
        $router->post('/articles', static fn () => null);

        try {
            $router->dispatch('DELETE', '/articles');
            self::fail('A method mismatch should throw an exception.');
        } catch (MethodNotAllowedHttpException $exception) {
            self::assertSame(['GET', 'HEAD', 'POST'], $exception->allowedMethods());
            self::assertSame('GET, HEAD, POST', $exception->headers()['Allow']);
        }
    }

    public function testHeadRequestsCanUseGetRoutes(): void
    {
        $router = new Router();
        $route = $router->get('/health', static fn (): string => 'ok');

        self::assertSame($route, $router->dispatch('HEAD', '/health')->route());
    }

    public function testItOnlyReturnsDeclaredRouteParameters(): void
    {
        $router = new Router();
        $router->get('/archive/{date:(?P<year>\d{4})-\d{2}}', static fn () => null);

        $matched = $router->dispatch('GET', '/archive/2026-09');

        self::assertSame(['date' => '2026-09'], $matched->parameters());
    }

    public function testItRejectsMalformedCustomExpressionsWhenRegistered(): void
    {
        $router = new Router();

        $this->expectException(\InvalidArgumentException::class);

        $router->get('/users/{id:(}', static fn () => null);
    }

    public function testConstraintsAreAppliedToDecodedParameters(): void
    {
        $router = new Router();
        $router->get('/safe/{value:[^<>]+}', static fn () => null);

        $this->expectException(NotFoundHttpException::class);

        $router->dispatch('GET', '/safe/%3Cunsafe%3E');
    }

    public function testEncodedSlashesCannotEscapeAParameterSegment(): void
    {
        $router = new Router();
        $router->get('/files/{name}', static fn () => null);

        $this->expectException(NotFoundHttpException::class);

        $router->dispatch('GET', '/files/directory%2Ffile');
    }

    public function testCustomExpressionsCannotConsumeMultipleSegments(): void
    {
        $router = new Router();
        $router->get('/files/{name:.*}', static fn () => null);

        $this->expectException(NotFoundHttpException::class);

        $router->dispatch('GET', '/files/directory/file');
    }
}
