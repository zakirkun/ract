<?php

declare(strict_types=1);

namespace Ract\Database\Relations;

use Ract\Database\Model;

final class BelongsTo extends Relation
{
    public function __construct(
        \Ract\Database\ModelQueryBuilder $query,
        Model $parent,
        private readonly string $foreignKey,
        private readonly string $ownerKey,
    ) {
        parent::__construct($query, $parent);
    }

    public function getResults(): ?Model
    {
        $key = $this->parent->getAttribute($this->foreignKey);

        return $key === null ? null : $this->query->where($this->ownerKey, $key)->first();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relation): void
    {
        $keys = [];

        foreach ($models as $model) {
            $model->setRelation($relation, null);
            $key = $model->getAttribute($this->foreignKey);

            if ($key !== null) {
                $keys[self::dictionaryKey($key)] = $key;
            }
        }

        if ($keys === []) {
            return;
        }

        $matches = [];

        foreach ($this->query->whereIn($this->ownerKey, array_values($keys))->get() as $related) {
            $matches[self::dictionaryKey($related->getAttribute($this->ownerKey))] = $related;
        }

        foreach ($models as $model) {
            $key = self::dictionaryKey($model->getAttribute($this->foreignKey));
            $model->setRelation($relation, $matches[$key] ?? null);
        }
    }
}
