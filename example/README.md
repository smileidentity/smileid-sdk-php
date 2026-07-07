# Smile ID PHP SDK Example

This repository is a small CLI application that demonstrates the public `smileid/smileid` PHP SDK.

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
```

Optional:

- `SMILE_BASE_URL` overrides the SDK environment URL.
- `SMILE_TIMEOUT` sets the per-request timeout in seconds.

## Commands

```bash
php bin/smileid-example-php services --country NG
php bin/smileid-example-php enhanced-kyc --country NG --id-type NIN --id-number 12345678901 --given-names Amina --last-name Okafor --email amina@example.com --privacy-url https://your-app.example.com/privacy
php bin/smileid-example-php status --job-id job_...
php bin/smileid-example-php replay --job-id job_... --callback-url https://your-app.example.com/smile-callback
```

## Development

```bash
composer test
composer analyse
composer lint
```
