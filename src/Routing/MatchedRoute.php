<?php

declare(strict_types=1);

namespace Ract\Routing;

final class MatchedRoute
{
    /** @param array<string, string> $parameters */
    public function __construct(
        private readonly Route $route,
        private readonly array $parameters,
    ) {
    }

    public function route(): Route
    {
        return $this->route;
    }

    /** @return array<string, string> */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
