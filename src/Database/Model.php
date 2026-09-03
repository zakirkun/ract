<?php

declare(strict_types=1);

namespace Ract\Database;

use BadMethodCallException;
use JsonSerializable;
use LogicException;
use Ract\Database\Relations\BelongsTo;
use Ract\Database\Relations\BelongsToMany;
use Ract\Database\Relations\HasMany;
use Ract\Database\Relations\HasOne;
use Ract\Database\Relations\Relation;

abstract class Model implements JsonSerializable
{
    private static ?DatabaseManager $resolver = null;

    protected ?string $table = null;

    protected ?string $connection = null;

    protected string $primaryKey = 'id';

    protected bool $incrementing = true;

    protected bool $timestamps = true;

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = ['*'];

    /** @var array<string, string> */
    protected array $casts = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, mixed> */
    private array $original = [];

    /** @var array<string, mixed> */
    private array $relations = [];

    private bool $recordExists = false;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }
    }

    public static function setConnectionResolver(DatabaseManager $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function unsetConnectionResolver(): void
    {
        self::$resolver = null;
    }

    public static function query(): ModelQueryBuilder
    {
        return (new static())->newQuery();
    }

    /** @return list<static> */
    public static function all(): array
    {
        /** @var list<static> $models */
        $models = static::query()->get();

        return $models;
    }

    public static function find(int|string $id): ?static
    {
        /** @var static|null $model */
        $model = static::query()->find($id);

        return $model;
    }

    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new ModelNotFoundException(static::class, $id);
        }

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /** @param list<mixed> $arguments */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $query = static::query();

        if (!is_callable([$query, $method])) {
            throw new BadMethodCallException(sprintf('Method "%s::%s" does not exist.', static::class, $method));
        }

        return $query->{$method}(...$arguments);
    }

    public function newQuery(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(
            $this,
            $this->database()->table($this->getTable(), $this->connection),
        );
    }

    /** @param array<string, mixed> $attributes */
    public function newFromBuilder(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->recordExists = true;
        $model->syncOriginal();

        return $model;
    }

    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $class = strrchr(static::class, '\\');
        $base = $class === false ? static::class : substr($class, 1);
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $base));

        if (preg_match('/[^aeiou]y$/', $snake) === 1) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $snake) === 1) {
            return $snake . 'es';
        }

        return $snake . 's';
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getForeignKey(): string
    {
        return self::snakeCase(self::classBasename(static::class)) . '_' . $this->getKeyName();
    }

    public function exists(): bool
    {
        return $this->recordExists;
    }

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!$this->isFillable($key)) {
                throw new MassAssignmentException(sprintf(
                    'Attribute "%s" is not mass assignable on %s.',
                    $key,
                    static::class,
                ));
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            if ($value === null || !isset($this->casts[$key])) {
                return $value;
            }

            return match ($this->casts[$key]) {
                'int', 'integer' => (int) $value,
                'real', 'float', 'double' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                'array', 'json' => is_string($value)
                    ? json_decode($value, true, 512, JSON_THROW_ON_ERROR)
                    : (array) $value,
                'string' => (string) $value,
                default => $value,
            };
        }

        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->relation($key);
            $this->relations[$key] = $relation->getResults();

            return $this->relations[$key];
        }

        return null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if (
            $this->recordExists
            && $key === $this->primaryKey
            && array_key_exists($this->primaryKey, $this->original)
            && $this->original[$this->primaryKey] !== $value
        ) {
            throw new LogicException(sprintf('The primary key of an existing %s model cannot be changed.', static::class));
        }

        $cast = $this->casts[$key] ?? null;

        $this->attributes[$key] = match ($cast) {
            'bool', 'boolean' => $value === null ? null : (bool) $value,
            'array', 'json' => $value === null || is_string($value)
                ? $value
                : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => $value,
        };

        return $this;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes)
            || ($this->relationLoaded($key) && $this->relations[$key] !== null);
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /** @param string|list<string> $relations */
    public function load(string|array $relations): static
    {
        EagerLoader::load([$this], is_string($relations) ? [$relations] : $relations);

        return $this;
    }

    public function relation(string $name): Relation
    {
        if ($name === '' || !method_exists($this, $name)) {
            throw new LogicException(sprintf('Relation "%s" is not defined on %s.', $name, static::class));
        }

        $relation = $this->{$name}();

        if (!$relation instanceof Relation) {
            throw new LogicException(sprintf('Relation method %s::%s must return a relation.', static::class, $name));
        }

        return $relation;
    }

    public function save(): bool
    {
        $this->updateTimestamps();
        $query = $this->newQuery();

        if (!$this->recordExists) {
            $attributes = $this->attributes;

            if ($this->incrementing && !array_key_exists($this->primaryKey, $attributes)) {
                $this->attributes[$this->primaryKey] = $query->query()->insertGetId($attributes, $this->primaryKey);
            } else {
                $query->query()->insert($attributes);
            }

            $this->recordExists = true;
            $this->syncOriginal();

            return true;
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new LogicException(sprintf('Existing model %s has no primary key.', static::class));
        }

        $dirty = $this->dirtyAttributes();
        unset($dirty[$this->primaryKey]);

        if ($dirty !== []) {
            $query->where($this->primaryKey, $key)->update($dirty);
        }

        $this->syncOriginal();

        return true;
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->recordExists || $this->getKey() === null) {
            return false;
        }

        $deleted = $this->newQuery()
            ->where($this->primaryKey, $this->getKey())
            ->delete() > 0;

        if ($deleted) {
            $this->recordExists = false;
        }

        return $deleted;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $visited = [];

        return $this->toArrayWithVisited($visited);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param class-string<Model> $related */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();

        return new HasOne(
            $instance->newQuery(),
            $this,
            $foreignKey ?? $this->getForeignKey(),
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = new $related();

        return new HasMany(
            $instance->newQuery(),
            $this,
            $foreignKey ?? $this->getForeignKey(),
            $localKey ?? $this->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? self::classBasename($related);

        return new BelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey ?? self::snakeCase($caller) . '_' . $instance->getKeyName(),
            $ownerKey ?? $instance->getKeyName(),
        );
    }

    /** @param class-string<Model> $related */
    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
    ): BelongsToMany {
        $instance = new $related();
        $models = [
            self::snakeCase(self::classBasename(static::class)),
            self::snakeCase(self::classBasename($related)),
        ];
        sort($models);

        return new BelongsToMany(
            $instance->newQuery(),
            $this,
            $this->database()->table($table ?? implode('_', $models), $this->connection),
            $foreignPivotKey ?? $this->getForeignKey(),
            $relatedPivotKey ?? $instance->getForeignKey(),
            $parentKey ?? $this->getKeyName(),
            $relatedKey ?? $instance->getKeyName(),
        );
    }

    private function database(): DatabaseManager
    {
        if (self::$resolver === null) {
            throw new LogicException('No model database connection resolver has been configured.');
        }

        return self::$resolver;
    }

    /** @param array<int, true> $visited @return array<string, mixed> */
    private function toArrayWithVisited(array &$visited): array
    {
        $values = [];

        foreach (array_keys($this->attributes) as $key) {
            $values[$key] = $this->getAttribute($key);
        }

        $id = spl_object_id($this);

        if (isset($visited[$id])) {
            return $values;
        }

        $visited[$id] = true;

        foreach ($this->relations as $key => $value) {
            $values[$key] = $this->serializeRelation($value, $visited);
        }

        return $values;
    }

    /** @param array<int, true> $visited */
    private function serializeRelation(mixed $value, array &$visited): mixed
    {
        if ($value instanceof self) {
            return $value->toArrayWithVisited($visited);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->serializeRelation($item, $visited), $value);
        }

        return $value;
    }

    /** @param class-string $class */
    private static function classBasename(string $class): string
    {
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }

    private static function snakeCase(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        return $this->fillable === [] && !in_array('*', $this->guarded, true) && !in_array($key, $this->guarded, true);
    }

    private function updateTimestamps(): void
    {
        if (!$this->timestamps) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->attributes['updated_at'] = $now;

        if (!$this->recordExists && !array_key_exists('created_at', $this->attributes)) {
            $this->attributes['created_at'] = $now;
        }
    }

    /** @return array<string, mixed> */
    private function dirtyAttributes(): array
    {
        return array_filter(
            $this->attributes,
            fn (mixed $value, string $key): bool => !array_key_exists($key, $this->original)
                || $this->original[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }
}
