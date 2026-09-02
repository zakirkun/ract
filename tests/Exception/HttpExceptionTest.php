<?php

declare(strict_types=1);

namespace Tests\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ract\Exception\HttpException;

final class HttpExceptionTest extends TestCase
{
    public function testItRejectsNonErrorStatusCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpException(302, 'Not an error');
    }

    public function testItRejectsInvalidResponseHeaders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpException(400, 'Bad request', ['Bad:Name' => 'value']);
    }
}
