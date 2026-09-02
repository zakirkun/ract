<?php

declare(strict_types=1);

namespace Ract\Database\Schema;

final class ColumnDefinition
{
    public bool $nullable = false;

    public bool $primary = false;

    public bool $unique = false;

    public bool $autoIncrement = false;

    public bool $hasDefault = false;

    public mixed $defaultValue = null;

    /** @param array<string, int> $parameters */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly array $parameters = [],
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->defaultValue = $value;

        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;

        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }
}
