<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Ract\Database\Model;
use Ract\Database\Relations\BelongsTo;

final class Profile extends Model
{
    protected bool $timestamps = false;

    /** @var list<string> */
    protected array $fillable = ['author_id', 'bio'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
