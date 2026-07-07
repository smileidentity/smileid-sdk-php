# AGENTS.md

This repository is a standalone example application for the Smile ID PHP SDK.

## Development rules

- Use only the public `SmileIdentity\Client` SDK API.
- Keep tests deterministic with Guzzle `MockHandler`; do not require real Smile ID credentials.
- Keep credentials out of source control and docs.
- Run `composer test` before handing off changes.

## Layout

- `src/App.php` contains command parsing and SDK calls.
- `bin/smileid-example-php` is the CLI entrypoint.
- `tests/AppTest.php` is the SDK testbench.
- `.github/workflows/ci.yml` runs PHPUnit, PHPStan, php-cs-fixer, and Semgrep.
