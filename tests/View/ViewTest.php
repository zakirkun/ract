<?php

declare(strict_types=1);

namespace Tests\View;

use PHPUnit\Framework\TestCase;
use Ract\Exception\ViewException;
use Ract\View\View;

final class ViewTest extends TestCase
{
    private View $view;

    protected function setUp(): void
    {
        $this->view = new View(dirname(__DIR__) . '/Fixtures/views');
    }

    public function testItRendersEscapedDataInsideALayout(): void
    {
        $output = $this->view->render('message', ['name' => '<Ract>'], 'layout');

        self::assertSame('Layout[Hello, &lt;Ract&gt;!]', trim($output));
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
