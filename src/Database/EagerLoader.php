<?php

declare(strict_types=1);

namespace Ract\Database;

use InvalidArgumentException;

final class EagerLoader
{
    /**
     * @param list<Model> $models
     * @param list<string> $relations
     */
    public static function load(array $models, array $relations): void
    {
        if ($models === [] || $relations === []) {
            return;
        }

        $tree = [];

        foreach ($relations as $relation) {
            if ($relation === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $relation) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid eager-loaded relation "%s".', $relation));
            }

            $branch = &$tree;

            foreach (explode('.', $relation) as $segment) {
                $branch[$segment] ??= [];
                $branch = &$branch[$segment];
            }

            unset($branch);
        }

        self::loadTree($models, $tree);
    }

    /**
     * @param list<Model> $models
     * @param array<string, array<string, mixed>> $tree
     */
    private static function loadTree(array $models, array $tree): void
    {
        foreach ($tree as $name => $nested) {
            $models[0]->relation($name)->eagerLoad($models, $name);

            if ($nested === []) {
                continue;
            }

            $relatedModels = [];

            foreach ($models as $model) {
                $value = $model->getRelation($name);

                if ($value instanceof Model) {
                    $relatedModels[] = $value;
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $related) {
                        if ($related instanceof Model) {
                            $relatedModels[] = $related;
                        }
                    }
                }
            }

            if ($relatedModels !== []) {
                self::loadTree($relatedModels, $nested);
            }
        }
    }
}
