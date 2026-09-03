<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Ract\Database\Model;
use Ract\Database\Relations\BelongsToMany;

final class Role extends Model
{
    protected bool $timestamps = false;

    /** @var list<string> */
    protected array $fillable = ['name'];

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }
}
