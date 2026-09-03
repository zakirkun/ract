<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use Ract\Http\Middleware;
use Ract\Http\Request;
use Ract\Http\Response;

final class HeaderMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->setHeader('X-Alias-Middleware', 'applied');
    }
}
