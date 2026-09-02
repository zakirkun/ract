<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use Ract\Config\Config;

final class ConfigTest extends TestCase
{
    public function testItReadsNestedValuesWithDefaults(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'Ract',
                'debug' => false,
            ],
        ]);

        self::assertSame('Ract', $config->get('app.name'));
        self::assertFalse($config->get('app.debug'));
        self::assertSame('fallback', $config->get('app.missing', 'fallback'));
        self::assertTrue($config->has('app.debug'));
        self::assertFalse($config->has('database.host'));
    }
}
