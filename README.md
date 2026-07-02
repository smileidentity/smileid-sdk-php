# smile-identity/core

![Packagist version](https://img.shields.io/badge/packagist-unreleased-lightgrey)
![CI status](https://img.shields.io/badge/CI-pending-lightgrey)
![License](https://img.shields.io/badge/license-MIT-blue)

Official Smile ID server-side SDK for PHP — V3 APIs.

This project is under active development. It's not yet published to Packagist, and the API surface may change without notice. Don't use it in production yet.

## Requirements

- PHP 8.1 or later
- Composer

## Install

```bash
composer require smile-identity/core
```

## Create a client

Construct one client with your partner ID and API key. The SDK fetches and refreshes its own access token — you never handle tokens yourself.

```php
use SmileIdentity\Client;

$smile = new Client(
    partnerId: '1234',
    apiKey: getenv('SMILE_API_KEY'),
    environment: 'sandbox',                              // default
    defaultCallbackUrl: 'https://app.example.com/cb',    // optional
);
```

Constructor options:

| Option | Required | Default | Notes |
| --- | --- | --- | --- |
| `partnerId` | yes | — | numeric string, no leading zeros |
| `apiKey` | yes | — | your partner API key |
| `environment` | no | `sandbox` | `sandbox` or `production` |
| `partnerSecret` | no | unset | enables HMAC request signing when set |
| `defaultCallbackUrl` | no | unset | used when a call omits `callbackUrl` |
| `baseUrl` | no | derived | explicit override; wins over `environment` |
| `timeout` | no | `30.0` | per-request total timeout in seconds |
| `maxRetries` | no | `2` | retries for idempotent operations only |
| `httpClient` | no | Guzzle | any `GuzzleHttp\ClientInterface`, injectable for testing |

## Environments

- `sandbox` (default) → `https://testapi.smileidentity.com`
- `production` → `https://api.smileidentity.com`

Pass `baseUrl` to override either.

## Binary inputs

Methods that upload images accept a file path, an open stream resource, or a string of raw bytes. When a plain string is ambiguous, use the explicit factories:

```php
use SmileIdentity\Helpers\BinaryInput;

BinaryInput::fromPath('/tmp/selfie.jpg');
BinaryInput::fromString($bytes, 'selfie.jpg');
BinaryInput::fromResource(fopen('/tmp/selfie.jpg', 'r'), 'selfie.jpg');
```

## Consent and user details

All verification submissions need a consent block and the user's details. Build consent with `Consent::granted(...)`, which always records consent as given. `userDetails` is an associative array with wire field names; at least one of `email` or `phone_number` is required.

```php
use SmileIdentity\Consent;

$consent = Consent::granted(
    grantedAt: new DateTimeImmutable(),
    noticeLanguage: 'EN',
    noticePrivacyPolicyUrl: 'https://example.com/privacy',
);

$userDetails = ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
```

The examples below assume `$smile`, `$consent` and `$userDetails` from above.

## Methods

### Enhanced KYC

Verify an ID number against the issuing authority.

```php
$accepted = $smile->enhancedKyc->verify(
    country: 'NG',
    idType: 'NIN',
    idNumber: '12345678901',
    userDetails: $userDetails,
    consent: $consent,
);

$accepted->jobId;        // "job_..."
$accepted->isAccepted;   // true
```

### Document verification

Verify a selfie and liveness images against a photo ID document. `livenessImages` takes 6 to 8 images.

```php
$accepted = $smile->documents->verify(
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
    document: '/tmp/passport.jpg',
    consent: $consent,
    country: 'NG',
    userDetails: $userDetails,
);
```

### Enhanced document verification

The same as document verification, but `idType` is required and the document is also checked against the issuing authority.

```php
$accepted = $smile->documents->verifyEnhanced(
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
    document: '/tmp/passport.jpg',
    consent: $consent,
    country: 'NG',
    idType: 'PASSPORT',
    userDetails: $userDetails,
);
```

### Biometric KYC

Verify a selfie against the photo on file with an ID authority.

```php
$accepted = $smile->biometricKyc->verify(
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
    consent: $consent,
    country: 'NG',
    idType: 'BVN',
    idNumber: '12345678901',
    userDetails: $userDetails,
);
```

### Biometric enrollment

Register a user's selfie for later authentication.

```php
$accepted = $smile->biometric->enroll(
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
    consent: $consent,
    userDetails: $userDetails,
    userId: 'user-123',
);
```

### Biometric authentication

Authenticate a returning user against their enrolled selfie. `userId` must match an enrollee. Pass `useEnrolledImage: true` to skip new images.

```php
$accepted = $smile->biometric->authenticate(
    userId: 'user-123',
    consent: $consent,
    userDetails: $userDetails,
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
);
```

### Selfie compare

Compare a selfie with another image: a document photo, an ID photo, or a portrait.

```php
$accepted = $smile->biometric->compare(
    selfieImage: '/tmp/selfie.jpg',
    comparisonImage: '/tmp/id_photo.jpg',
    comparisonImageType: 'ID_PHOTO',    // DOCUMENT | ID_PHOTO | PORTRAIT
    consent: $consent,
    userDetails: $userDetails,
);
```

### Check a verification

```php
$status = $smile->verifications->retrieve($accepted->jobId);

$status->status;      // "complete", "processing" or "not_found"
$status->isComplete;  // true when terminal
$status->message;     // e.g. "Verification completed with state: clear"
```

A job that is not found returns a `JobStatus` with `status` of `not_found` rather than throwing, so you can poll a job that has not landed yet.

### Wait for a verification to complete

```php
$status = $smile->verifications->waitUntilComplete(
    $accepted->jobId,
    interval: 2.0,     // seconds between polls
    timeout: 60.0,     // seconds before TimeoutError
    treatNotFoundAsPending: true,
);
```

Throws `SmileIdentity\Errors\TimeoutError` if the job does not complete in time.

### Replay a callback

Ask Smile ID to resend the callback for a completed verification. Replaying a job that is still processing throws `ConflictError`.

```php
$replay = $smile->verifications->replay($accepted->jobId, callbackUrl: 'https://app.example.com/cb');
```

### Report user fraud

`reason` is required when reporting fraud; `notes` is required when clearing a report or when the reason is `OTHER`.

```php
$report = $smile->users->reportFraud(
    'user-123',
    isFraud: true,
    reportedBy: 'ops@example.com',
    reason: 'ACCOUNT_TAKEOVER',
);

// Convenience wrappers:
$smile->users->flagFraud('user-123', reason: 'MULE_ACCOUNT', reportedBy: 'ops@example.com');
$smile->users->clearFraud('user-123', notes: 'False positive', reportedBy: 'ops@example.com');
```

### Bank codes

No authentication needed.

```php
$banks = $smile->services->bankCodes(country: 'NG');
$banks->bankCodes[0]->code;   // "044"
```

### Supported ID types

No authentication needed.

```php
$idTypes = $smile->services->supportedIdTypes(country: 'NG');
$idTypes->idTypes[0]->type;   // "BVN"
```

### Supported documents

No authentication needed.

```php
$documents = $smile->services->supportedDocuments(countryCode: 'NG');
$documents->validDocuments[0]->idTypes[0]->code;   // "DRIVERS_LICENSE"
```

### ID authority status

Check whether an ID authority is currently reachable.

```php
$idStatus = $smile->services->idStatus(country: 'NG', idType: 'NIN');
$idStatus->lastKnownStatus;   // "online"
```

## Error handling

Every error the SDK throws extends `SmileIdentity\Errors\SmileIDError`, which extends `\Exception`. Catch the base class to handle everything, or a subclass to handle one case:

```php
use SmileIdentity\Errors\PaymentRequiredError;
use SmileIdentity\Errors\SmileIDError;

try {
    $smile->enhancedKyc->verify(/* ... */);
} catch (PaymentRequiredError $e) {
    // top up the wallet
} catch (SmileIDError $e) {
    $e->getMessage();   // human-readable message
    $e->statusCode;     // HTTP status, null for local/connection errors
    $e->status;         // status text from the body, when present
    $e->code;           // API error code, e.g. "2413" (services endpoints only)
    $e->rawBody;        // the unparsed response body
}
```

| Class | Raised on |
| --- | --- |
| `InvalidRequestError` | HTTP 400 or 415 |
| `ValidationError` | client-side validation, before anything is sent |
| `AuthenticationError` | HTTP 401 after one token refresh attempt |
| `PaymentRequiredError` | HTTP 402, insufficient wallet balance |
| `PermissionError` | HTTP 403 |
| `NotFoundError` | HTTP 404 (except `verifications->retrieve`) |
| `ConflictError` | HTTP 409, replay of a job still processing |
| `PayloadTooLargeError` | HTTP 413 |
| `RateLimitError` | HTTP 429 |
| `APIError` | HTTP 5xx |
| `ConnectionError` | network failure, no HTTP response |
| `TimeoutError` | `waitUntilComplete` deadline passed |

## Retries

The SDK retries idempotent operations only: status and services reads, and its internal token fetch. It retries on connection errors, 408, 429 and 5xx, honours the `Retry-After` header, and never retries a 409. Verification submissions are never retried automatically — a connection failure surfaces as `ConnectionError` and you decide whether to resubmit.

## Telemetry

Every request carries three headers identifying the SDK: `SmileID-Source-SDK: php`, `SmileID-Source-SDK-Version`, and a `User-Agent` of the form `smileid-sdk-php/<version> (php/<php-version>)`. They are observability metadata, never authentication, and carry no personal data.

## HMAC request signing

Request signing is off by default. Setting `partnerSecret` turns it on: each request then carries a `SmileID-Timestamp` header and a `SmileID-Request-Signature` header computed over the timestamp and the raw request body. The exact signature construction is provisional and must be confirmed with Smile ID before you rely on it in production.

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

MIT. See [LICENSE](LICENSE).
