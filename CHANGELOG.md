# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
