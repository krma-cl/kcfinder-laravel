# Changelog

All notable changes to this package are documented here.

## [1.2.1] - 2026-07-15

### Fixed

- Allow all backward-compatible KCFinder 4.x releases from 4.6 onward instead of unnecessarily pinning the bridge to core 4.6.0.

## [1.2.0] - 2026-07-15

### Added

- Official classic browser bridge for the neutral operation observer introduced by KCFinder 4.6.
- Automatic snapshots and Laravel events for classic uploads, edits, moves, renames, deletes and directory creation.
- One event per successfully mutated file in bulk operations, without rescanning storage.

### Changed

- The minimum supported KCFinder core version is now 4.6.
- Listener failures are isolated by the core bridge boundary, preserving successful classic browser mutations and logging the secondary failure.

## [1.1.1] - 2026-07-15

### Fixed

- `SelectedUrlResolverInterface` now extends the core `UrlResolverInterface`, so custom selected URL resolvers only need to implement the specialized adapter contract.

## [1.1.0] - 2026-07-15

### Added

- Versioned JSON operation results with stable errors and non-fatal warnings.
- Native `FileUploaded`, `FileEdited`, `FileMoved`, `FileRenamed`, `FileDeleted` and `DirectoryCreated` Laravel events.
- File snapshots carrying descriptor metadata, streamed checksums and the authenticated user.
- Independent preview and selected URL resolver contracts, with support for Laravel temporary URLs.
- Reporter helpers that let applications synchronize catalogs and audit trails without rescanning storage.

### Compatibility

- Existing selection APIs and legacy URL configuration remain supported.
- Structured responses are opt-in and do not alter the legacy KCFinder browser protocol.

[1.1.0]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.1.0
[1.1.1]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.1.1
[1.2.0]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.2.0
[1.2.1]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.2.1
