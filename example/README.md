# Smile ID PHP SDK Example

This repository is a small CLI application that demonstrates the public `smileid/usesmileid` PHP SDK.

It also acts as a testbench: PHPUnit runs the same CLI code against Guzzle `MockHandler` responses and verifies the SDK sends the expected requests.

## Requirements

- PHP 8.1 or later.
- Composer.
- Smile ID sandbox credentials for real API calls.

## Setup

```bash
composer install
```

The Composer configuration uses the sibling SDK checkout as a path repository.

## Configuration

```bash
export SMILE_PARTNER_ID="12345"
export SMILE_API_KEY="..."
export SMILE_CALLBACK_URL="https://your-app.example.com/smile-callback"
export SMILE_BASE_URL="https://devapi.smileidentity.com"
```

Partner ids are displayed zero-padded (for example 002) but must be passed without leading zeros (2).

`SMILE_BASE_URL` sets the API host. The SDK only names two environments, sandbox and production, so any other host — devapi, for example — has to come from this variable or the `--base-url` flag.

Optional:

- `SMILE_TIMEOUT` sets the per-request timeout in seconds.

Each variable has a matching global flag: `--partner-id`, `--api-key`, `--base-url`, `--callback-url` and `--timeout`. Global flags go before the command; putting one after the command is a usage error.

## Commands

```bash
php bin/smileid-example-php services --country NG
php bin/smileid-example-php enhanced-kyc --country NG --id-type NIN --id-number 12345678901 --given-names "Amina Fatou" --last-name Clearwater --email amina.clearwater@example.com --privacy-url https://your-app.example.com/privacy
php bin/smileid-example-php status --job-id job_...
php bin/smileid-example-php replay --job-id job_... --callback-url https://your-app.example.com/smile-callback
```

Outside production, Smile ID matches test identities on given names + last name + email. An identity it does not recognise resolves to `block`.

`status` prints the job's own status: `processing` while it runs, then the decision — `clear`, `block`, `attention` or `error`.

## Development

```bash
composer test
composer analyse
composer lint
```
