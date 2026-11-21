<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\APIError;
use SmileIdentity\Errors\SmileIDError;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;

/** Responses, errors and the status-lookup advice for Enhanced KYC sync. */
final class EnhancedKycSyncTest extends TestCase
{
    /** @return array<string, mixed> */
    private function userDetails(): array
    {
        return ['given_names' => 'Amina Fatou', 'last_name' => 'Clearwater', 'email' => 'amina.clearwater@example.com'];
    }

    private function consent(): Consent
    {
        return Consent::granted(
            grantedAt: '2026-03-06T12:00:00.000Z',
            noticeLanguage: 'EN',
            noticePrivacyPolicyUrl: 'https://example.com/privacy',
        );
    }

    /**
     * The Clearwater persona from the submitEnhancedKycSync fixtures, with the
     * ids and receipt from the spec's example.
     */
    public function testVerifySyncParsesTheResultAndReadsTheJobIdFromTheHeader(): void
    {
        $body = [
            'job_id' => 'job_body_ignored',
            'user_id' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
            'status' => 'clear',
            'reason' => null,
            'message' => 'Verification successful',
            'kyc_receipt' => 'https://portal.usesmileid.com/api/v3/001/job_result_summary/job_01h8x9y2z3a4b5c6d7e8f9g0h1/generate_v3_pdf/live?token=REDACTED',
            'partner_params' => ['order_id' => 'ORD-12345'],
        ];
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [
                'SmileID-Job-ID' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
                'User-ID' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
                'Response-Signature' => 'c2lnbmF0dXJl',
                'Response-Timestamp' => '2026-03-10T12:00:04.000Z',
            ], (string) json_encode($body)),
        ]);

        $result = $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());

        self::assertSame('clear', $result->status);
        self::assertSame('Verification successful', $result->message);
        self::assertNull($result->reason);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $result->jobId);
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $result->userId);
        self::assertSame($body['kyc_receipt'], $result->kycReceipt);
        self::assertSame($body, $result->data);
        self::assertSame(['order_id' => 'ORD-12345'], $result->data['partner_params']);
    }

    /** The Ghostwell persona: a blocked result, with the job id only in the body. */
    public function testVerifySyncFallsBackToTheBodyJobId(): void
    {
        $body = ['job_id' => 'job_from_body', 'status' => 'block', 'reason' => 'identifier_not_found', 'message' => 'Verification failed: Unable to validate ID - Not Found'];
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($body))]);

        $result = $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());

        self::assertSame('block', $result->status);
        self::assertSame('identifier_not_found', $result->reason);
        self::assertSame('Verification failed: Unable to validate ID - Not Found', $result->message);
        self::assertSame('job_from_body', $result->jobId);
        self::assertNull($result->userId);
        self::assertNull($result->kycReceipt);
    }

    public function testVerifySyncParsesAnEmptyBody(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{}')]);

        $result = $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());

        self::assertSame('', $result->status);
        self::assertSame('', $result->message);
        self::assertNull($result->reason);
        self::assertNull($result->jobId);
        self::assertSame([], $result->data);
    }

    public function testVerifySyncSendsCallbackUrlOnlyWhenPassed(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), new Response(200, [], '{"status":"clear"}')],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent(), callbackUrl: 'https://example.com/sync-cb');

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame(['https://example.com/sync-cb'], array_column(MultipartParser::named($parts, 'callback_url'), 'body'));
    }

    /** The README's advice: after a 408, read the outcome by job ID instead of resubmitting. */
    public function testVerifySyncTimeoutIsResolvedThroughTheStatusEndpoint(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(408, ['SmileID-Job-ID' => 'job_01h8x9y2z3'], '{"status":"Request Timeout","message":"The ID authority did not respond in time."}'),
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode([
                'status' => 'block',
                'job_id' => 'job_01h8x9y2z3',
                'user_id' => 'user_1',
                'message' => 'Job completed',
            ])),
        ]);

        try {
            $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());
            self::fail('Expected an error.');
        } catch (SmileIDError $e) {
            self::assertSame(408, $e->statusCode);
            self::assertSame('job_01h8x9y2z3', $e->jobId);
            $status = $mock->client->verifications->retrieve($e->jobId);
        }

        self::assertCount(4, $mock->history);
        self::assertSame('https://testapi.smileidentity.com/v3/enhanced_kyc/sync', (string) $mock->request(1)->getUri());
        $request = $mock->request(3);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/status/job_01h8x9y2z3', (string) $request->getUri());
        self::assertTrue($status->isComplete);
        self::assertSame('block', $status->status);
        self::assertSame('job_01h8x9y2z3', $status->jobId);
        self::assertSame('user_1', $status->userId);
    }

    public function testVerifySyncRaisesOnAuthorityUnavailableAndInternalFailure(): void
    {
        foreach ([[424, SmileIDError::class, 'job_424'], [500, APIError::class, 'job_500'], [500, APIError::class, null]] as [$status, $class, $jobId]) {
            $headers = $jobId === null ? [] : ['SmileID-Job-ID' => $jobId];
            $mock = new MockClient([MockClient::tokenResponse(), new Response($status, $headers, '{"status":"x","message":"failed"}')]);
            try {
                $mock->client->enhancedKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());
                self::fail('Expected an error.');
            } catch (SmileIDError $e) {
                self::assertInstanceOf($class, $e);
                self::assertSame($status, $e->statusCode);
                self::assertSame($jobId, $e->jobId);
            }
            self::assertCount(2, $mock->history);
        }
    }

    public function testValidationRejectsBeforeAnyRequest(): void
    {
        $mock = new MockClient([]);

        foreach ([
            'http callback' => fn () => $mock->client->enhancedKyc->verifySync('NG', 'NIN', '1', $this->userDetails(), $this->consent(), callbackUrl: 'http://example.com/cb'),
            'missing email and phone' => fn () => $mock->client->enhancedKyc->verifySync('NG', 'NIN', '1', ['given_names' => 'John', 'last_name' => 'Doe'], $this->consent()),
        ] as $label => $call) {
            try {
                $call();
                self::fail('Expected a ValidationError: ' . $label);
            } catch (ValidationError) {
                self::addToAssertionCount(1);
            }
        }
        self::assertCount(0, $mock->history);
    }
}
