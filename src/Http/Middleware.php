<?php

declare(strict_types=1);

namespace Ract\Http;

use Closure;

interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response;
}
