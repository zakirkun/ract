<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Ract\Database\Model;
use Ract\Database\Relations\BelongsTo;
use Ract\Database\Relations\HasMany;

final class Article extends Model
{
    protected bool $timestamps = false;

    /** @var list<string> */
    protected array $fillable = ['author_id', 'title'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
