<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;

class ForumThreadPolicy
{
    /** Ubah judul dan isi: hanya penulis (BR-020). */
    public function update(User $user, ForumThread $thread): bool
    {
        return $user->id === $thread->author_id;
    }

    /** Sematkan dan kunci: hanya guru pemilik kelas. */
    public function moderate(User $user, ForumThread $thread): bool
    {
        return $user->id === $thread->course->teacher_id;
    }

    /** Hapus: penulis atau guru pemilik. Aturan OI-20 (sudah dibalas orang lain) dicek di controller. */
    public function delete(User $user, ForumThread $thread): bool
    {
        return $user->id === $thread->author_id
            || $user->id === $thread->course->teacher_id;
    }
}
