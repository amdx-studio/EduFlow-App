<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'author_id', 'title', 'body', 'is_pinned', 'is_locked'])]
class ForumThread extends Model
{
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    /** BR-019: hanya guru pemilik dan siswa terdaftar. Selain itu tidak terlihat (404). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('course', fn ($q) => $q->visibleTo($user));
    }
}
