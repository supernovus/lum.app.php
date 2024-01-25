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

## TODO

I am planning to split more of the `Lum\Controllers\Has\Auth` code into
new classes/traits in the [lum-auth] package, as that package is expanded
to support new forms of auth including passkeys and a few MFA options.

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
