<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ErrorFactory;
use SmileIdentity\Errors\InvalidRequestError;
use SmileIdentity\Errors\NotFoundError;
use SmileIdentity\Errors\PayloadTooLargeError;
use SmileIdentity\Errors\PaymentRequiredError;
use SmileIdentity\Errors\PermissionError;
use SmileIdentity\Errors\RateLimitError;
use SmileIdentity\Errors\SmileIDError;
use SmileIdentity\Tests\Support\MockClient;

/**
 * Error hierarchy per spec §7: both wire shapes ({status,message} and
 * {error,code}), class chosen by HTTP status, all accessor fields populated;
 * verifications.retrieve returns a not_found JobStatus instead of raising.
 */
final class ErrorHierarchyTest extends TestCase
{
    /** @return array<string, mixed> */
    private function entryArgs(): array
    {
        return [
            'country' => 'NG',
            'idType' => 'NIN',
            'idNumber' => '12345678901',
            'userDetails' => ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            'consent' => Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy'),
        ];
    }

    public function testStatusMessageShape400RaisesInvalidRequestError(): void
    {
        $raw = '{"status":"Bad Request","message":"Either email or phone_number is required."}';
        $mock = new MockClient([MockClient::tokenResponse(), new Response(400, [], $raw)]);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs());
            self::fail('Expected InvalidRequestError');
        } catch (InvalidRequestError $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame('Bad Request', $e->status);
            self::assertSame('Either email or phone_number is required.', $e->getMessage());
            self::assertNull($e->code);
            self::assertSame($raw, $e->rawBody);
        }
    }

    public function testGolden402RaisesPaymentRequiredError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(402, [], '{"status":"Payment Required","message":"Insufficient wallet balance."}'),
        ]);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs());
            self::fail('Expected PaymentRequiredError');
        } catch (PaymentRequiredError $e) {
            self::assertSame(402, $e->statusCode);
            self::assertSame('Insufficient wallet balance.', $e->getMessage());
        }
    }

    public function testGolden413RaisesPayloadTooLargeError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(413, [], '{"status":"Content Too Large","message":"selfie_image is too large."}'),
        ]);

        try {
            $mock->client->documents->verify(
                selfieImage: 'fake-bytes',
                livenessImages: array_fill(0, 6, 'fake-bytes'),
                document: 'fake-bytes',
                consent: Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy'),
                country: 'NG',
                userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            );
            self::fail('Expected PayloadTooLargeError');
        } catch (PayloadTooLargeError $e) {
            self::assertSame(413, $e->statusCode);
            self::assertSame('selfie_image is too large.', $e->getMessage());
        }
    }

    public function testErrorCodeShape403RaisesPermissionErrorWithCode(): void
    {
        $raw = '{"error":"You are not authorized to do that.","code":"2413"}';
        $mock = new MockClient([new Response(403, [], $raw)]);

        try {
            $mock->client->services->bankCodes();
            self::fail('Expected PermissionError');
        } catch (PermissionError $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame('You are not authorized to do that.', $e->getMessage());
            self::assertSame('2413', $e->code);
            self::assertNull($e->status);
            self::assertSame($raw, $e->rawBody);
        }
    }

    public function testIdStatusReorderedShapeRaisesInvalidRequestError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(400, [], '{"message":"\"country\" is required","status":"Bad Request"}'),
        ]);

        try {
            $mock->client->services->idStatus(country: '', idType: 'NIN');
            self::fail('Expected InvalidRequestError');
        } catch (InvalidRequestError $e) {
            self::assertSame('"country" is required', $e->getMessage());
            self::assertSame('Bad Request', $e->status);
        }
    }

    public function testRetrieve404ReturnsNotFoundJobStatusInsteadOfRaising(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(404, [], '{"status":"not_found","job_id":"job_1","user_id":"unknown","message":"Verification not found"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isNotFound);
        self::assertSame('not_found', $status->status);
        self::assertSame('Verification not found', $status->message);
    }

    public function testReplay404StillRaisesNotFoundError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(404, [], '{"status":"Not Found","message":"Verification not found"}'),
        ]);

        $this->expectException(NotFoundError::class);
        $mock->client->verifications->replay('job_1');
    }

    public function testRateLimitAndUnparseableBody(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(429, [], 'not json'),
        ], ['maxRetries' => 0]);

        try {
            $mock->client->verifications->retrieve('job_1');
            self::fail('Expected RateLimitError');
        } catch (RateLimitError $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame('Too Many Requests', $e->getMessage()); // falls back to the reason phrase
            self::assertSame('not json', $e->rawBody);
        }
    }

    public function testClassForCoversWholeTable(): void
    {
        self::assertSame(InvalidRequestError::class, ErrorFactory::classFor(400));
        self::assertSame(InvalidRequestError::class, ErrorFactory::classFor(415));
        self::assertSame(\SmileIdentity\Errors\AuthenticationError::class, ErrorFactory::classFor(401));
        self::assertSame(PaymentRequiredError::class, ErrorFactory::classFor(402));
        self::assertSame(PermissionError::class, ErrorFactory::classFor(403));
        self::assertSame(NotFoundError::class, ErrorFactory::classFor(404));
        self::assertSame(\SmileIdentity\Errors\ConflictError::class, ErrorFactory::classFor(409));
        self::assertSame(PayloadTooLargeError::class, ErrorFactory::classFor(413));
        self::assertSame(RateLimitError::class, ErrorFactory::classFor(429));
        self::assertSame(\SmileIdentity\Errors\APIError::class, ErrorFactory::classFor(500));
        self::assertSame(\SmileIdentity\Errors\APIError::class, ErrorFactory::classFor(503));
        self::assertSame(SmileIDError::class, ErrorFactory::classFor(418));
    }

    public function testEveryErrorExtendsSmileIDErrorAndException(): void
    {
        $error = ErrorFactory::fromResponse(403, '{"error":"nope","code":"2413"}');
        self::assertInstanceOf(SmileIDError::class, $error);
        self::assertInstanceOf(\Exception::class, $error);
    }
}
