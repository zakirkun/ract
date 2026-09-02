<?php

declare(strict_types=1);

namespace Ract\View;

use Ract\Exception\ViewException;
use Throwable;

final class View
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($this->path($view), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->path($layout), [...$data, 'content' => $content]);
    }

    public function exists(string $view): bool
    {
        try {
            return is_file($this->path($view));
        } catch (ViewException) {
            return false;
        }
    }

    private function path(string $view): string
    {
        if ($view === '' || str_contains($view, '..') || str_contains($view, "\0")) {
            throw new ViewException(sprintf('Invalid view name "%s".', $view));
        }

        $view = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);
        $file = rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view;

        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        if (!is_file($file)) {
            throw new ViewException(sprintf('View "%s" was not found.', $view));
        }

        return $file;
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $file, array $data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $exception;
        }
    }
}
