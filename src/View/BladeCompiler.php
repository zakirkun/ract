<?php

declare(strict_types=1);

namespace Ract\View;

use RuntimeException;

final class BladeCompiler
{
    public function compile(string $template): string
    {
        $template = preg_replace('/\{\{--.*?--\}\}/s', '', $template) ?? $template;
        $escapedOpenings = [
            "\x1ARACT_ESCAPED_ECHO\x1A" => '{{',
            "\x1ARACT_ESCAPED_RAW_ECHO\x1A" => '{!!',
        ];
        $template = str_replace(
            ['@{{', '@{!!'],
            array_keys($escapedOpenings),
            $template,
        );
        $echoes = [];
        $template = $this->extractEchos($template, '{!!', '!!}', false, $echoes);
        $template = $this->extractEchos($template, '{{', '}}', true, $echoes);
        $escapedAt = "\x1ARACT_ESCAPED_AT\x1A";
        $template = str_replace('@@', $escapedAt, $template);
        $directives = [
            'extends' => static fn (string $expression): string => '<?php $__view->extend(' . $expression . '); ?>',
            'include' => static fn (string $expression): string => '<?= $__view->includeWithScope([' . $expression . '], get_defined_vars()) ?>',
            'section' => static fn (string $expression): string => '<?php $__view->startSection(' . $expression . '); ?>',
            'yield' => static fn (string $expression): string => '<?= $__view->yieldContent(' . $expression . ') ?>',
            'if' => static fn (string $expression): string => '<?php if (' . $expression . '): ?>',
            'elseif' => static fn (string $expression): string => '<?php elseif (' . $expression . '): ?>',
            'unless' => static fn (string $expression): string => '<?php if (!(' . $expression . ')): ?>',
            'foreach' => static fn (string $expression): string => '<?php foreach (' . $expression . '): ?>',
            'for' => static fn (string $expression): string => '<?php for (' . $expression . '): ?>',
            'while' => static fn (string $expression): string => '<?php while (' . $expression . '): ?>',
            'isset' => static fn (string $expression): string => '<?php if (isset(' . $expression . ')): ?>',
            'empty' => static fn (string $expression): string => '<?php if (empty(' . $expression . ')): ?>',
        ];

        foreach ($directives as $directive => $compiler) {
            $template = $this->compileParenthesized($template, $directive, $compiler);
        }

        $endDirectives = [
            'endsection' => '<?php $__view->stopSection(); ?>',
            'show' => '<?= $__view->showSection() ?>',
            'endif' => '<?php endif; ?>',
            'else' => '<?php else: ?>',
            'endunless' => '<?php endif; ?>',
            'endforeach' => '<?php endforeach; ?>',
            'endfor' => '<?php endfor; ?>',
            'endwhile' => '<?php endwhile; ?>',
            'endisset' => '<?php endif; ?>',
            'endempty' => '<?php endif; ?>',
            'break' => '<?php break; ?>',
            'continue' => '<?php continue; ?>',
            'php' => '<?php ',
            'endphp' => ' ?>',
        ];

        foreach ($endDirectives as $directive => $replacement) {
            $template = preg_replace(
                '/@' . preg_quote($directive, '/') . '(?![A-Za-z0-9_])/',
                $replacement,
                $template,
            ) ?? $template;
        }

        return strtr($template, [
            $escapedAt => '@',
            ...$escapedOpenings,
            ...$echoes,
        ]);
    }

    /** @param array<string, string> $replacements */
    private function extractEchos(
        string $template,
        string $opening,
        string $closing,
        bool $escaped,
        array &$replacements,
    ): string {
        $offset = 0;

        while (($position = strpos($template, $opening, $offset)) !== false) {
            $expressionStart = $position + strlen($opening);
            $end = $this->closingDelimiter($template, $expressionStart, $closing);
            $expression = trim(substr($template, $expressionStart, $end - $expressionStart));

            if ($expression === '') {
                throw new RuntimeException('Blade echo expressions cannot be empty.');
            }

            $key = "\x1ARACT_ECHO_" . count($replacements) . "\x1A";
            $replacements[$key] = $escaped ? '<?= e(' . $expression . ') ?>' : '<?= ' . $expression . ' ?>';
            $template = substr_replace($template, $key, $position, $end - $position + strlen($closing));
            $offset = $position + strlen($key);
        }

        return $template;
    }

    private function closingDelimiter(string $template, int $start, string $delimiter): int
    {
        $quote = null;
        $escaped = false;
        $length = strlen($template);

        for ($index = $start; $index < $length; $index++) {
            $character = $template[$index];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '\'' || $character === '"') {
                $quote = $character;
                continue;
            }

            if (substr_compare($template, $delimiter, $index, strlen($delimiter)) === 0) {
                return $index;
            }
        }

        throw new RuntimeException('Unclosed Blade echo expression.');
    }

    /** @param callable(string): string $compiler */
    private function compileParenthesized(string $template, string $directive, callable $compiler): string
    {
        $offset = 0;
        $needle = '@' . $directive;

        while (($position = strpos($template, $needle, $offset)) !== false) {
            if (($template[$position - 1] ?? '') === '@') {
                $offset = $position + strlen($needle);
                continue;
            }

            $afterName = $position + strlen($needle);
            $next = $template[$afterName] ?? '';

            if ($next !== '' && preg_match('/[A-Za-z0-9_]/', $next) === 1) {
                $offset = $afterName;
                continue;
            }

            $open = $afterName;

            while (isset($template[$open]) && ctype_space($template[$open])) {
                $open++;
            }

            if (($template[$open] ?? '') !== '(') {
                $offset = $afterName;
                continue;
            }

            $close = $this->closingParenthesis($template, $open);
            $expression = substr($template, $open + 1, $close - $open - 1);
            $replacement = $compiler($expression);
            $template = substr_replace($template, $replacement, $position, $close - $position + 1);
            $offset = $position + strlen($replacement);
        }

        return $template;
    }

    private function closingParenthesis(string $template, int $open): int
    {
        $depth = 0;
        $quote = null;
        $escaped = false;
        $length = strlen($template);

        for ($index = $open; $index < $length; $index++) {
            $character = $template[$index];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '\'' || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')' && --$depth === 0) {
                return $index;
            }
        }

        throw new RuntimeException('Unclosed Blade directive expression.');
    }
}
