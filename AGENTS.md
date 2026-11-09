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

## Quality gates

`.github/workflows/quality-gates.yml` runs on every pull request:

- `api-compat` compares the public API with the latest `v*` tag using `roave/backward-compatibility-check`. A new break fails unless the PR has the `breaking` label. Run it locally with `composer install -d tools/backward-compatibility-check` (PHP 8.4 or later) and `scripts/check-api-compat.sh origin/main`.
- `coverage` uploads Cobertura XML as the `coverage` artifact and requires 100% line coverage of `src/Generated/`. On bot PRs (labelled `spec-sync`, `parity-port` or `release`, or opened by a `[bot]` account) it also requires every changed line to be covered and blocks new coverage exclusions (`scripts/check-coverage-exclusions.sh`). Don't add exclusions; add tests.
- `release-scripts` tests `scripts/bump-version.sh <major|minor|patch>`, which updates the version files and cuts the changelog's Unreleased section.

`release.yml` refuses a tag that is not a major bump when a PR labelled `breaking` merged since the previous tag, and a major bump when none did.

## Org-wide agent conventions

For conventions that apply across Smile ID repositories, see [smileidentity/agents](https://github.com/smileidentity/agents) (private repo, internal contributors only).
