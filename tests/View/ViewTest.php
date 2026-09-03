<?php

declare(strict_types=1);

namespace Tests\View;

use PHPUnit\Framework\TestCase;
use Ract\Exception\ViewException;
use Ract\View\View;

final class ViewTest extends TestCase
{
    private View $view;

    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ract-view-test-' . bin2hex(random_bytes(6));
        $this->view = new View(dirname(__DIR__) . '/Fixtures/views', $this->cacheDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
    }

    public function testItRendersEscapedDataInsideALayout(): void
    {
        $output = $this->view->render('message', ['name' => '<Ract>'], 'layout');

        self::assertSame('Layout[Hello, &lt;Ract&gt;!]', trim($output));
    }

    public function testBladeViewsSupportInheritanceIncludesControlFlowAndEscaping(): void
    {
        $output = $this->view->render('blade/page', [
            'items' => ['<First>', 'Second'],
            'trusted' => '<strong>Trusted</strong>',
        ]);

        self::assertStringContainsString('<title>Dashboard</title>', $output);
        self::assertStringContainsString('<p>&lt;First&gt;</p>', $output);
        self::assertStringContainsString('<p>Second</p>', $output);
        self::assertStringContainsString('<strong>Trusted</strong>', $output);
        self::assertStringNotContainsString('<p>Empty</p>', $output);
        self::assertNotSame([], glob($this->cacheDirectory . DIRECTORY_SEPARATOR . '*.php'));
    }

    public function testBladeElseBranchesRenderWhenACollectionIsEmpty(): void
    {
        $output = $this->view->render('blade/page', [
            'items' => [],
            'trusted' => '',
        ]);

        self::assertStringContainsString('<p>Empty</p>', $output);
        self::assertStringNotContainsString('<p>Second</p>', $output);
    }

    public function testBladeParsingPreservesClosingDelimitersInStringsAndLiteralDirectives(): void
    {
        $output = $this->view->render('blade/syntax');

        self::assertStringContainsString("}}@elsewhere\n@if", trim($output));
    }

    public function testBladeCacheInvalidatesWhenContentChangesWithoutANewerTimestamp(): void
    {
        $sourceDirectory = $this->cacheDirectory . '-source';
        $compiledDirectory = $this->cacheDirectory . '-compiled';
        mkdir($sourceDirectory);
        $source = $sourceDirectory . DIRECTORY_SEPARATOR . 'dynamic.blade.php';

        try {
            file_put_contents($source, 'First');
            $sourceTime = filemtime($source);
            $view = new View($sourceDirectory, $compiledDirectory);
            self::assertSame('First', $view->render('dynamic'));

            file_put_contents($source, 'Second');
            touch($source, $sourceTime);
            clearstatcache(true, $source);

            self::assertSame('Second', $view->render('dynamic'));
        } finally {
            foreach (glob($compiledDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                unlink($file);
            }

            if (is_dir($compiledDirectory)) {
                rmdir($compiledDirectory);
            }

            if (is_file($source)) {
                unlink($source);
            }

            if (is_dir($sourceDirectory)) {
                rmdir($sourceDirectory);
            }
        }
    }

    public function testItRejectsMissingViews(): void
    {
        $this->expectException(ViewException::class);

        $this->view->render('missing');
    }

    public function testItRejectsDirectoryTraversal(): void
    {
        $this->expectException(ViewException::class);

        $this->view->render('../secret');
    }
}
