<?php

declare(strict_types=1);

namespace Ract\Console;

use InvalidArgumentException;
use RuntimeException;

final class CodeGenerator
{
    /** @var list<string> */
    private const RESERVED_CLASS_NAMES = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif',
        'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval',
        'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'from', 'function',
        'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null',
        'numeric', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'resource', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true',
        'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield', 'yield_from',
    ];

    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool}> $fields
     */
    public function model(string $name, array $fields = [], bool $force = false): string
    {
        $class = $this->className($name);
        $path = $this->path('app/Models/' . $class . '.php');

        return $this->write($path, $this->modelContents($class, $fields), $force);
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool}> $fields
     */
    public function controller(
        string $name,
        bool $resource = false,
        ?string $model = null,
        array $fields = [],
        bool $force = false,
    ): string {
        $class = $this->className($name);
        $path = $this->path('app/Controllers/' . $class . '.php');
        $contents = $resource
            ? $this->resourceControllerContents($class, $this->className($model ?? $this->modelFromController($class)), $fields)
            : $this->controllerContents($class);

        return $this->write($path, $contents, $force);
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool}> $fields
     */
    public function migration(
        string $name,
        string $table,
        array $fields = [],
        bool $force = false,
    ): string {
        $migration = $this->migrationName($name);
        $table = $this->tableName($table);
        $directory = $this->path('database/migrations');
        $matches = glob($directory . DIRECTORY_SEPARATOR . '*_' . $migration . '.php') ?: [];

        if ($matches !== [] && !$force) {
            throw new RuntimeException(sprintf('Migration "%s" already exists.', $migration));
        }

        $path = $matches[0] ?? $directory . DIRECTORY_SEPARATOR . date('Y_m_d_His') . '_' . $migration . '.php';

        return $this->write($path, $this->migrationContents($table, $fields), $force);
    }

    /**
     * @param list<array{name: string, type: string, nullable: bool}> $fields
     * @return list<string>
     */
    public function crud(string $name, array $fields, bool $force = false): array
    {
        $model = $this->className($name);
        $table = $this->plural($this->snake($model));
        $controller = $model . 'Controller';
        $migration = 'create_' . $table . '_table';
        $routePath = $this->path('app/Routes/' . $table . '.php');

        if (!$force) {
            foreach ([
                $this->path('app/Models/' . $model . '.php'),
                $this->path('app/Controllers/' . $controller . '.php'),
                $routePath,
            ] as $path) {
                if (is_file($path)) {
                    throw new RuntimeException(sprintf('File "%s" already exists. Use --force to overwrite it.', $path));
                }
            }

            $migrationPattern = $this->path('database/migrations/*_' . $migration . '.php');

            if ((glob($migrationPattern) ?: []) !== []) {
                throw new RuntimeException(sprintf('Migration "%s" already exists.', $migration));
            }
        }

        $paths = [
            $this->model($model, $fields, $force),
            $this->controller($controller, true, $model, $fields, $force),
            $this->migration($migration, $table, $fields, $force),
        ];
        $paths[] = $this->write(
            $routePath,
            $this->routeContents($controller, $table),
            $force,
        );

        return $paths;
    }

    /** @return list<array{name: string, type: string, nullable: bool}> */
    public function parseFields(string $definition): array
    {
        if (trim($definition) === '') {
            return [];
        }

        $fields = [];
        $names = [];
        $allowed = ['string', 'text', 'integer', 'bigInteger', 'boolean', 'decimal', 'dateTime', 'timestamp'];

        foreach (explode(',', $definition) as $item) {
            [$name, $type] = array_pad(explode(':', trim($item), 2), 2, 'string');
            $nullable = str_ends_with($type, '?');
            $type = rtrim($type, '?');

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid field name "%s".', $name));
            }

            if (in_array(strtolower($name), ['id', 'created_at', 'updated_at'], true)) {
                throw new InvalidArgumentException(sprintf(
                    'Field "%s" is managed by generated migrations and cannot be declared.',
                    $name,
                ));
            }

            if (!in_array($type, $allowed, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported field type "%s".', $type));
            }

            if (in_array($name, $names, true)) {
                throw new InvalidArgumentException(sprintf('Field "%s" is duplicated.', $name));
            }

            $names[] = $name;
            $fields[] = ['name' => $name, 'type' => $type, 'nullable' => $nullable];
        }

        return $fields;
    }

    /** @param list<array{name: string, type: string, nullable: bool}> $fields */
    private function modelContents(string $class, array $fields): string
    {
        $fillable = implode(', ', array_map(
            static fn (array $field): string => "'" . $field['name'] . "'",
            $fields,
        ));
        $casts = [];

        foreach ($fields as $field) {
            $cast = match ($field['type']) {
                'boolean' => 'boolean',
                'integer', 'bigInteger' => 'integer',
                'decimal' => 'float',
                default => null,
            };

            if ($cast !== null) {
                $casts[] = sprintf("        '%s' => '%s',", $field['name'], $cast);
            }
        }

        $castBlock = $casts === [] ? '' : "\n\n    /** @var array<string, string> */\n    protected array \$casts = [\n"
            . implode("\n", $casts)
            . "\n    ];";

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

final class {$class} extends \\Ract\\Database\\Model
{
    /** @var list<string> */
    protected array \$fillable = [{$fillable}];{$castBlock}
}

PHP;
    }

    private function controllerContents(string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

final class {$class} extends \\Ract\\Controller
{
}

PHP;
    }

    /** @param list<array{name: string, type: string, nullable: bool}> $fields */
    private function resourceControllerContents(string $class, string $model, array $fields): string
    {
        $fieldNames = implode(', ', array_map(
            static fn (array $field): string => "'" . $field['name'] . "'",
            $fields,
        ));
        $dataBody = $fields === []
            ? '        return $this->request->post();'
            : <<<PHP
        \$missing = new \stdClass();
        \$data = [];

        foreach ([{$fieldNames}] as \$field) {
            \$value = \$this->request->input(\$field, \$missing);

            if (\$value !== \$missing) {
                \$data[\$field] = \$value;
            }
        }

        return \$data;
PHP;
        $modelClass = '\\App\\Models\\' . $model;

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

final class {$class} extends \\Ract\\Controller
{
    public function index(): \\Ract\\Http\\Response
    {
        return \$this->json({$modelClass}::all());
    }

    public function store(): \\Ract\\Http\\Response
    {
        return \$this->json({$modelClass}::create(\$this->data()), 201);
    }

    public function show(string \$id): \\Ract\\Http\\Response
    {
        return \$this->json({$modelClass}::findOrFail(\$id));
    }

    public function update(string \$id): \\Ract\\Http\\Response
    {
        \$model = {$modelClass}::findOrFail(\$id);
        \$model->update(\$this->data());

        return \$this->json(\$model);
    }

    public function destroy(string \$id): \\Ract\\Http\\Response
    {
        {$modelClass}::findOrFail(\$id)->delete();

        return new \\Ract\\Http\\Response('', 204);
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
{$dataBody}
    }
}

PHP;
    }

    /** @param list<array{name: string, type: string, nullable: bool}> $fields */
    private function migrationContents(string $table, array $fields): string
    {
        $columns = [];

        foreach ($fields as $field) {
            $line = sprintf("            \$table->%s('%s')", $field['type'], $field['name']);
            $line .= $field['nullable'] ? '->nullable();' : ';';
            $columns[] = $line;
        }

        $columnBlock = $columns === [] ? '' : implode("\n", $columns) . "\n";

        return <<<PHP
<?php

declare(strict_types=1);

use Ract\Database\Migrations\Migration;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;

return new class () extends Migration {
    public function up(SchemaBuilder \$schema): void
    {
        \$schema->create('{$table}', static function (Blueprint \$table): void {
            \$table->id();
{$columnBlock}            \$table->timestamps();
        });
    }

    public function down(SchemaBuilder \$schema): void
    {
        \$schema->dropIfExists('{$table}');
    }
};

PHP;
    }

    private function routeContents(string $controller, string $table): string
    {
        $controllerClass = '\\App\\Controllers\\' . $controller;

        return <<<PHP
<?php

declare(strict_types=1);

return static function (\\Ract\\Routing\\Router \$router): void {
    \$router->get('/{$table}', [{$controllerClass}::class, 'index'])->name('{$table}.index');
    \$router->post('/{$table}', [{$controllerClass}::class, 'store'])->name('{$table}.store');
    \$router->get('/{$table}/{id:\\d+}', [{$controllerClass}::class, 'show'])->name('{$table}.show');
    \$router->put('/{$table}/{id:\\d+}', [{$controllerClass}::class, 'update'])->name('{$table}.update');
    \$router->patch('/{$table}/{id:\\d+}', [{$controllerClass}::class, 'update']);
    \$router->delete('/{$table}/{id:\\d+}', [{$controllerClass}::class, 'destroy'])->name('{$table}.destroy');
};

PHP;
    }

    private function className(string $name): string
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '.')) {
            throw new InvalidArgumentException(sprintf('Invalid class name "%s".', $name));
        }

        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $class) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid class name "%s".', $name));
        }

        if (in_array(strtolower($class), self::RESERVED_CLASS_NAMES, true)) {
            throw new InvalidArgumentException(sprintf('Class name "%s" is a reserved PHP name.', $class));
        }

        return $class;
    }

    private function modelFromController(string $controller): string
    {
        return str_ends_with($controller, 'Controller')
            ? substr($controller, 0, -10)
            : $controller;
    }

    private function migrationName(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid migration name "%s".', $name));
        }

        return $name;
    }

    private function tableName(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid table name "%s".', $name));
        }

        return $name;
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function plural(string $value): string
    {
        if (preg_match('/[^aeiou]y$/', $value) === 1) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $value) === 1) {
            return $value . 'es';
        }

        return $value . 's';
    }

    private function path(string $relative): string
    {
        return rtrim($this->rootPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function write(string $path, string $contents, bool $force): string
    {
        if (is_file($path) && !$force) {
            throw new RuntimeException(sprintf('File "%s" already exists. Use --force to overwrite it.', $path));
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create directory "%s".', $directory));
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Could not write file "%s".', $path));
        }

        return $path;
    }
}
