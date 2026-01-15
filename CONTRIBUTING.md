# Contributing

Thanks for helping improve Laravel Enumify and the Vite plugin.

## Workflow

1) Create a feature branch from `main`
2) Keep commits focused and scoped
3) Run checks locally
4) Open a PR and ensure CI passes

## Local checks

### Laravel package

```bash
cd packages/laravel-enumify
composer test
composer test-coverage
```

### Vite plugin

```bash
cd packages/vite-plugin-enumify
pnpm run build
pnpm run typecheck
```

## Release notes

- Tag releases after merge to `main`
- Publish PHP package to Packagist and Vite plugin to NPM
