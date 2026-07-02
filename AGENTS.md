# AGENTS.md

This repository holds Smile ID's V3 server-side SDK for PHP.

## Source of truth

The OpenAPI specs at [smileidentity/api-reference](https://github.com/smileidentity/api-reference) define the API surface. When the SDK and the specs disagree, the specs win — update this SDK to match them, not the other way round.

## Layout

- `src/Generated/` — generator-owned code. Don't hand-edit files here; a future build phase will populate this directory from the OpenAPI specs and regenerate it on each update.
- `src/Client/`, `src/Errors/`, `src/Helpers/` — hand-written code that wraps and supports the generated client. This is where SDK logic, error handling, and convenience helpers live.

## Running tests

```bash
composer install
composer test
```

or directly:

```bash
vendor/bin/phpunit
```

## Org-wide agent conventions

For conventions that apply across Smile ID repositories, see [smileidentity/agents](https://github.com/smileidentity/agents) (private repo, internal contributors only).
