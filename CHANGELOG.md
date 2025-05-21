# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2025-05-20
### Fixed
- The `Lum\App` static class actually works properly now.
### Changed
- A few more tweaks to the `Lum\Controllers\Core::model()` method,
  and `Lum\Controllers\Has\Models` trait.

## [1.2.0] - 2025-05-01
### Added
- New `Lum\App` static class with methods for making
  common app bootstrap processes simpler.
- A new `\Lum\Controllers\Core::tableModel()` method that is a wrapper
  around the model() method (which also got a revamp, see below).
### Changed
- Added support for `lum-mailer` version `3.x` which is a rewrite
  that breaks compatibility with the older versions.
- Moved the `has_errors` wrapper from Messages to Notifications,
  as that is where it was supposed to be put when those two were
  split up. Whoops!
- Made some big enhancements to the `Controllers\Core::model()` method.
  It can name have a different name for cached instances than the
  underlying model name. This will be useful for when a single model class 
  may have multiple instances with different datasets.
- Some minor reformatting and adding `/** @disregard */` rules.

## [1.1.0] - 2024-09-19
### Fixed
- A syntax error in `Common/Auth_Token`
### Changed
- `get_auth()` logging preference is in a property now.

## [1.0.1] - 2024-05-31
### Fixed
- Fixed `Models\Common\Plugin` to use `Controllers\Core`.
- Updated test to work with new changes.
### Changed
- Updated README in regards to the upcoming _auth_ API changes.

## [1.0.0] - 2024-01-25
### Added
- Initial release; split from the old `lum-framework` package.
- The `docs/config/legacy.json` config for the `Resources` trait.
### Changed
- Removed all deprecated functionality.
- Reorganized the `Controllers` namespace for more logical consistency.
- Major overhaul to the `Controllers\Has\Resources` trait.
- All DB-specific `Models` are in their own packages now.
- Auth plugins are in the `lum-auth` package now.

[Unreleased]: https://github.com/supernovus/lum.app.php/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/supernovus/lum.app.php/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/supernovus/lum.app.php/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/supernovus/lum.app.php/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/supernovus/lum.app.php/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/supernovus/lum.app.php/releases/tag/v1.0.0

