<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Ract\Application;
use Ract\Config\Config;
use Ract\Console\BufferedOutput;
use Ract\Console\Kernel;
use Ract\Routing\Router;
use Ract\View\View;

final class ConsoleTest extends TestCase
{
    private string $root;

    private Kernel $kernel;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-console-' . bin2hex(random_bytes(6));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views', 0775, true);
        $app = new Application(
            $this->root,
            new Config(['app' => ['debug' => false, 'timezone' => 'UTC']]),
            new Router(),
            new View($this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views'),
        );
        $this->kernel = new Kernel($app);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testItGeneratesModelsAndRefusesToOverwriteThemWithoutForce(): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle(['ract', 'make:model', 'Post'], $output));
        $file = $this->root . '/app/Models/Post.php';
        self::assertFileExists($file);
        self::assertStringContainsString('final class Post extends \\Ract\\Database\\Model', (string) file_get_contents($file));

        self::assertSame(1, $this->kernel->handle(['ract', 'make:model', 'Post'], $output));
        self::assertStringContainsString('already exists', $output->contents());
        self::assertSame(0, $this->kernel->handle(['ract', 'make:model', 'Post', '--force'], $output));
    }

    public function testItGeneratesAResourceController(): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle([
            'ract',
            'make:controller',
            'PostController',
            '--resource',
            '--model=Post',
        ], $output));

        $contents = (string) file_get_contents($this->root . '/app/Controllers/PostController.php');
        self::assertStringContainsString('public function index(): \\Ract\\Http\\Response', $contents);
        self::assertStringContainsString('public function destroy(string $id): \\Ract\\Http\\Response', $contents);
        self::assertStringContainsString('\\App\\Models\\Post::findOrFail($id)', $contents);
    }

    public function testCrudGenerationCreatesModelControllerMigrationAndRoutes(): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle([
            'ract',
            'make:crud',
            'Post',
            '--fields=title:string,published:boolean',
        ], $output));

        self::assertFileExists($this->root . '/app/Models/Post.php');
        self::assertFileExists($this->root . '/app/Controllers/PostController.php');
        self::assertFileExists($this->root . '/app/Routes/posts.php');
        self::assertCount(1, glob($this->root . '/database/migrations/*_create_posts_table.php') ?: []);

        $model = (string) file_get_contents($this->root . '/app/Models/Post.php');
        $routes = (string) file_get_contents($this->root . '/app/Routes/posts.php');
        self::assertStringContainsString("protected array \$fillable = ['title', 'published'];", $model);
        self::assertStringContainsString("'published' => 'boolean'", $model);
        self::assertStringContainsString("->post('/posts'", $routes);
        self::assertStringContainsString("->delete('/posts/{id:\\d+}'", $routes);
    }

    public function testGeneratedClassesDoNotCollideWithFrameworkClassNames(): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle(['ract', 'make:model', 'Model'], $output));
        self::assertSame(0, $this->kernel->handle(['ract', 'make:controller', 'Controller'], $output));
        self::assertSame(0, $this->kernel->handle([
            'ract',
            'make:crud',
            'Response',
            '--fields=title:string',
        ], $output));

        $model = (string) file_get_contents($this->root . '/app/Models/Model.php');
        $controller = (string) file_get_contents($this->root . '/app/Controllers/Controller.php');
        $resource = (string) file_get_contents($this->root . '/app/Controllers/ResponseController.php');

        self::assertStringContainsString('final class Model extends \\Ract\\Database\\Model', $model);
        self::assertStringContainsString('final class Controller extends \\Ract\\Controller', $controller);
        self::assertStringContainsString('\\App\\Models\\Response::all()', $resource);
        self::assertStringContainsString('public function index(): \\Ract\\Http\\Response', $resource);
    }

    public function testItReportsInvalidGeneratorInputWithoutWritingFiles(): void
    {
        $output = new BufferedOutput();

        self::assertSame(1, $this->kernel->handle(['ract', 'make:model', '../Unsafe'], $output));
        self::assertStringContainsString('Invalid class name', $output->contents());
        self::assertDirectoryDoesNotExist($this->root . '/app/Models');
    }

    public function testItRejectsReservedClassNamesWithoutWritingFiles(): void
    {
        $output = new BufferedOutput();

        self::assertSame(1, $this->kernel->handle(['ract', 'make:model', 'Class'], $output));
        self::assertStringContainsString('reserved PHP name', $output->contents());
        self::assertDirectoryDoesNotExist($this->root . '/app/Models');
    }

    public function testItRejectsSchemaManagedCrudFieldsWithoutWritingFiles(): void
    {
        $output = new BufferedOutput();

        self::assertSame(1, $this->kernel->handle([
            'ract',
            'make:crud',
            'Post',
            '--fields=id:integer,title:string',
        ], $output));
        self::assertStringContainsString('managed by generated migrations', $output->contents());
        self::assertDirectoryDoesNotExist($this->root . '/app/Models');
    }

    public function testCrudGenerationChecksEveryConflictBeforeWritingFiles(): void
    {
        $controller = $this->root . '/app/Controllers/PostController.php';
        mkdir(dirname($controller), 0775, true);
        file_put_contents($controller, 'existing controller');
        $output = new BufferedOutput();

        self::assertSame(1, $this->kernel->handle([
            'ract',
            'make:crud',
            'Post',
            '--fields=title:string',
        ], $output));

        self::assertStringContainsString('already exists', $output->contents());
        self::assertFileDoesNotExist($this->root . '/app/Models/Post.php');
        self::assertFileDoesNotExist($this->root . '/app/Routes/posts.php');
        self::assertSame([], glob($this->root . '/database/migrations/*.php') ?: []);
        self::assertSame('existing controller', file_get_contents($controller));
    }

    public function testHelpListsTheLaravelInspiredCommands(): void
    {
        $output = new BufferedOutput();

        self::assertSame(0, $this->kernel->handle(['ract', 'help'], $output));
        self::assertStringContainsString('make:model', $output->contents());
        self::assertStringContainsString('make:crud', $output->contents());
        self::assertStringContainsString('migrate:rollback', $output->contents());
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
