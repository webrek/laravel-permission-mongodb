<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Comment;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Example Comment Policy using Laravel Permission MongoDB
 */
class CommentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-comments');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-comments');
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->hasPermissionTo('edit-comments')
            || ($user->hasPermissionTo('edit-own-comments') && $comment->user_id === $user->id);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->hasPermissionTo('delete-comments')
            || ($comment->user_id === $user->id);
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->hasPermissionTo('moderate-comments');
    }
}
