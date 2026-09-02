<?php

declare(strict_types=1);

namespace Ract\Console;

final class Input
{
    /** @var list<string> */
    private array $arguments = [];

    /** @var array<string, string|bool> */
    private array $options = [];

    /** @param list<string> $tokens */
    public function __construct(array $tokens = [])
    {
        foreach ($tokens as $token) {
            if (!str_starts_with($token, '--')) {
                $this->arguments[] = $token;
                continue;
            }

            $option = substr($token, 2);
            $separator = strpos($option, '=');

            if ($separator === false) {
                $this->options[$option] = true;
                continue;
            }

            $this->options[substr($option, 0, $separator)] = substr($option, $separator + 1);
        }
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
