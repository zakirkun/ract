<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Ract\Database\Model;
use Ract\Database\Relations\BelongsToMany;
use Ract\Database\Relations\HasMany;
use Ract\Database\Relations\HasOne;

final class Author extends Model
{
    protected bool $timestamps = false;

    /** @var list<string> */
    protected array $fillable = ['name'];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
