# Changelog

## [1.4.0] - 2026-07-17

- Discover the optional Bootstrap 5 Composer package as an external KCFinder
  theme root.
- Serve mounted theme assets and dynamic CSS/JavaScript entrypoints through the
  authenticated browser route.
- Keep the core and theme packages immutable inside `vendor`.
- Require KCFinder 4.9 external theme root support.

## [1.3.1] - 2026-07-17

- Let `kcfinder:install-assets` detect and publish the optional Composer
  Bootstrap 5 theme package.
- Record core and theme versions in the published asset manifest.

## [1.3.0] - 2026-07-17

- Add the optional authenticated Laravel HTTP bridge for the classic browser.
- Initialize an isolated native session and synchronize CSRF on the first request.
- Add safe static asset publication and cache clearing Artisan commands.
- Emit `FileCopied`, `DirectoryRenamed` and `DirectoryDeleted`.
- Require KCFinder core 4.8.1 for trusted runtime observer injection.
- Document the local-disk boundary and configurable security headers.

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
[1.3.0]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.3.0
[1.3.1]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.3.1
[1.4.0]: https://github.com/krma-cl/kcfinder-laravel/releases/tag/v1.4.0
