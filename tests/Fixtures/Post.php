<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Ract\Database\Model;

final class Post extends Model
{
    /** @var list<string> */
    protected array $fillable = ['title', 'published'];

    /** @var array<string, string> */
    protected array $casts = [
        'published' => 'boolean',
    ];
}
