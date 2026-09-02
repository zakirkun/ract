# Ract

Ract is a lightweight PHP 8.1 MVC framework inspired by CodeIgniter's explicit application structure and Laravel's container, providers, facades, active record, migrations, generators, and scheduler. The repository contains both the framework under `src/` and a runnable sample application under `app/`.

## Requirements

- PHP 8.1 or newer
- Composer
- PDO plus `pdo_sqlite`, `pdo_mysql`, or `pdo_pgsql` for the configured database

## Quick start

```bash
composer install
php bin/ract serve
```

Open <http://localhost:8080>. The sample application includes `GET /hello/{name}` and `GET /api/status`. To use another address:

```bash
php bin/ract serve 127.0.0.1 9000
```

For Apache or Nginx, serve only `public/` and rewrite non-file requests to `public/index.php`.

## Project layout

```text
app/
├── Config/                 Array-based application and database configuration
├── Console/Kernel.php      Application commands and scheduled tasks
├── Controllers/            HTTP controllers
├── Models/                 Generated active-record models
├── Routes/                 Generated and modular route files
├── Views/                  PHP templates, layouts, and error pages
└── routes.php              Main routes and modular-route loader
bin/ract                    Framework CLI
bootstrap/app.php           Composer, application, providers, and route bootstrap
database/migrations/        Timestamped database migrations
public/                     HTTP front controller and development router
src/                        Framework source under the Ract namespace
tests/                      PHPUnit suite and fixtures
```

## Application lifecycle and container

`bootstrap/app.php` creates `Application`, which loads every PHP array in `app/Config`, registers `app.providers`, boots those providers, and then loads the routes. A request is bound into the container before dispatch. Controller constructors, controller actions, and route closures can therefore receive class-typed dependencies:

```php
use Ract\Database\DatabaseManager;
use Ract\Http\Request;

$router->get('/posts/{id}', static function (
    Request $request,
    DatabaseManager $database,
    string $id,
): array {
    return $database->table('posts')->find($id) ?? [];
});
```

The container supports `bind()`, `singleton()`, `instance()`, `alias()`, `make()`, and `call()`. Add service-provider class names to `providers` in `app/Config/app.php`; provider `register()` methods define bindings and all providers are registered before their `boot()` methods run. `Ract\Support\Facades\App`, `DB`, and `Schema` proxy to the active container.

## Routing and controllers

`app/routes.php` registers main routes and loads every `app/Routes/*.php` file in filename order. Each route file must return a callable receiving `Ract\Routing\Router`. Available methods are `get`, `post`, `put`, `patch`, `delete`, `options`, `match`, and `any`:

```php
use App\Controllers\ArticleController;
use Ract\Routing\Router;

return static function (Router $router): void {
    $router->get('/articles', [ArticleController::class, 'index'])
        ->name('articles.index');
    $router->get('/articles/{id:\d+}', [ArticleController::class, 'show'])
        ->name('articles.show');
};
```

Controllers extend `Ract\Controller`. Its `view()`, `json()`, and `redirect()` helpers return responses; handlers must return a `Response`, array (JSON), string (HTML), or `null` (204). List routes with `php bin/ract routes`.

## Requests, responses, and views

`Request` exposes `query()`, `post()`, `input()`, `header()`, `json()`, `files()`, `bearerToken()`, and `isAjax()`. `input()` reads form, JSON, and query data; JSON and URL-encoded bodies are available for PUT and PATCH CRUD requests. Standalone responses use `Response::html()`, `Response::json()`, or `Response::redirect()`.

Views are PHP files under `app/Views`. Data keys become local variables. Escape untrusted output with `e()`:

```php
<h1><?= e($title) ?></h1>
```

When rendering a layout, the child view is available as `$content`; the layout prints it unescaped because the child owns value escaping.

## Database, query builder, and models

The default connection is SQLite at `database/database.sqlite`. Configure another connection with `DB_CONNECTION=sqlite|mysql|pgsql` and `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, and optional MySQL `DB_CHARSET` environment variables.

Use constructor injection or the facade:

```php
use Ract\Database\Connection;
use Ract\Support\Facades\DB;

$published = DB::table('posts')
    ->where('published', true)
    ->latest()
    ->limit(20)
    ->get();

