<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;

class ForumPostPolicy
{
    public function update(User $user, ForumPost $post): bool
    {
        return $user->id === $post->author_id;
    }

    public function delete(User $user, ForumPost $post): bool
    {
        return $user->id === $post->author_id
            || $user->id === $post->thread->course->teacher_id;
    }
}
