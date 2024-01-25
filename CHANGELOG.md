# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/supernovus/lum.app.php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/supernovus/lum.app.php/releases/tag/v1.0.0

