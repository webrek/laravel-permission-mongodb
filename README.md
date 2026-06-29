# webrek/laravel-permission-mongodb

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-packagist]

> ## ⚠️ Abandoned — use [`webrek/laravel-mongo-permission`][link-replacement] instead
>
> This was a quick fork of [`mostafamaklad/laravel-permission-mongodb`][link-upstream] and it is no longer maintained. It still ships the upstream `Maklad\Permission\` namespace and was never properly rebranded.
>
> For new projects, install the maintained, built-from-scratch package: **[`webrek/laravel-mongo-permission`][link-replacement]** — native MongoDB, `spatie/laravel-permission`-compatible API, supported on current Laravel.
>
> ```bash
> composer require webrek/laravel-mongo-permission
> ```
>
> The rest of this README is kept for existing installs only.

---

This package allows you to manage user permissions and roles in a database.
It is inspired from [laravel-permission][link-laravel-permission]. Same code, same everything, but compatible with [laravel-mongodb][link-laravel-mongodb].

Once installed you can do stuff like this:

```php
// Adding permissions to a user
$user->givePermissionTo('edit articles');

// Adding permissions via a role
$user->assignRole('writer');

$role->givePermissionTo('edit articles');
```

If you're using multiple guards we've got you covered as well. Every guard will have its own set of permissions and roles that can be assigned to the guard's users. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

Because all permissions will be registered on [Laravel's gate](https://laravel.com/docs/5.5/authorization), you can test if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit articles');
```

