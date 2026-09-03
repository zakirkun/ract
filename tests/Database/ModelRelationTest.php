<?php

declare(strict_types=1);

namespace Tests\Database;

use PHPUnit\Framework\TestCase;
use Ract\Config\Config;
use Ract\Database\DatabaseManager;
use Ract\Database\Model;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;
use Tests\Fixtures\Models\Article;
use Tests\Fixtures\Models\Author;
use Tests\Fixtures\Models\Comment;
use Tests\Fixtures\Models\Profile;
use Tests\Fixtures\Models\Role;

final class ModelRelationTest extends TestCase
{
    private DatabaseManager $database;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for model relation tests.');
        }

        $this->database = new DatabaseManager(new Config([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]));
        Model::setConnectionResolver($this->database);
        $schema = new SchemaBuilder($this->database->connection());
        $schema->create('authors', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $schema->create('profiles', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('author_id');
            $table->string('bio');
        });
        $schema->create('articles', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('author_id');
            $table->string('title');
        });
        $schema->create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('article_id');
            $table->string('body');
        });
        $schema->create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $schema->create('author_role', static function (Blueprint $table): void {
            $table->bigInteger('author_id');
            $table->bigInteger('role_id');
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
    }

    public function testRelationsLoadLazilyAndUseConventionalKeys(): void
    {
        $author = Author::create(['name' => 'Ada']);
        $profile = Profile::create(['author_id' => $author->id, 'bio' => 'Engineer']);
        $article = Article::create(['author_id' => $author->id, 'title' => 'Ract']);
        $comment = Comment::create(['article_id' => $article->id, 'body' => 'Useful']);

        self::assertSame($profile->id, $author->profile->id);
        self::assertSame($author->id, $article->author->id);
        self::assertSame($article->id, $comment->article->id);
        self::assertSame(['Ract'], array_map(
            static fn (Article $related): string => $related->title,
            $author->articles,
        ));
        self::assertTrue($author->relationLoaded('articles'));
    }

    public function testDottedEagerLoadingAndManyToManyRelationsSerializeRecursively(): void
    {
        $author = Author::create(['name' => 'Grace']);
        Profile::create(['author_id' => $author->id, 'bio' => 'Admiral']);
        $first = Article::create(['author_id' => $author->id, 'title' => 'Compilers']);
        $second = Article::create(['author_id' => $author->id, 'title' => 'Systems']);
        Comment::create(['article_id' => $first->id, 'body' => 'First']);
        Comment::create(['article_id' => $second->id, 'body' => 'Second']);
        $role = Role::create(['name' => 'admin']);
        $this->database->table('author_role')->insert([
            'author_id' => $author->id,
            'role_id' => $role->id,
        ]);

        $loaded = Author::query()->with(['profile', 'articles.comments', 'roles'])->find($author->id);

        self::assertNotNull($loaded);
        self::assertTrue($loaded->relationLoaded('profile'));
        self::assertTrue($loaded->articles[0]->relationLoaded('comments'));
        self::assertSame(['First', 'Second'], array_map(
            static fn (Article $article): string => $article->comments[0]->body,
            $loaded->articles,
        ));
        self::assertSame('admin', $loaded->roles[0]->name);
        self::assertSame('First', $loaded->toArray()['articles'][0]['comments'][0]['body']);
        self::assertSame('Admiral', $loaded->toArray()['profile']['bio']);
    }

    public function testEagerLoadingMatchesEquivalentNumericKeyTypes(): void
    {
        $author = Author::create(['name' => 'Katherine']);
        Article::create(['author_id' => $author->id, 'title' => 'Orbital mechanics']);
        $detached = (new Author())->newFromBuilder([
            'id' => (string) $author->id,
            'name' => $author->name,
        ]);

        $detached->load('articles');

        self::assertSame('Orbital mechanics', $detached->articles[0]->title);
    }

    public function testWhereInWhereNotInAndPluckSupportRelationQueries(): void
    {
        $author = Author::create(['name' => 'Linus']);
        Article::create(['author_id' => $author->id, 'title' => 'One']);
        Article::create(['author_id' => $author->id, 'title' => 'Two']);
        Article::create(['author_id' => $author->id, 'title' => 'Three']);

        self::assertSame(
            ['One', 'Three'],
            Article::query()->whereIn('title', ['One', 'Three'])->orderBy('id')->pluck('title'),
        );
        self::assertSame(
            ['Two'],
            Article::query()->whereNotIn('title', ['One', 'Three'])->pluck('title'),
        );
        self::assertSame([], Article::query()->whereIn('id', [])->get());
        self::assertCount(3, Article::query()->whereNotIn('id', [])->get());
    }
}
