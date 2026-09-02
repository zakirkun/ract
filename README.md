# Ract

Ract is a lightweight PHP MVC framework inspired by the direct, approachable workflow of CodeIgniter 3. It keeps controllers, routes, configuration, and views explicit while using PHP 8.1 types and Composer PSR-4 autoloading.

> Ract is currently an MVC MVP. It is a clean foundation for an application or for adding framework modules; it is not yet a drop-in replacement for CodeIgniter 3.

## Requirements

- PHP 8.1 or newer
- Composer

## Quick start

```bash
composer install
php bin/ract serve
```

Open <http://localhost:8080>. The sample application also includes:

- `GET /hello/{name}` — a dynamic controller route
- `GET /api/status` — a JSON response

Use a different address if needed:

```bash
php bin/ract serve 127.0.0.1 9000
```

For Apache or Nginx, set the document root to `public/` and rewrite non-file requests to `public/index.php`.

## Project layout

```text
app/
├── Config/app.php          Application configuration
├── Controllers/            Application controllers
├── Views/                  PHP view templates and layouts
└── routes.php              Route definitions
bin/ract                    Framework CLI
bootstrap/app.php           Composer and application bootstrap
public/index.php            HTTP front controller
public/router.php           PHP development-server router
src/                        Ract framework source
tests/                      PHPUnit test suite
```

## Request lifecycle

1. `public/index.php` loads `bootstrap/app.php`.
2. `Application` creates a `Request` from PHP globals.
3. `Router` matches the HTTP method and normalized URI.
4. The route handler or controller action runs with URI parameters.
5. The result is normalized to a `Response` and sent.
6. HTTP and application exceptions become HTML error responses.

## Routing

Routes are registered in `app/routes.php`:

```php
<?php

use App\Controllers\ArticleController;
use Ract\Routing\Router;

return static function (Router $router): void {
    $router->get('/articles', [ArticleController::class, 'index'])
        ->name('articles.index');

    $router->get('/articles/{id:\d+}', [ArticleController::class, 'show'])
        ->name('articles.show');

    $router->post('/articles', [ArticleController::class, 'store']);
};
```

Available methods are `get`, `post`, `put`, `patch`, `delete`, `options`, `match`, and `any`. A `{name}` segment matches one URI segment. Use `{name:expression}` to add a regular-expression constraint. A trailing slash is normalized, and `HEAD` requests may match `GET` routes.

List all registered routes with:

```bash
php bin/ract routes
```

## Controllers

Controllers extend `Ract\Controller`. They receive the current request, configuration repository, and view renderer through the base class.

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Ract\Controller;
use Ract\Http\Response;

final class ArticleController extends Controller
{
    public function show(string $id): Response
    {
        return $this->view('articles/show', [
            'id' => $id,
            'preview' => $this->request->query('preview', false),
        ], 'layouts/main');
    }

    public function store(): Response
    {
        return $this->json([
            'title' => $this->request->post('title'),
        ], 201);
    }
}
```

The base controller provides `view()`, `json()`, and `redirect()` response helpers. A route closure may directly return:

- a `Ract\Http\Response`;
- an array, converted to JSON;
- a string, converted to HTML; or
- `null`, converted to a `204 No Content` response.

## Requests and responses

`Ract\Http\Request` offers:

```php
$this->request->method();
$this->request->path();
$this->request->query('page', 1);
$this->request->post('title');
$this->request->input('title');
$this->request->header('Authorization');
$this->request->json();
$this->request->files();
$this->request->bearerToken();
$this->request->isAjax();
```

Create standalone responses with:

```php
use Ract\Http\Response;

Response::html('<h1>Created</h1>', 201);
Response::json(['created' => true], 201);
Response::redirect('/articles');
```

## Views

Views are PHP files under `app/Views`. Data keys become local variables. Always escape untrusted output with the global `e()` helper:

```php
<h1><?= e($title) ?></h1>
```

Pass a layout as the third argument to the controller's `view()` method. The rendered child view is available to the layout as `$content`; it should be printed without escaping because the child template has already handled its own values.

```php
return $this->view('articles/show', $data, 'layouts/main');
```

## Configuration

Every PHP file in `app/Config` must return an array. Its filename becomes the top-level configuration key. For example, values in `app/Config/app.php` are read with:

```php
$this->config->get('app.name');
$this->config->get('app.timezone', 'UTC');
```

The sample configuration supports `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, and `APP_URL` environment variables. Debug mode defaults to enabled for local development. Set `APP_DEBUG=0` in production so exception details are not rendered.

## Errors

Missing routes return `404`; method mismatches return `405` with an `Allow` header; uncaught errors return `500`. Customize the templates in `app/Views/errors`. Throw `Ract\Exception\HttpException` (or a subclass) when an action needs a specific HTTP error response.

## Testing

```bash
composer test
```

The suite covers routing, HTTP primitives, configuration, rendering, controller dispatch, and safe exception responses.

## CLI

```text
php bin/ract help
php bin/ract routes
php bin/ract serve [host] [port]
```

## MVP scope and roadmap

The current core intentionally does not include a database layer, query builder, sessions, validation, middleware, cache, migrations, or a CI3 compatibility facade. Those can be introduced as independent modules without coupling them to the routing and HTTP core.

Good next milestones are:

1. service container and middleware pipeline;
2. database connections and query builder;
3. session, CSRF, and validation services;
4. command generators and migrations;
5. cache, logging, and package publishing.

## License

Ract is released under the MIT License.
