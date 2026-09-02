<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Ract\Controller;
use Ract\Http\Response;

final class TestController extends Controller
{
    public function show(string $name): Response
    {
        return $this->view('message', ['name' => $name]);
    }
}
