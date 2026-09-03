<?php

declare(strict_types=1);

namespace Ract\Database\Relations;

use Ract\Database\Model;

final class HasOne extends Relation
{
    public function __construct(
        \Ract\Database\ModelQueryBuilder $query,
        Model $parent,
        private readonly string $foreignKey,
        private readonly string $localKey,
    ) {
        parent::__construct($query, $parent);
    }

    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->localKey);

        return $key === null ? null : $this->query->where($this->foreignKey, $key)->first();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relation): void
    {
        $keys = [];

        foreach ($models as $model) {
            $model->setRelation($relation, null);
            $key = $model->getAttribute($this->localKey);

            if ($key !== null) {
                $keys[self::dictionaryKey($key)] = $key;
            }
        }

        if ($keys === []) {
            return;
        }

        $matches = [];

        foreach ($this->query->whereIn($this->foreignKey, array_values($keys))->get() as $related) {
            $key = self::dictionaryKey($related->getAttribute($this->foreignKey));
            $matches[$key] ??= $related;
        }

        foreach ($models as $model) {
            $key = self::dictionaryKey($model->getAttribute($this->localKey));
            $model->setRelation($relation, $matches[$key] ?? null);
        }
    }
}
