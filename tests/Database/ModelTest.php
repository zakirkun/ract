<?php

declare(strict_types=1);

namespace Tests\Database;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ract\Config\Config;
use Ract\Database\DatabaseManager;
use Ract\Database\MassAssignmentException;
use Ract\Database\Model;
use Ract\Database\ModelNotFoundException;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;
use Tests\Fixtures\Post;

final class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for model tests.');
        }

        $database = new DatabaseManager(new Config([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]));
        Model::setConnectionResolver($database);
        (new SchemaBuilder($database->connection()))->create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
    }

    public function testModelsCreateFindUpdateAndDeleteRecords(): void
    {
        $post = Post::create(['title' => 'Hello', 'published' => true]);

        self::assertTrue($post->exists());
        self::assertIsInt($post->id);
        self::assertTrue($post->published);
        self::assertSame('Hello', Post::findOrFail($post->id)->title);
        self::assertCount(1, Post::where('published', true)->get());

        $post->update(['title' => 'Changed']);
        self::assertSame('Changed', Post::find($post->id)?->title);

        self::assertTrue($post->delete());
        self::assertNull(Post::find($post->id));
    }

    public function testExistingModelsRejectPrimaryKeyMutation(): void
    {
        $post = Post::create(['title' => 'Original']);
        $id = $post->id;

        try {
            $post->id = $id + 1;
            self::fail('Changing a persisted primary key should fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('primary key', strtolower($exception->getMessage()));
        }

        self::assertSame($id, $post->id);
        self::assertSame('Original', Post::findOrFail($id)->title);
    }

    public function testModelsProtectAttributesFromMassAssignment(): void
    {
        $this->expectException(MassAssignmentException::class);
        $this->expectExceptionMessage('admin');

        Post::create(['title' => 'Hello', 'admin' => true]);
    }

    public function testFindOrFailProducesAnHttp404Exception(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Post::findOrFail(999);
    }

    public function testModelsSerializeCastAttributes(): void
    {
        $post = Post::create(['title' => 'JSON', 'published' => false]);

        self::assertSame([
            'title' => 'JSON',
            'published' => false,
            'updated_at' => $post->updated_at,
            'created_at' => $post->created_at,
            'id' => $post->id,
        ], $post->toArray());
    }
}
