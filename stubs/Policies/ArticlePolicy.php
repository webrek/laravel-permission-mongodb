<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Article;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Example Article Policy using Laravel Permission MongoDB
 *
 * To use: Publish this stub to app/Policies/ArticlePolicy.php
 * php artisan vendor:publish --tag=permission-policies
 *
 * Register in AuthServiceProvider:
 * protected $policies = [
 *     Article::class => ArticlePolicy::class,
 * ];
 */
class ArticlePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any articles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-articles');
    }

    /**
     * Determine if the user can view the article.
     */
    public function view(User $user, Article $article): bool
    {
        // Allow if user has permission OR is the author
        return $user->hasPermissionTo('view-articles')
            || $article->user_id === $user->id;
    }

    /**
     * Determine if the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-articles');
    }

    /**
     * Determine if the user can update the article.
     */
    public function update(User $user, Article $article): bool
    {
        // Editors can edit any article, authors can edit their own
        return $user->hasPermissionTo('edit-articles')
            || ($user->hasPermissionTo('edit-own-articles') && $article->user_id === $user->id);
    }

    /**
     * Determine if the user can delete the article.
     */
    public function delete(User $user, Article $article): bool
    {
        // Admins can delete any article, authors can delete their own
        return $user->hasPermissionTo('delete-articles')
            || ($user->hasPermissionTo('delete-own-articles') && $article->user_id === $user->id);
    }

    /**
     * Determine if the user can publish the article.
     */
    public function publish(User $user, Article $article): bool
    {
        return $user->hasPermissionTo('publish-articles');
    }

    /**
     * Determine if the user can unpublish the article.
     */
    public function unpublish(User $user, Article $article): bool
    {
        return $user->hasPermissionTo('unpublish-articles');
    }

    /**
     * Determine if the user can restore the article.
     */
    public function restore(User $user, Article $article): bool
    {
        return $user->hasPermissionTo('restore-articles');
    }

    /**
     * Determine if the user can permanently delete the article.
     */
    public function forceDelete(User $user, Article $article): bool
    {
        return $user->hasPermissionTo('force-delete-articles');
    }
}
