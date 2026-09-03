<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Ract\Database\Model;
use Ract\Database\Relations\BelongsTo;

final class Comment extends Model
{
    protected bool $timestamps = false;

    /** @var list<string> */
    protected array $fillable = ['article_id', 'body'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
