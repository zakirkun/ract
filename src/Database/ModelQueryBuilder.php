<?php

declare(strict_types=1);

namespace Ract\Database;

final class ModelQueryBuilder
{
    /** @var list<string> */
    private array $eagerLoads = [];

    public function __construct(
        private readonly Model $model,
        private readonly QueryBuilder $query,
    ) {
    }

    public function query(): QueryBuilder
    {
        return $this->query;
    }

    public function select(string ...$columns): self
    {
        $this->query->select(...$columns);

        return $this;
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

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        $this->query->whereNotIn($column, $values);

        return $this;
    }

    /** @param string|list<string> $relations */
    public function with(string|array $relations): self
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        foreach ($relations as $relation) {
            if ($relation === '') {
                throw new \InvalidArgumentException('Eager-loaded relation names cannot be empty.');
            }

            if (!in_array($relation, $this->eagerLoads, true)) {
                $this->eagerLoads[] = $relation;
            }
        }

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
        $models = array_map(
            fn (array $attributes): Model => $this->model->newFromBuilder($attributes),
            $this->query->get(),
        );

        EagerLoader::load($models, $this->eagerLoads);

        return $models;
    }

    public function first(): ?Model
    {
        $attributes = $this->query->first();
        $model = $attributes === null ? null : $this->model->newFromBuilder($attributes);

        if ($model !== null) {
            EagerLoader::load([$model], $this->eagerLoads);
        }

        return $model;
    }

    public function find(int|string $id): ?Model
    {
        $attributes = $this->query->find($id, $this->model->getKeyName());
        $model = $attributes === null ? null : $this->model->newFromBuilder($attributes);

        if ($model !== null) {
            EagerLoader::load([$model], $this->eagerLoads);
        }

        return $model;
    }

    /** @return list<mixed>|array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        return $this->query->pluck($column, $key);
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
