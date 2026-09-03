<?php

declare(strict_types=1);

namespace Ract\Database\Relations;

use Ract\Database\Model;

final class HasMany extends Relation
{
    public function __construct(
        \Ract\Database\ModelQueryBuilder $query,
        Model $parent,
        private readonly string $foreignKey,
        private readonly string $localKey,
    ) {
        parent::__construct($query, $parent);
    }

    /** @return list<Model> */
    public function getResults(): array
    {
        $key = $this->parent->getAttribute($this->localKey);

        return $key === null ? [] : $this->query->where($this->foreignKey, $key)->get();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relation): void
    {
        $keys = [];

        foreach ($models as $model) {
            $model->setRelation($relation, []);
            $key = $model->getAttribute($this->localKey);

            if ($key !== null) {
                $keys[self::dictionaryKey($key)] = $key;
            }
        }

        if ($keys === []) {
            return;
        }

        $groups = [];

        foreach ($this->query->whereIn($this->foreignKey, array_values($keys))->get() as $related) {
            $groups[self::dictionaryKey($related->getAttribute($this->foreignKey))][] = $related;
        }

        foreach ($models as $model) {
            $key = self::dictionaryKey($model->getAttribute($this->localKey));
            $model->setRelation($relation, $groups[$key] ?? []);
        }
    }
}
