# lum.app.php

## Summary

A collection of libraries for MVC-style apps.

Has abstract classes, utility classes, and modular traits for both controllers
and a small selection of certain models that apps may desire.

This is one of the packages that replaces the older [lum-framework] package.

## Extensions

Database-specific model classes are in their own packages:

 * [PDO/SQL](https://github.com/supernovus/lum.app-pdo.php)
 * [MongoDB](https://github.com/supernovus/lum.app-mongo.php)

## TODO: Auth changes!

Currently a lot of logic for authentication is implemented in the
`Lum\Controllers\Has\Auth`, `Lum\Controllers\For\User*`, and
various `Lum\Models\*` classes and traits. That is going to change.
The majority of actual authentication code will be moved into the
[lum-auth] package, which will be modularlized further and have
a new API that is cleaner and easier to extend (features such as
MFA and Passkeys will be added to the new version.)

The existing traits and classes will still exist, with their existing
API methods, but they'll be using the new `lum-auth` APIs instead of
implementing the functionality themselves. Several of the methods
in `Lum\Controllers\Has\Auth` will be marked as deprecated for
all future versions of the `1.x` releases, and will be removed entirely
from the next major (`2.0`) release. Some of the other traits and
classes may also have deprecated APIs, the docs will be updated
accordingly once the refactoring has been done.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.app.php)
 * [Packageist](https://packagist.org/packages/lum/lum-app)

## Authors

- Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)


[lum-framework]: https://github.com/supernovus/lum.framework.php
[lum-auth]: https://github.com/supernovus/lum.auth.php
