<?php

declare(strict_types=1);

namespace Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ract\Http\Response;

final class ResponseTest extends TestCase
{
    public function testItCreatesJsonResponses(): void
    {
        $response = Response::json(['status' => 'ok'], 201, ['X-Ract' => 'yes']);

        self::assertSame(201, $response->statusCode());
        self::assertSame('{"status":"ok"}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->header('content-type'));
        self::assertSame('yes', $response->header('X-Ract'));
    }

    public function testItRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Response())->setHeader('X-Test', "safe\r\nX-Injected: true");
    }

    public function testItRejectsInvalidStatusCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(statusCode: 99);
    }

    public function testSettingAHeaderReplacesItsValueCaseInsensitively(): void
    {
        $response = (new Response())
            ->setHeader('Content-Type', 'text/plain')
            ->setHeader('content-type', 'application/json');

        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertCount(1, $response->headers());
    }

    public function testItRejectsInvalidHeaderNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Response())->setHeader('Bad:Name', 'value');
    }

    public function testItRejectsControlCharactersInHeaderValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Response())->setHeader('X-Test', "value\0suffix");
    }
}
