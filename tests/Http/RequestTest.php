<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Ract\Http\Request;

final class RequestTest extends TestCase
{
    public function testItExposesNormalizedRequestInput(): void
    {
        $request = new Request(
            method: 'post',
            uri: '/articles/?page=2',
            query: ['page' => '2', 'shared' => 'query'],
            data: ['title' => 'Ract', 'shared' => 'post'],
            headers: [
                'X-Requested-With' => 'XMLHttpRequest',
                'Authorization' => 'Bearer test-token',
            ],
        );

        self::assertSame('POST', $request->method());
        self::assertSame('/articles', $request->path());
        self::assertSame('2', $request->query('page'));
        self::assertSame('Ract', $request->post('title'));
        self::assertSame('post', $request->input('shared'));
        self::assertTrue($request->isAjax());
        self::assertSame('test-token', $request->bearerToken());
    }

    public function testItDecodesJsonBodies(): void
    {
        $request = new Request('POST', '/api', body: '{"name":"Ract","ready":true}');

        self::assertSame(['name' => 'Ract', 'ready' => true], $request->json());
    }

    public function testJsonBodiesAreAvailableAsRequestInput(): void
    {
        $request = new Request(
            'PATCH',
            '/articles/1',
            headers: ['Content-Type' => 'application/json; charset=UTF-8'],
            body: '{"title":"Updated","published":true}',
        );

        self::assertSame('Updated', $request->input('title'));
        self::assertTrue($request->input('published'));
        self::assertSame(['title' => 'Updated', 'published' => true], $request->post());
    }

    public function testUrlEncodedBodiesAreAvailableAsRequestInput(): void
    {
        $request = new Request(
            'PUT',
            '/articles/1',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: 'title=Updated+post&published=1',
        );

        self::assertSame('Updated post', $request->input('title'));
        self::assertSame('1', $request->input('published'));
    }
}
