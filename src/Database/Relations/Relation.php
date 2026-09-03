<?php

declare(strict_types=1);

namespace Ract\Database\Relations;

use Ract\Database\Model;
use Ract\Database\ModelQueryBuilder;

abstract class Relation
{
    public function __construct(
        protected readonly ModelQueryBuilder $query,
        protected readonly Model $parent,
    ) {
    }

    public function query(): ModelQueryBuilder
    {
        return $this->query;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $this->query->where($column, $operator);
        } else {
            $this->query->where($column, $operator, $value);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        $this->query->latest($column);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query->limit($limit);

        return $this;
    }

    abstract public function getResults(): mixed;

    /** @param list<Model> $models */
    abstract public function eagerLoad(array $models, string $relation): void;

    protected static function dictionaryKey(mixed $value): string
    {
        if (is_int($value)
            || (is_string($value) && preg_match('/^-?(?:0|[1-9]\d*)$/D', $value) === 1)
        ) {
            return 'integer:' . ((string) $value === '-0' ? '0' : (string) $value);
        }

        return is_scalar($value) || $value === null
            ? get_debug_type($value) . ':' . (string) $value
            : serialize($value);
    }
}