## Table of contents
* [Installation](#installation)
    * [Laravel Compatibility](#laravel-compatibility)
    * [Laravel](#laravel)
    * [Lumen](#lumen)
* [Usage](#usage)
    * [Using "direct" permissions](#using-direct-permissions)
    * [Using permissions via roles](#using-permissions-via-roles)
    * [Using Blade directives](#using-blade-directives)
* [Using multiple guards](#using-multiple-guards)
    * [Using permissions and roles with multiple guards](#using-permissions-and-roles-with-multiple-guards)
    * [Assigning permissions and roles to guard users](#assigning-permissions-and-roles-to-guard-users)
    * [Using blade directives with multiple guards](#using-blade-directives-with-multiple-guards)
* [Using a middleware](#using-a-middleware)
* [Using artisan commands](#using-artisan-commands)
* [Unit Testing](#unit-testing)
* [Database Seeding](#database-seeding)
* [Extending](#extending)
* [Cache](#cache)
    * [Manual cache reset](#manual-cache-reset)
    * [Cache Identifier](#cache-identifier)
* [Need a UI?](#need-a-ui)
* [Change log](#change-log)
* [Testing](#testing)
* [Contributing](#contributing)
* [Security](#security)
* [Credits](#credits)
* [License](#license)

## Installation

### Laravel Compatibility

 Laravel  | Package | PHP
:---------|:--------|:----
 5.x      | 1.x, 2.x, 3.x | 7.0+
 6.x      | 2.x, 3.x | 7.2+
 7.x      | 3.x | 7.2+
 8.x      | 3.1.x | 7.3+
 9.x      | 4.x | 8.0+
 10.x     | 6.x | 8.2+
 11.x     | 6.x | 8.2+
 12.x     | 6.x | 8.2+

### Laravel

You can install the package via composer:

For Laravel 10.x, 11.x, and 12.x use this fork:

``` bash
composer require webrek/laravel-permission-mongodb
```

For Laravel 9.x and older, this fork is not published; use the original package instead:

``` bash
# Laravel 9.x
composer require mostafamaklad/laravel-permission-mongodb:"^4.0"
# Laravel 8.x and older
composer require mostafamaklad/laravel-permission-mongodb:"^3.1"
```

You can publish [the migration](database/migrations/create_permission_collections.php.stub) with:

```bash
php artisan vendor:publish --provider="Maklad\Permission\PermissionServiceProvider" --tag="migrations"
```

```bash
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Maklad\Permission\PermissionServiceProvider" --tag="config"
```

When published, the [`config/permission.php`](config/permission.php) config file contains:

```php
return [

    'models' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Moloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Maklad\Permission\Contracts\Permission` contract.
         */

        'permission' => Maklad\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Moloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Maklad\Permission\Contracts\Role` contract.
         */

        'role' => Maklad\Permission\Models\Role::class,

    ],

    'collection_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',
    ],

    /*
     * By default all permissions will be cached for 24 hours unless a permission or
     * role is updated. Then the cache will be flushed immediately.
     */

    'cache_expiration_time' => 60 * 24,

    /*
     * By default we'll make an entry in the application log when the permissions
     * could not be loaded. Normally this only occurs while installing the packages.
     *
     * If for some reason you want to disable that logging, set this value to false.
     */

    'log_registration_exception' => true,
    
    /*
     * When set to true, the required permission/role names are added to the exception
     * message. This could be considered an information leak in some contexts, so
     * the default setting is false here for optimum safety.
     */
    
    'display_permission_in_exception' => false,
];
```

### Lumen

You can install the package via Composer:

``` bash
composer require webrek/laravel-permission-mongodb
```

Copy the required files:

```bash
cp vendor/webrek/laravel-permission-mongodb/config/permission.php config/permission.php
cp vendor/webrek/laravel-permission-mongodb/database/migrations/create_permission_collections.php.stub database/migrations/2018_01_01_000000_create_permission_collections.php
```

You will also need to create another configuration file at `config/auth.php`. Get it on the Laravel repository or just run the following command:

```bash
curl -Ls https://raw.githubusercontent.com/laravel/lumen-framework/5.5/config/auth.php -o config/auth.php
```

Then, in `bootstrap/app.php`, register the middlewares:

```php
$app->routeMiddleware([
    'auth'       => App\Http\Middleware\Authenticate::class,
    'permission' => Maklad\Permission\Middlewares\PermissionMiddleware::class,
    'role'       => Maklad\Permission\Middlewares\RoleMiddleware::class,
]);
```

As well as the configuration and the service provider:

```php
$app->configure('permission');
$app->register(Maklad\Permission\PermissionServiceProvider::class);
```

Now, run your migrations:

```bash
php artisan migrate
```

## Usage

First, add the `Maklad\Permission\Traits\HasRoles` trait to your `User` model(s):

```php
use Illuminate\Auth\Authenticatable;
use MongoDB\Laravel\Eloquent\Model as Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Maklad\Permission\Traits\HasRoles;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasRoles;

    // ...
}
```

> Note: that if you need to use `HasRoles` trait with another model ex.`Page` you will also need to add `protected $guard_name = 'web';` as well to that model or you would get an error

```php
use MongoDB\Laravel\Eloquent\Model as Model;
use Maklad\Permission\Traits\HasRoles;

class Page extends Model
{
    use HasRoles;

    protected $guard_name = 'web'; // or whatever guard you want to use

    // ...
}
```

This package allows for users to be associated with permissions and roles. Every role is associated with multiple permissions.
A `Role` and a `Permission` are regular Moloquent models. They require a `name` and can be created like this:

```php
use Maklad\Permission\Models\Role;
use Maklad\Permission\Models\Permission;

$role = Role::create(['name' => 'writer']);
$permission = Permission::create(['name' => 'edit articles']);
```

A permission can be assigned to a role using 1 of these methods:

```php
$role->givePermissionTo($permission);
$permission->assignRole($role);
```

Multiple permissions can be synced to a role using 1 of these methods:

```php
$role->syncPermissions($permissions);
$permission->syncRoles($roles);
```

A permission can be removed from a role using 1 of these methods:

```php
$role->revokePermissionTo($permission);
$permission->removeRole($role);
```

If you're using multiple guards the `guard_name` attribute needs to be set as well. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

The `HasRoles` trait adds Moloquent relationships to your models, which can be accessed directly or used as a base query:

```php
// get a list of all permissions directly assigned to the user
$permissions = $user->permissions; // Returns a collection

// get all permissions inherited by the user via roles
$permissions = $user->getAllPermissions(); // Returns a collection

// get all permissions names
$permissions = $user->getPermissionNames(); // Returns a collection

// get a collection of all defined roles
$roles = $user->roles->pluck('name'); // Returns a collection

// get all role names
$roles = $user->getRoleNames() // Returns a collection;
```

The `HasRoles` trait also adds a `role` scope to your models to scope the query to certain roles or permissions:

```php
$users = User::role('writer')->get(); // Returns only users with the role 'writer'
$users = User::permission('edit articles')->get(); // Returns only users with the permission 'edit articles'
```

The scope can accept a string, a `\Maklad\Permission\Models\Role` object, a `\Maklad\Permission\Models\Permission` object or an `\Illuminate\Support\Collection` object.


### Using "direct" permissions

A permission can be given to any user with the `HasRoles` trait:

```php
$user->givePermissionTo('edit articles');

// You can also give multiple permission at once
$user->givePermissionTo('edit articles', 'delete articles');

// You may also pass an array
$user->givePermissionTo(['edit articles', 'delete articles']);
```

A permission can be revoked from a user:

```php
$user->revokePermissionTo('edit articles');
```

Or revoke & add new permissions in one go:

```php
$user->syncPermissions(['edit articles', 'delete articles']);
```

You can test if a user has a permission:

```php
$user->hasPermissionTo('edit articles');
```

...or if a user has multiple permissions:

```php
$user->hasAnyPermission(['edit articles', 'publish articles', 'unpublish articles']);
```

Saved permissions will be registered with the `Illuminate\Auth\Access\Gate` class for the default guard. So you can
test if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit articles');
```

### Using permissions via roles

A role can be assigned to any user:

```php
$user->assignRole('writer');

// You can also assign multiple roles at once
$user->assignRole('writer', 'admin');
// or as an array
$user->assignRole(['writer', 'admin']);
```

A role can be removed from a user:

```php
$user->removeRole('writer');
```

Roles can also be synced:

```php
// All current roles will be removed from the user and replaced by the array given
$user->syncRoles(['writer', 'admin']);
```

You can determine if a user has a certain role:

```php
$user->hasRole('writer');
```

You can also determine if a user has any of a given list of roles:

```php
$user->hasAnyRole(Role::all());
```

You can also determine if a user has all of a given list of roles:

```php
$user->hasAllRoles(Role::all());
```

The `assignRole`, `hasRole`, `hasAnyRole`, `hasAllRoles`  and `removeRole` functions can accept a
 string, a `\Maklad\Permission\Models\Role` object or an `\Illuminate\Support\Collection` object.

A permission can be given to a role:

```php
$role->givePermissionTo('edit articles');
```

You can determine if a role has a certain permission:

```php
$role->hasPermissionTo('edit articles');
```

A permission can be revoked from a role:

```php
$role->revokePermissionTo('edit articles');
```

The `givePermissionTo` and `revokePermissionTo` functions can accept a
string or a `Maklad\Permission\Models\Permission` object.

Permissions are inherited from roles automatically.
Additionally, individual permissions can be assigned to the user too. 

For instance:

```php
$role = Role::findByName('writer');
$role->givePermissionTo('edit articles');

$user->assignRole('writer');

$user->givePermissionTo('delete articles');
```

In the above example, a role is given permission to edit articles and this role is assigned to a user.
Now the user can edit articles and additionally delete articles. The permission of `delete articles` is the user's direct permission because it is assigned directly to them.
When we call `$user->hasDirectPermission('delete articles')` it returns `true`, but `false` for `$user->hasDirectPermission('edit articles')`.

This method is useful if one builds a form for setting permissions for roles and users in an application and wants to restrict or change inherited permissions of roles of the user, i.e. allowing to change only direct permissions of the user.

You can list all of these permissions:

```php
// Direct permissions
$user->getDirectPermissions() // Or $user->permissions;

// Permissions inherited from the user's roles
$user->getPermissionsViaRoles();

// All permissions which apply on the user (inherited and direct)
$user->getAllPermissions();
```

All these responses are collections of `Maklad\Permission\Models\Permission` objects.

If we follow the previous example, the first response will be a collection with the `delete article` permission, the
second will be a collection with the `edit article` permission and the third will contain both.

### Using Blade directives
This package also adds Blade directives to verify whether the currently logged in user has all or any of a given list of roles.

Optionally you can pass in the `guard` that the check will be performed on as a second argument.
#### Blade and Roles
Test for a specific role:
```php
@role('writer')
    I am a writer!
@else
    I am not a writer...
@endrole
```
is the same as
```php
@hasrole('writer')
    I am a writer!
@else
    I am not a writer...
@endhasrole
```
Test for any role in a list:
```php
@hasanyrole(Role::all())
    I have one or more of these roles!
@else
    I have none of these roles...
@endhasanyrole
// or
@hasanyrole('writer|admin')
    I am either a writer or an admin or both!
@else
    I have none of these roles...
@endhasanyrole
```
Test for all roles:
```php
@hasallroles(Role::all())
    I have all of these roles!
@else
    I do not have all of these roles...
@endhasallroles
// or
@hasallroles('writer|admin')
    I am both a writer and an admin!
@else
    I do not have all of these roles...
@endhasallroles
```

#### Blade and Permissions
This package doesn't add any permission-specific Blade directives. Instead, use Laravel's native `@can` directive to check if a user has a certain permission.

```php
@can('edit articles')
  //
@endcan
```
or
```php
@if(auth()->user()->can('edit articles') && $some_other_condition)
  //
@endif
```

## Using multiple guards

When using the default Laravel auth configuration all of the above methods will work out of the box, no extra configuration required.

However when using multiple guards they will act like namespaces for your permissions and roles. Meaning every guard has its own set of permissions and roles that can be assigned to their user model.

### Using permissions and roles with multiple guards

When creating new permissions and roles, if no guard is specified, then the **first** defined guard in `auth.guards` config array will be used. When creating permissions and roles for specific guards you'll have to specify their `guard_name` on the model:

```php
// Create a superadmin role for the admin users

$user->hasPermissionTo('publish articles', 'admin');
```

> **Note**: When determining whether a role/permission is valid on a given model, it chooses the guard in this order: first the `$guard_name` property of the model; then the guard in the config (through a provider); then the first-defined guard in the `auth.guards` config array; then the `auth.defaults.guard` config.

### Assigning permissions and roles to guard users

You can use the same methods to assign permissions and roles to users as described above in [using permissions via roles](#using-permissions-via-roles). Just make sure the `guard_name` on the permission or role matches the guard of the user, otherwise a `GuardDoesNotMatch` exception will be thrown.

### Using blade directives with multiple guards

You can use all of the blade directives listed in [using blade directives](#using-blade-directives) by passing in the guard you wish to use as the second argument to the directive:

```php
@role('super-admin', 'admin')
    I am a super-admin!
@else
    I am not a super-admin...
@endrole
```

## Using a middleware

This package comes with `RoleMiddleware` and `PermissionMiddleware` middleware. You can add them inside your `app/Http/Kernel.php` file.

```php
protected $routeMiddleware = [
    // ...
    'role' => \Maklad\Permission\Middlewares\RoleMiddleware::class,
    'permission' => \Maklad\Permission\Middlewares\PermissionMiddleware::class,
];
```

Then you can protect your routes using middleware rules:

```php
Route::group(['middleware' => ['role:super-admin']], function () {
    //
});

Route::group(['middleware' => ['permission:publish articles']], function () {
    //
});

Route::group(['middleware' => ['role:super-admin','permission:publish articles']], function () {
    //
});
```
You can protect your controllers similarly, by setting desired middleware in the constructor:

```php
public function __construct()
{
    $this->middleware(['role:super-admin','permission:publish articles|edit articles']);
}
```

You can add something in Laravel exception handler:

```php
public function render($request, Exception $exception)
{
    if ($exception instanceof \Maklad\Permission\Exceptions\UnauthorizedException) {
        // Code here ...
    }

    return parent::render($request, $exception);
}

```

## Using artisan commands

You can create a role or permission from a console with artisan commands.

```bash
php artisan permission:create-role writer
```

```bash
php artisan permission:create-permission 'edit articles'
```

When creating permissions and roles for specific guards you can specify the guard names as a second argument:

```bash
php artisan permission:create-role writer web
```

```bash
php artisan permission:create-permission 'edit articles' web
```

## Unit Testing

In your application's tests, if you are not seeding roles and permissions as part of your test `setUp()` then you may run into a chicken/egg situation where roles and permissions aren't registered with the gate (because your tests create them after that gate registration is done). Working around this is simple: In your tests simply add a `setUp()` instruction to re-register the permissions, like this:

```php
public function setUp()
{
    // first include all the normal setUp operations
    parent::setUp();

    // now re-register all the roles and permissions
    $this->app->make(\Maklad\Permission\PermissionRegistrar::class)->registerPermissions();
}
```

## Database Seeding

Two notes about Database Seeding:

1. It is best to flush the `maklad.permission.cache` before seeding, to avoid cache conflict errors. This can be done from an Artisan command (see Troubleshooting: Cache section, later) or directly in a seeder class (see example below).

2. Here's a sample seeder, which clears the cache, creates permissions, and then assigns permissions to roles:
```php
use Illuminate\Database\Seeder;
use Maklad\Permission\Models\Role;
use Maklad\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()['cache']->forget('maklad.permission.cache');
        
        // create permissions
        Permission::firstOrCreate(['name' => 'edit articles']);
        Permission::firstOrCreate(['name' => 'delete articles']);
        Permission::firstOrCreate(['name' => 'publish articles']);
        Permission::firstOrCreate(['name' => 'unpublish articles']);
        
        // create roles and assign existing permissions
        $role = Role::firstOrCreate(['name' => 'writer']);
        $role->givePermissionTo('edit articles');
        $role->givePermissionTo('delete articles');
        
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['publish articles', 'unpublish articles']);
    }
}
```

## Extending
If you need to EXTEND the existing `Role` or `Permission` models note that:

- Your `Role` model needs to extend the `Maklad\Permission\Models\Role` model
- Your `Permission` model needs to extend the `Maklad\Permission\Models\Permission` model

If you need to extend or replace the existing `Role` or `Permission` models you just need to
keep the following things in mind:

- Your `Role` model needs to implement the `Maklad\Permission\Contracts\Role` contract
- Your `Permission` model needs to implement the `Maklad\Permission\Contracts\Permission` contract

In BOTH cases, whether extending or replacing, you will need to specify your new models in the configuration. To do this you must update the `models.role` and `models.permission` values in the configuration file after publishing the configuration with this command:
  ```bash
  php artisan vendor:publish --provider="Maklad\Permission\PermissionServiceProvider" --tag="config"
  ```

## Cache

Role and Permission data are cached to speed up performance.

When you use the supplied methods for manipulating roles and permissions, the cache is automatically reset for you:

```php
$user->assignRole('writer');
$user->removeRole('writer');
$user->syncRoles(params);
$role->givePermissionTo('edit articles');
$role->revokePermissionTo('edit articles');
$role->syncPermissions(params);
$permission->assignRole('writer');
$permission->removeRole('writer');
$permission->syncRoles(params);
```

HOWEVER, if you manipulate permission/role data directly in the database instead of calling the supplied methods, then you will not see the changes reflected in the application unless you manually reset the cache.

### Manual cache reset
To manually reset the cache for this package, run:
```bash
php artisan cache:forget maklad.permission.cache
```

### Cache Identifier

> Note: If you are leveraging a caching service such as `redis` or `memcached` and there are other sites running on your server, you could run into cache clashes. It is prudent to set your own cache `prefix` in `/config/cache.php` for each application uniquely. This will prevent other applications from accidentally using/changing your cached data.

## Need a UI?

As we are based on [laravel-permission][link-laravel-permission]. The package doesn't come with any screens out of the box, you should build that yourself. To get started check out [this extensive tutorial](https://scotch.io/tutorials/user-authorization-in-laravel-54-with-spatie-laravel-permission) by [Caleb Oki](http://www.caleboki.com/).

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CONDUCT.md) for details.

## Security

If you discover any security-related issues, please report them privately via [GitHub security advisories][link-security] instead of using the public issue tracker.

## Credits

This is a fork. Original work and ongoing thanks to:

- [Mostafa Maklad][link-author] (original author)
- [Freek Van der Herten][link-freekmurze] (spatie/laravel-permission, the inspiration)
- [All Contributors][link-contributors]

Fork maintained by [webrek][link-webrek].

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


[link-packagist]: https://packagist.org/packages/webrek/laravel-permission-mongodb
[ico-version]: https://img.shields.io/packagist/v/webrek/laravel-permission-mongodb.svg?style=flat-square
[ico-license]: https://img.shields.io/packagist/l/webrek/laravel-permission-mongodb.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/webrek/laravel-permission-mongodb.svg?style=flat-square

[link-replacement]: https://github.com/webrek/laravel-mongo-permission
[link-upstream]: https://github.com/mostafamaklad/laravel-permission-mongodb
[link-author]: https://github.com/mostafamaklad
[link-webrek]: https://github.com/webrek
[link-security]: https://github.com/webrek/laravel-permission-mongodb/security/advisories/new
[link-contributors]: ../../contributors
[link-releases]: ../../releases
[link-laravel-permission]: https://github.com/spatie/laravel-permission
[link-laravel-mongodb]: https://github.com/mongodb/laravel-mongodb
[link-freekmurze]: https://github.com/freekmurze
