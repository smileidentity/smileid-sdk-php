# smileid/usesmileid

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
composer require smileid/usesmileid
```

## Create a client

Construct one client with your partner ID and API key. Before each request the SDK fetches a short-lived access token. A verification job's token is bound to that job's product and, when the job names one, its user; other calls get a token with neither. You never handle tokens yourself.

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
| `partnerId` | yes | — | numeric string; partner ids are displayed zero-padded (for example 002) but must be passed without leading zeros (2) |
| `apiKey` | yes | — | your partner API key |
| `environment` | no | `sandbox` | `sandbox` or `production` |
| `defaultCallbackUrl` | no | unset | used when a call omits `callbackUrl`; must be https |
| `baseUrl` | no | derived | explicit override; wins over `environment`; must be an absolute https URL with no query or fragment |
| `timeout` | no | `30.0` | per-request total timeout in seconds |
| `maxRetries` | no | `2` | retries for idempotent operations only |
| `httpClient` | no | Guzzle | any `GuzzleHttp\ClientInterface`, injectable for testing |

## Environments

`environment` names two hosts:

- `sandbox` (default) → `https://testapi.smileidentity.com`
- `production` → `https://api.smileidentity.com`

Any other host needs `baseUrl`, which wins over `environment`:

```php
$smile = new Client(
    partnerId: '2',
    apiKey: getenv('SMILE_API_KEY'),
    baseUrl: 'https://your-environment.example.com',
);
```

Outside production, Smile ID matches test identities on given names + last name + email. An identity it does not recognise resolves to `block`.

All URLs you give the SDK must be https. The base URL must be absolute with no query or fragment, and callback URLs (`defaultCallbackUrl` and per-request `callbackUrl`) must be absolute https URLs. Anything else raises `ValidationError` before a request is made.

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

`verifySync` takes the same arguments and returns the result in the response, so you need no callback endpoint. The body is the payload the callback would have carried. It is never retried automatically. If it raises a 408 or 424 error, the job may still have run. Resubmitting runs and bills a second lookup, so don't resubmit automatically. Read the outcome with `verifications->retrieve()`, using the job ID from the error. Do the same for a 500 error that has a job ID. A 500 error with no job ID means the job was not created.

```php
$result = $smile->enhancedKyc->verifySync(
    country: 'NG',
    idType: 'NIN',
    idNumber: '12345678901',
    userDetails: $userDetails,
    consent: $consent,
);

$result->status;   // "clear", "attention", "block" or "error"
$result->reason;   // e.g. "identifier_not_found", or null
$result->jobId;              // "job_...", from the SmileID-Job-ID header
$result->userId;             // from the User-ID header
$result->data;               // the whole response body, including fields not typed on the result
$result->responseSignature;  // the Response-Signature header
$result->responseTimestamp;  // the Response-Timestamp header
```

The response carries the same `Response-Signature` and `Response-Timestamp` headers as the callback. The result exposes them so you can check them with your own webhook signature code. The SDK does not check them.

```php
use SmileIdentity\Errors\SmileIDError;

try {
    $result = $smile->enhancedKyc->verifySync(/* ... */);
} catch (SmileIDError $e) {
    if (!in_array($e->statusCode, [408, 424, 500], true) || $e->jobId === null) {
        throw $e;
    }
    $status = $smile->verifications->retrieve($e->jobId);
}
```

### Basic KYC

Check the identity details you submit against the ID authority's record. The result reports a match verdict for each field and never returns the record. It goes to your callback URL.

```php
$accepted = $smile->basicKyc->verify(
    country: 'NG',
    idType: 'NIN',
    idNumber: '12345678901',
    userDetails: $userDetails,
    consent: $consent,
);
```

`verifySync` takes the same arguments and returns the result in the response, so you need no callback endpoint. It is never retried automatically. If it raises a 408 or 424 error, the job may still have run. Resubmitting runs and bills a second lookup, so don't resubmit automatically. Read the outcome with `verifications->retrieve()`, using the job ID from the error. Do the same for a 500 error that has a job ID. A 500 error with no job ID means the job was not created.

