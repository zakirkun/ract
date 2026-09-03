<?php

declare(strict_types=1);

namespace Ract\Http\Middleware;

use Closure;
use Ract\Container\Container;
use Ract\Http\Middleware;
use Ract\Http\Request;
use Ract\Http\Response;
use Ract\Session\Session;
use Ract\Session\SessionManager;

final class StartSession implements Middleware
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly Container $container,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->sessions->start($request);
        $request->setSession($session);
        $this->container->instance(Session::class, $session);
        $response = $next($request);
        $this->sessions->save($session);
        $response->addHeader('Set-Cookie', $this->sessions->cookieHeader($session));

        return $response;
    }
}
