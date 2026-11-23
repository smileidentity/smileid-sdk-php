# AGENTS.md

This repository holds Smile ID's V3 server-side SDK for PHP.

## Source of truth

The API surface (endpoints, request and response shapes) comes from `specs/v3/openapi.yaml` in [smileidentity/api-reference](https://github.com/smileidentity/api-reference). Read it at a release tag such as `v3.1115.0`, not at `main`. When a task or pull request names a tag, use that one. When the SDK and the spec disagree, the spec wins.

- Ignore anything marked `x-smile-sdk-exclude: true` or `x-internal: true`. These are server-to-server details, such as the HMAC signature headers, that the SDKs leave out on purpose. The SDK doesn't sign requests. It authenticates every call with the spec's `SmileIDToken` scheme: it gets a token from `POST /v3/token` and sends it in the `SmileID-Token` header, in `src/Client/Transport.php`. Keep that code when you ignore the excluded headers.
- Don't add endpoints or fields that aren't in the spec. Internal endpoints are kept out of it on purpose.

## Layout

- `src/Generated/` — wire models (`Models/`) and per-operation functions (`Operations/`). This directory is hand-written for now, because there is no generator yet. Edit it by hand, but keep it shaped like generator output: wire models and one thin function per operation, with no business logic. A generator will replace it later.
- `src/Client/`, `src/Errors/`, `src/Helpers/` — hand-written code that wraps and supports the generated client. This is where SDK logic, error handling, and convenience helpers live.

## Keeping the SDKs in step

This SDK is one of the `smileid-sdk-*` repositories, which share one design. A change to the public API here should be made the same way in the others: the same operations, option names and defaults, adjusted only for each language's naming conventions.

Automated pull requests use these labels and branches:

| Label | Branch | Meaning |
|---|---|---|
| `spec-sync` | `spec-sync/rolling` | Brings the SDK up to a new spec tag. There is one rolling pull request at a time, and the automation finds it by this branch name, so don't open another. |
| `spec-sync` | `spec-sync/<scope>` | A manual run limited to one part of the spec, such as AML operations. |
| `spec-sync` and `breaking` | `spec-sync/breaking-<tag>` | A change that needs a major release. Breaking changes always get their own pull request. |
| `release` | `release/vX.Y.Z` | A version bump and changelog entry. Made by hand for now. An automated release train is planned. |

Porting changes between SDKs automatically, with the `parity-port` label, is planned but not built yet.

The `breaking` label is not only for automation. CI fails any pull request that breaks the public API unless it has the `breaking` label, including pull requests made by hand.

Add `parity:skip` to a pull request that should not be ported to the other SDKs.

Tests:

- Every new operation needs a golden fixture test, which checks the exact request body and headers, and tests that parse its responses. Golden fixture tests live in `tests/GoldenFixtureTest.php`; follow the existing cases there. Shared persona cases for integration tests live in the private `smileid-sdk-fixtures` repository.
- Changed lines must be fully covered by tests. Don't add coverage exclusion comments.

## Spec coverage

`spec-coverage.json` accounts for every public operation in the spec at its `spec_tag`. Each operation names the method a caller uses, such as `client->basicKyc->verifySync`, its path, and where a caller reads each response header the spec declares. Anything the SDK doesn't do is marked `unsupported` with a reason. The `spec-coverage` workflow checks the file against the spec, and `tests/SpecCoverageTest.php` checks it against the code: every method must exist, and the supported paths must be exactly the paths in `src/Generated/Operations/Operations.php` and the token path in `src/Client/Transport.php`.

A change that adds, removes or stops supporting an operation, or starts or stops exposing a response header, must update `spec-coverage.json` in the same pull request. The file is kept out of the Composer package by `.gitattributes`.

## Running tests

```bash
composer install
composer test
```

or directly:

```bash
vendor/bin/phpunit
```

`php-cs-fixer` and `phpstan` are pinned to exact versions in `composer.json`, so a new release of either cannot turn CI red. Bump them on purpose, in their own pull request.

## Quality gates

`.github/workflows/quality-gates.yml` runs on every pull request:

- `api-compat` compares the public API with the newest stable `vX.Y.Z` tag on the base branch using `roave/backward-compatibility-check`. A new break fails unless the PR has the `breaking` label. Run it locally with `composer install -d tools/backward-compatibility-check` (PHP 8.4 or later) and `scripts/check-api-compat.sh origin/main`. The tool's `composer.lock` is committed, so CI and local runs use the same Roave version. The script fails if it cannot parse Roave's output.
- `coverage` uploads Cobertura XML as the `coverage` artifact and requires 100% line coverage of `src/Generated/`. On bot PRs (labelled `spec-sync`, `parity-port` or `release`, or opened by a `[bot]` account) it also requires every changed line to be covered and blocks new coverage exclusions (`scripts/check-coverage-exclusions.sh`). Don't add exclusions; add tests.
- `release-guards` runs on release pull requests (labelled `release`, or on a `release/vX.Y.Z` branch). It runs `scripts/check-release.sh <tag> HEAD`, the same guards `release.yml` runs on the tag, and also fails if the tag already exists. Tags cannot be moved, so a guard that fails only after tagging uses up the version.
- `scripts` runs shellcheck and the tests in `scripts/tests/`. `scripts/bump-version.sh <major|minor|patch>` updates the version files and cuts the changelog's Unreleased section.

`release.yml` runs `scripts/check-release.sh`, which checks `src/Version.php` and the dated `CHANGELOG.md` heading, then runs `scripts/check-release-breaking.sh`. It looks at the PRs whose commits are between the previous stable tag and the new one. It refuses a tag that is not a major bump when one of them is labelled `breaking`, and a major bump when none is (except 0.x to 1.0.0).

## Org-wide agent conventions

For conventions that apply across Smile ID repositories, see [smileidentity/agents](https://github.com/smileidentity/agents) (private repo, internal contributors only).