```php
$result = $smile->basicKyc->verifySync(
    country: 'NG',
    idType: 'NIN',
    idNumber: '12345678901',
    userDetails: $userDetails,
    consent: $consent,
);

$result->status;                      // "clear", "attention", "block" or "error"
$result->matchResults['overall'] ?? null; // "exact_match", or null when there are no match results
$result->jobId;                       // "job_..."
$result->userId;                      // "user_...", from the User-ID header
$result->responseSignature;           // the Response-Signature header, or null
$result->responseTimestamp;           // the Response-Timestamp header, or null
```

```php
use SmileIdentity\Errors\SmileIDError;

try {
    $result = $smile->basicKyc->verifySync(/* ... */);
} catch (SmileIDError $e) {
    if (!in_array($e->statusCode, [408, 424, 500], true) || $e->jobId === null) {
        throw $e;
    }
    $status = $smile->verifications->retrieve($e->jobId);
}
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

### Residency document verification

Verify a selfie against a passport and read the visa endorsed in it. The passport and visa are separate images, and `idType` is optional and defaults to `PASSPORT`, the only value the API accepts. Any other value throws a `ValidationError` before the request is sent. The result arrives by callback.

```php
$accepted = $smile->documents->verifyResidency(
    selfieImage: '/tmp/selfie.jpg',
    livenessImages: glob('/tmp/liveness/*.jpg'),
    document: '/tmp/passport.jpg',
    visa: '/tmp/visa.jpg',
    consent: $consent,
    country: 'ZA',
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

To verify against a user's enrolled selfie instead of a fresh capture, pass `useEnrolledImage: true` and the enrolled `userId`; the selfie and liveness images may then be omitted.

```php
$accepted = $smile->biometricKyc->verify(
    useEnrolledImage: true,
    userId: 'user-123',
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

Authenticate a returning user against a fresh selfie and liveness capture. `userId` must match an enrollee.

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
    comparisonImageType: 'ID_PHOTO',    // DOCUMENT | ID_PHOTO | PORTRAIT (validated by the server)
    consent: $consent,
    userDetails: $userDetails,
);
```

### Check a verification

```php
$status = $smile->verifications->retrieve($accepted->jobId);

$status->status;      // "processing", "not_found", or the decision: "clear", "block", "attention", "error"
$status->isComplete;  // true when terminal, i.e. neither processing nor not_found
$status->message;     // e.g. "Job completed"
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

Polls while the status is `processing` (and, by default, while it is `not_found`), and returns as soon as the job reports a decision. Throws `SmileIdentity\Errors\TimeoutError` if the job does not complete in time.

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

### AML screening

Screen a name against sanctions, PEP and adverse-media lists. `countries` is a list of uppercase ISO 3166-1 alpha-2 codes. The result goes to your callback URL.

```php
$accepted = $smile->aml->oneTime(
    countries: ['NG', 'KE'],
    fullName: 'John Doe',
    userDetails: $userDetails,
    consent: $consent,
);
```

`oneTimeSync` takes the same arguments and returns the result in the response, so you need no callback endpoint. Pass an `idempotencyKey` (16 to 128 characters of `A-Z a-z 0-9 - _`) so a retry returns the original result. The SDK retries a timed-out call only when you pass a key.

If it raises a 408 error, nothing was billed and the job has ended in error, so its status will only ever report that error. Resubmit rather than poll, with the same `idempotencyKey` so the resubmission is safe to repeat. If it raises a 500 error that has a job ID, read the outcome with `verifications->retrieve()`, using the job ID from the error.

```php
$result = $smile->aml->oneTimeSync(
    countries: ['NG'],
    fullName: 'John Doe',
    userDetails: $userDetails,
    consent: $consent,
    idempotencyKey: 'onboarding-8f14e45fceea167a',
);

$result->status;            // "clear", "attention", "block" or "error"
$result->noOfPersonsFound;  // 0
$result->jobId;             // "job_..."
$result->userId;            // "user_...", from the User-ID header
$result->responseSignature; // the Response-Signature header, or null
$result->responseTimestamp; // the Response-Timestamp header, or null
```

`monitoring` screens a name and enrols it for ongoing monitoring. `monitoringNews` reads the adverse-media articles for one person in a monitoring job.

```php
$accepted = $smile->aml->monitoring(
    countries: ['NG'],
    userDetails: $userDetails,
    consent: $consent,
    fullName: 'Jane Doe',
);

$news = $smile->aml->monitoringNews(
    jobId: $accepted->jobId,
    reference: 'ref_001',
    newsCategory: 'financial_crime',
    page: 1,
    limit: 25,
);
```

### Phone number verification

Check who a mobile number is registered to, and how well that matches the names you supply. `phoneNumber` is the number to check, in E.164 format. `userDetails` holds the consumer's own details, which are never the number checked. Kenya requires `idNumber`, and Ghana requires `operator` (`MTN`, `VODAFONE` or `AIRTELTIGO`). The result goes to your callback URL.

```php
$accepted = $smile->phone->verify(
    country: 'NG',
    phoneNumber: '+2348012345678',
    userDetails: $userDetails,
    consent: $consent,
);
```

### Address verification

Check an address against the address on record with a data provider, in Nigeria (`NG`) or South Africa (`ZA`). Send `idNumber` or `utilityNumber`. Nigeria needs `utilityNumber`, `utilityProvider` and `utilityType` (`PrePaid` or `PostPaid`). South Africa needs `idNumber`. The result goes to your callback URL.

```php
$accepted = $smile->address->verify(
    address: '12 Test Road, Testville',
    country: 'NG',
    userDetails: $userDetails,
    consent: $consent,
    utilityNumber: '0123456789',
    utilityProvider: 'IKEDC',
    utilityType: 'PrePaid',
);
```

### Smile Links

A Smile Link is a URL you give to end users. Opening it runs a verification flow and creates a job for you. `idTypes` lists the verification configurations, each with the wire keys `country`, `id_type` and `verification_method`. The calls are synchronous and use a token with no product claim.

```php
$idTypes = [['country' => 'NG', 'id_type' => 'NIN', 'verification_method' => 'enhanced_kyc']];

$created = $smile->smileLinks->create(
    idTypes: $idTypes,
    name: 'Onboarding',
    noticePrivacyPolicyUrl: 'https://example.com/privacy',
    isSingleUse: false,
);
$created->link;  // "https://links.usesmileid.com/v3/..."
$created->refId;

$link = $smile->smileLinks->get($created->refId);
$link = $smile->smileLinks->update($created->refId, isDisabled: true);

$page = $smile->smileLinks->list(limit: 20, page: 1, singleUse: false);
$page->links[0]->jobStats?->totalJobs;
$page->totalPages;
```

`update` takes `idTypes`, `isDisabled` or both. Nothing else on a link can change after it is created.

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
    $e->jobId;          // the SmileID-Job-ID response header, null when absent
}
```

| Class | Raised on |
| --- | --- |
| `InvalidRequestError` | HTTP 400 or 415 |
| `ValidationError` | client-side validation, before anything is sent |
| `AuthenticationError` | HTTP 401 |
| `PaymentRequiredError` | HTTP 402, insufficient wallet balance |
| `PermissionError` | HTTP 403 |
| `NotFoundError` | HTTP 404 (except `verifications->retrieve`) |
| `ConflictError` | HTTP 409, replay of a job still processing |
| `PayloadTooLargeError` | HTTP 413 |
| `RateLimitError` | HTTP 429 |
| `UnexpectedResponseError` | a 2xx response whose body is not a JSON object |
| `APIError` | HTTP 5xx |
| `ConnectionError` | network failure, no HTTP response |
| `TimeoutError` | `waitUntilComplete` deadline passed |

## Retries

The SDK retries idempotent operations only: status and services reads, and the token fetch made before each request. It retries on connection errors, 408, 429 and 5xx, honours the `Retry-After` header, and never retries a 409. Verification submissions are never retried automatically, except `aml->oneTimeSync` when you pass an `idempotencyKey` — a connection failure surfaces as `ConnectionError` and you decide whether to resubmit.

## Telemetry

Every request carries three headers identifying the SDK: `SmileID-Source-SDK: php`, `SmileID-Source-SDK-Version`, and a `User-Agent` of the form `smileid-sdk-php/<version> (php/<php-version>)`. They are observability metadata, never authentication, and carry no personal data.

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

MIT. See [LICENSE](LICENSE).
