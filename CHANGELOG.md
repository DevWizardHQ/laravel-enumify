# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.2.0 - 2026-01-25

### What's Changed

* Refactor tests for enumify: enhance Order model setup and improve path handling by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/12

**Full Changelog**: https://github.com/DevWizardHQ/laravel-enumify/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-01-25

### What's Changed

* feat: Add enumify:refactor command for detection and normalization by @jannatul7355 in https://github.com/DevWizardHQ/laravel-enumify/pull/9
* test: improve test enum path reliability by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/10
* fix: refactor command tests by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/11

### New Contributors

* @jannatul7355 made their first contribution in https://github.com/DevWizardHQ/laravel-enumify/pull/9

**Full Changelog**: https://github.com/DevWizardHQ/laravel-enumify/compare/v1.0.0...v1.1.0

## v1.0.0 - 2026-01-16

### What's Changed

* feat: refactor ts output and install by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/5
* Fix indentation, validation, and formatting issues in TypeScript generator by @Copilot in https://github.com/DevWizardHQ/laravel-enumify/pull/7
* Add localization support for generated TypeScript enums by @Copilot in https://github.com/DevWizardHQ/laravel-enumify/pull/8
* feat: Add localization support for generated TypeScript enums, allowing integration with React/Vue localizer libraries and generating utility hooks. by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/6

**Full Changelog**: https://github.com/DevWizardHQ/laravel-enumify/compare/v0.2.0...v1.0.0

## v0.2.0 - 2026-01-15

### What's Changed

* Fix CI failures: add missing test coverage and resolve prefer-lowest dependency conflicts by @Copilot in https://github.com/DevWizardHQ/laravel-enumify/pull/4
* Preserve exact casing from PHP enum case names in TypeScript by @iqbalhasandev in https://github.com/DevWizardHQ/laravel-enumify/pull/3

### New Contributors

* @Copilot made their first contribution in https://github.com/DevWizardHQ/laravel-enumify/pull/4
* @iqbalhasandev made their first contribution in https://github.com/DevWizardHQ/laravel-enumify/pull/3

**Full Changelog**: https://github.com/DevWizardHQ/laravel-enumify/compare/v0.1.0...v0.2.0

## v0.1.0 - 2026-01-15

### What's Changed

* build(deps): bump actions/setup-node from 4 to 6 by @dependabot[bot] in https://github.com/DevWizardHQ/laravel-enumify/pull/1
* build(deps): bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/DevWizardHQ/laravel-enumify/pull/2

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/DevWizardHQ/laravel-enumify/pull/1

**Full Changelog**: https://github.com/DevWizardHQ/laravel-enumify/commits/v0.1.0

## [Unreleased]

## [0.1.0] - 2026-01-15

### Added

- Initial release
- `enumify:install` command for Wayfinder-like setup experience
- `enumify:sync` command with `--force`, `--dry-run`, `--only`, `--format` options
- Support for BackedEnum (string and int) and UnitEnum
- Label extraction from `label()` method and static `labels()` method
- Custom method extraction (`color()`, `isActive()`, etc.) into TypeScript maps
- Boolean method helper function generation
- Barrel index.ts generation
- Manifest file for change tracking
- Atomic file writes with hash-based caching
- `.gitkeep` preservation
- Configurable file naming (kebab, camel, pascal)
- Configurable export style (enum, const)
- Include/exclude filters with glob pattern support
