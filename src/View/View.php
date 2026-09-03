<?php

declare(strict_types=1);

namespace Ract\View;

use Ract\Exception\ViewException;
use RuntimeException;
use Throwable;

final class View
{
    private readonly string $cacheDirectory;

    private readonly BladeCompiler $compiler;

    private ?string $parentView = null;

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    public function __construct(
        private readonly string $directory,
        ?string $cacheDirectory = null,
        ?BladeCompiler $compiler = null,
    ) {
        $this->cacheDirectory = $cacheDirectory
            ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-views-' . sha1($this->directory);
        $this->compiler = $compiler ?? new BladeCompiler();
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $state = [$this->parentView, $this->sections, $this->sectionStack];
        $this->parentView = null;
        $this->sections = [];
        $this->sectionStack = [];

        try {
            $content = $this->renderNamed($view, $data);
            $depth = 0;

            while ($this->parentView !== null) {
                if (++$depth > 20) {
                    throw new ViewException('Blade view inheritance exceeded 20 levels.');
                }

                $parent = $this->parentView;
                $this->parentView = null;
                $content = $this->renderNamed($parent, [...$data, 'content' => $content]);
            }

            if ($layout !== null) {
                $content = $this->renderNamed($layout, [...$data, 'content' => $content]);
            }

            return $content;
        } finally {
            [$this->parentView, $this->sections, $this->sectionStack] = $state;
        }
    }

    public function exists(string $view): bool
    {
        try {
            return is_file($this->path($view));
        } catch (ViewException) {
            return false;
        }
    }

    public function extend(string $view): void
    {
        $this->parentView = $view;
    }

    public function startSection(string $section, mixed $content = null): void
    {
        if ($section === '') {
            throw new ViewException('Blade section names cannot be empty.');
        }

        if ($content !== null) {
            $this->sections[$section] = (string) $content;
            return;
        }

        $this->sectionStack[] = $section;
        ob_start();
    }

    public function stopSection(): void
    {
        $section = array_pop($this->sectionStack);

        if ($section === null) {
            throw new ViewException('Cannot end a Blade section that was not started.');
        }

        $this->sections[$section] = (string) ob_get_clean();
    }

    public function showSection(): string
    {
        $this->stopSection();
        $section = array_key_last($this->sections);

        return $section === null ? '' : $this->sections[$section];
    }

    public function yieldContent(string $section, mixed $default = ''): string
    {
        return $this->sections[$section] ?? (string) $default;
    }

    /**
     * @param list<mixed> $arguments
     * @param array<string, mixed> $scope
     */
    public function includeWithScope(array $arguments, array $scope): string
    {
        $view = $arguments[0] ?? null;
        $data = $arguments[1] ?? [];

        if (!is_string($view) || !is_array($data)) {
            throw new ViewException('Blade @include expects a view name and an optional data array.');
        }

        $scope = array_filter(
            $scope,
            static fn (string $key): bool => !str_starts_with($key, '__'),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->renderNamed($view, [...$scope, ...$data]);
    }

    private function path(string $view): string
    {
        if ($view === '' || str_contains($view, '..') || str_contains($view, "\0")) {
            throw new ViewException(sprintf('Invalid view name "%s".', $view));
        }

        $view = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);
        $base = rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view;
        $candidates = str_ends_with($base, '.php') ? [$base] : [$base . '.blade.php', $base . '.php'];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        throw new ViewException(sprintf('View "%s" was not found.', $view));
    }

    /** @param array<string, mixed> $data */
    private function renderNamed(string $view, array $data): string
    {
        return $this->renderFile($this->path($view), $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $file, array $data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            $renderFile = str_ends_with($file, '.blade.php') ? $this->compiledPath($file) : $file;

            (static function (string $__file, array $__data, View $__view): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($renderFile, $data, $this);

            if (ob_get_level() !== $level + 1) {
                throw new ViewException('A Blade section was started but not ended.');
            }

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $exception;
        }
    }

    private function compiledPath(string $source): string
    {
        if (!is_dir($this->cacheDirectory)
            && !mkdir($this->cacheDirectory, 0775, true)
            && !is_dir($this->cacheDirectory)
        ) {
            throw new RuntimeException(sprintf('Unable to create compiled view directory "%s".', $this->cacheDirectory));
        }

        $template = file_get_contents($source);

        if ($template === false) {
            throw new RuntimeException(sprintf('Unable to read Blade view "%s".', $source));
        }

        $compiled = rtrim($this->cacheDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . sha1($source . "\0" . $template)
            . '.php';

        if (!is_file($compiled)) {
            $temporary = tempnam($this->cacheDirectory, 'ract-view-');

            if ($temporary === false
                || file_put_contents($temporary, $this->compiler->compile($template), LOCK_EX) === false
            ) {
                if (is_string($temporary) && is_file($temporary)) {
                    @unlink($temporary);
                }

                throw new RuntimeException(sprintf('Unable to compile Blade view "%s".', $source));
            }

            if (!@rename($temporary, $compiled)) {
                if (!is_file($compiled)) {
                    @unlink($temporary);
                    throw new RuntimeException(sprintf('Unable to store compiled Blade view "%s".', $source));
                }

                @unlink($temporary);
            }
        }

        return $compiled;
    }
}
