<?php

declare(strict_types=1);

namespace Ract\Database;

final class ModelQueryBuilder
{
    public function __construct(
        private readonly Model $model,
        private readonly QueryBuilder $query,
    ) {
    }

    public function query(): QueryBuilder
    {
        return $this->query;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->query->where($column, $operator);
        } else {
            $this->query->where($column, $operator, $value);
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        $this->query->latest($column);

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->query->offset($offset);

        return $this;
    }

    /** @return list<Model> */
    public function get(): array
    {
        return array_map(
            fn (array $attributes): Model => $this->model->newFromBuilder($attributes),
            $this->query->get(),
        );
    }

    public function first(): ?Model
    {
        $attributes = $this->query->first();

        return $attributes === null ? null : $this->model->newFromBuilder($attributes);
    }

    public function find(int|string $id): ?Model
    {
        $attributes = $this->query->find($id, $this->model->getKeyName());

        return $attributes === null ? null : $this->model->newFromBuilder($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Model
    {
        $class = $this->model::class;
        /** @var Model $model */
        $model = new $class();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }
}
