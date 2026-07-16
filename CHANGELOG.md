# Changelog

All notable changes to this package are documented here.

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
