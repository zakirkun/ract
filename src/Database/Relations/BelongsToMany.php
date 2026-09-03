<?php

declare(strict_types=1);

namespace Ract\Database\Relations;

use Ract\Database\Model;
use Ract\Database\ModelQueryBuilder;
use Ract\Database\QueryBuilder;

final class BelongsToMany extends Relation
{
    public function __construct(
        ModelQueryBuilder $query,
        Model $parent,
        private readonly QueryBuilder $pivot,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        private readonly string $parentKey,
        private readonly string $relatedKey,
    ) {
        parent::__construct($query, $parent);
    }

    /** @return list<Model> */
    public function getResults(): array
    {
        $key = $this->parent->getAttribute($this->parentKey);

        if ($key === null) {
            return [];
        }

        $relatedIds = $this->pivot
            ->where($this->foreignPivotKey, $key)
            ->pluck($this->relatedPivotKey);

        return $relatedIds === []
            ? []
            : $this->query->whereIn($this->relatedKey, array_values($relatedIds))->get();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relation): void
    {
        $parentKeys = [];

        foreach ($models as $model) {
            $model->setRelation($relation, []);
            $key = $model->getAttribute($this->parentKey);

            if ($key !== null) {
                $parentKeys[self::dictionaryKey($key)] = $key;
            }
        }

        if ($parentKeys === []) {
            return;
        }

        $pivotRows = $this->pivot
            ->whereIn($this->foreignPivotKey, array_values($parentKeys))
            ->get([$this->foreignPivotKey, $this->relatedPivotKey]);
        $relatedIds = [];

        foreach ($pivotRows as $pivotRow) {
            $id = $pivotRow[$this->relatedPivotKey];
            $relatedIds[self::dictionaryKey($id)] = $id;
        }

        if ($relatedIds === []) {
            return;
        }

        $relatedByKey = [];

        foreach ($this->query->whereIn($this->relatedKey, array_values($relatedIds))->get() as $related) {
            $relatedByKey[self::dictionaryKey($related->getAttribute($this->relatedKey))] = $related;
        }

        $groups = [];

        foreach ($pivotRows as $pivotRow) {
            $parentKey = self::dictionaryKey($pivotRow[$this->foreignPivotKey]);
            $relatedKey = self::dictionaryKey($pivotRow[$this->relatedPivotKey]);

            if (isset($relatedByKey[$relatedKey])) {
                $groups[$parentKey][] = $relatedByKey[$relatedKey];
            }
        }

        foreach ($models as $model) {
            $key = self::dictionaryKey($model->getAttribute($this->parentKey));
            $model->setRelation($relation, $groups[$key] ?? []);
        }
    }
}
