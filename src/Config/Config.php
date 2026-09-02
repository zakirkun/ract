<?php

declare(strict_types=1);

namespace Ract\Config;

use RuntimeException;

final class Config
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = [])
    {
    }

    public static function loadDirectory(string $directory): self
    {
        if (!is_dir($directory)) {
            return new self();
        }

        $items = [];
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];

        foreach ($files as $file) {
            $values = require $file;

            if (!is_array($values)) {
                throw new RuntimeException(sprintf('Configuration file "%s" must return an array.', $file));
            }

            $items[pathinfo($file, PATHINFO_FILENAME)] = $values;
        }

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $missing = new \stdClass();

        return $this->get($key, $missing) !== $missing;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