DB::transaction(static function (Connection $connection): void {
    $connection->table('posts')->insert(['title' => 'Inside a transaction']);
});
```

The query builder provides safe identifier quoting, bound values, `select`, `where`, null predicates, ordering, limit/offset, `get`, `first`, `find`, `value`, `count`, `exists`, `insert`, `insertGetId`, `update`, and `delete`.

Models extend `Ract\Database\Model`, infer a plural snake-case table, use `id` as their key, and maintain `created_at`/`updated_at` by default:

```php
final class Post extends Model
{
    /** @var list<string> */
    protected array $fillable = ['title', 'published'];

    /** @var array<string, string> */
    protected array $casts = ['published' => 'boolean'];
}

$post = Post::create(['title' => 'Hello', 'published' => true]);
$post->update(['title' => 'Updated']);
Post::where('published', true)->get();
Post::findOrFail($post->id); // throws an HTTP 404 when absent
```

Mass assignment rejects fields not listed in `$fillable`. Schema blueprints support IDs, strings, text, integers, big integers, booleans, decimals, datetimes, timestamps, nullable/default/unique modifiers, and timestamp pairs.

## Generators and migrations

```text
php bin/ract make:model Post [--fields=title:string,published:boolean] [--force]
php bin/ract make:controller PostController [--resource] [--model=Post] [--fields=...] [--force]
php bin/ract make:migration create_posts_table [--table=posts] [--fields=...] [--force]
php bin/ract make:crud Post --fields=title:string,published:boolean [--force]
php bin/ract migrate
php bin/ract migrate:rollback [--step=1]
```

Field definitions are comma-separated `name:type` values. Supported types are `string`, `text`, `integer`, `bigInteger`, `boolean`, `decimal`, `dateTime`, and `timestamp`; append `?` for nullable fields. Do not declare `id`, `created_at`, or `updated_at`, which generated migrations manage automatically. Generator class names cannot be PHP reserved names. `make:crud` preflights conflicts, then creates a fillable model, JSON resource controller, migration, and REST route module. Generated route modules are loaded automatically. Existing files are never replaced unless `--force` is given.

Migrations are PHP files in `database/migrations` that return a `Migration` instance. `migrate` tracks completed files in a `migrations` table; rollback without `--step` reverses the latest batch.

## Scheduler and crond

Define tasks in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('migrate')->dailyAt('02:00');
    $schedule->call(static function (): void {
        // Application task.
    })->everyFiveMinutes()->timezone('UTC')->name('application task');
}
```

Events accept five-field numeric cron expressions through `cron()`, plus `everyMinute`, five/ten/fifteen/thirty-minute, `hourly`, `daily`, `dailyAt`, `weekly`, `weeklyOn`, `weekdays`, `monthly`, and `monthlyOn` frequencies.

Run one scheduler evaluation every minute from crond:

```cron
* * * * * cd /absolute/path/to/ract && php bin/ract schedule:run >> /dev/null 2>&1
```

For development, `php bin/ract schedule:work` keeps a foreground worker running; `--sleep=1` through `--sleep=60` controls polling. `schedule:work --once` performs one evaluation. The scheduler does not provide overlap locks or distributed coordination, so configure only one worker for tasks that must not run concurrently.

## Configuration and errors

Each file in `app/Config` must return an array; its filename is the top-level key. Read values with dotted keys such as `$config->get('app.timezone', 'UTC')`. Application variables are `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, and `APP_URL`. Debug defaults to enabled; set `APP_DEBUG=0` in production.

Missing routes and models return 404, malformed JSON returns 400, method mismatches return 405 with `Allow`, and uncaught exceptions return 500. Customize `app/Views/errors`. Throw `Ract\Exception\HttpException` subclasses for intended HTTP errors. Invalid `APP_TIMEZONE` values stop bootstrap instead of silently using the process timezone. The framework currently has no validation, middleware, sessions, authentication, cache, queue, or scheduler locking layer; generated CRUD accepts only its generated fillable fields but does not validate their values.

## Testing

```bash
composer test
```

If `pdo_sqlite` is installed but disabled in `php.ini`, run the complete database suite with:

```bash
php -d extension=pdo_sqlite vendor/bin/phpunit
```

## License

Ract is released under the MIT License.
