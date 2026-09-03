# Ract Agent Guide

## What this repository is
Ract is a small PHP 8.1+ MVC framework combining an explicit CodeIgniter-style application with Laravel-inspired container, provider, facade, database, migration, generator, and scheduler APIs. This repository contains both framework code (`src/`) and the runnable sample application (`app/`).

## Commands
Run commands from the repository root. This Windows workspace uses `composer.bat`:

- Install: `call composer.bat install --no-interaction`
- Build: none; PHP is interpreted and no build script exists.
- Test: `call composer.bat test`
- Full DB tests when SQLite is disabled in `php.ini`: `php -d extension=pdo_sqlite vendor/bin/phpunit`
- Typecheck: none configured; Composer has no PHPStan or Psalm dependency/script.
- Lint: none configured; Composer has no PHPCS or Pint dependency/script.
- CLI help: `php bin/ract help`
- Development server: `php bin/ract serve [host] [port]` (default `localhost:8080`).
- Routes: `php bin/ract routes`

PHPUnit 10.5 reads `phpunit.xml`, loads `vendor/autoload.php`, and scans `tests/`. Database tests skip when `pdo_sqlite` is unavailable.

## Layout and boundaries
- `src/`: framework code under `Ract\`; it must not depend on the sample application's `App\` namespace.
- `src/Container/` and `src/Support/`: autowiring, providers, global helpers, and facades.
- `src/Database/`: PDO connections, query/model APIs, eager loading, relations, schema blueprints, and migrations.
- `src/Console/`: commands, generators, and five-field cron scheduling.
- `src/Http/`, `src/Routing/`, and `src/View/`: requests/responses, middleware dispatch, routes, and PHP/Blade-style rendering.
- `src/Session/` and `src/Validation/`: file-backed sessions, session middleware, and request/form validation.
- `app/Controllers/`, `app/Models/`, `app/Views/`: application HTTP and persistence code.
- `app/Config/`: PHP arrays loaded by filename (`database.php` becomes `database.*`).
- `app/routes.php`: main routes plus the sorted loader for callable `app/Routes/*.php` modules.
- `app/Console/Kernel.php`: application schedule and custom command extension point.
- `database/migrations/`: migration files; the SQLite database itself is ignored.
- `bootstrap/app.php`: creates the app, boots configured providers, and loads routes.
- `public/`: HTTP front controller/router; this is the only web document root.
- `bin/ract`: instantiates `App\Console\Kernel` for all CLI commands.
- `tests/`: PHPUnit tests; reusable test classes/templates/migrations live in `tests/Fixtures/`.
- `.github/workflows/ci.yml`: PHP 8.1-8.4 test matrix and tested `v*` tag release publication.

Composer maps `Ract\` to `src/`, `App\` to `app/`, and `Tests\` to `tests/`. After moving classes, run `call composer.bat dump-autoload`.

## Conventions
- Non-template PHP files use `declare(strict_types=1);`, PSR-4 namespaces, one class per file, typed signatures/properties, and PHPDoc for array shapes/lists.
- Concrete classes are usually `final`; intentionally extensible bases (`Controller`, `Model`, `Kernel`, `Migration`) are not.
- Classes are PascalCase; methods/properties are camelCase; PHPUnit methods start with `test`; route names are dotted lowercase.
- Controllers must extend `Ract\Controller` and return responses instead of emitting output. Routes/actions may return only `Response`, array, string, or `null`.
- The container autowires class-typed constructor/action parameters. Bind interfaces/primitives in a provider; register providers in `app.providers`.
- Route parameters use `{name}` or `{name:regex}`. Modular route files return `static function (Router $router): void`.
- Middleware implements `Ract\Http\Middleware`; configure globals/aliases in `app.php`, then apply route middleware with `->middleware()` or `Router::middleware()` groups.
- Models default to plural snake-case tables, `id`, and timestamps. Declare `$fillable`; unknown mass-assigned fields throw `MassAssignmentException`.
- Relation methods return `HasOne`, `HasMany`, `BelongsTo`, or `BelongsToMany`; dotted eager loads use `with('articles.comments')` or `load()`.
- SQL identifiers are validated and quoted; values belong in query-builder bindings, never interpolated SQL.
- Migration files return an anonymous `Migration`; implement both `up(SchemaBuilder)` and `down(SchemaBuilder)`.
- Generator fields use `name:type`, comma separation, and `?` nullability. `id`/timestamp fields and PHP-reserved class names are rejected. `make:crud` requires `--fields` and preflights conflicts unless `--force` is present.
- `.blade.php` takes precedence over `.php`; `{{ }}` escapes, `{!! !!}` is raw, and compiled files belong in `storage/framework/views`.
- Plain-PHP view data becomes local variables. Escape untrusted values with `e()`; legacy layouts print child `$content` unescaped.
- Validation rules are strings/lists and nested keys use dots. JSON failures are 422; browser failures need the session middleware and a same-origin `Referer` to redirect with flash data.

## Errors, tests, and breakpoints
- Use `InvalidArgumentException` for invalid API input, focused runtime exceptions for subsystem failures, and `HttpException` subclasses for intended HTTP responses.
- `Application::handle()` converts malformed JSON to 400 and other uncaught throwables to 500. `APP_DEBUG` defaults true; production must set `APP_DEBUG=0`. Invalid `APP_TIMEZONE` values fail bootstrap.
- Tests are final `TestCase` subclasses using direct construction, `setUp()`, and `self::assert...`. HTTP application tests call `handle(new Request(...))`; they do not start a server.
- `DatabaseServiceProvider` sets the static model resolver. Boot it before using models; keep DB tests isolated and clear manually installed resolvers in `tearDown()`.
- Generated CRUD has fillable-field filtering but no validation. JSON and URL-encoded body parsing in `Request` must continue to work for PUT/PATCH.
- The sample app starts a file session globally; preserve multiple `Set-Cookie` values and never expose password-like fields through `_old_input`.
- `schedule:run` has no overlap/distributed lock. Crond should invoke it once per minute from one coordinator when concurrency matters.
- Keep encoded slash/backslash rejection, header/status validation, production-safe error pages, and HEAD body suppression intact.
