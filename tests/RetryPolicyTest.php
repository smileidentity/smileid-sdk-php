<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\APIError;
use SmileIdentity\Errors\ConflictError;
use SmileIdentity\Errors\ConnectionError;
use SmileIdentity\Tests\Support\MockClient;

/**
 * Retry policy per spec §2.6: idempotent ops only (GETs + token), retry on
 * 408/429/5xx and connection errors, never on 409, honour Retry-After, entry
 * POSTs never retried.
 */
final class RetryPolicyTest extends TestCase
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

    public function testIdempotentGetRetriesOn503ThenSucceeds(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(503, [], '{"status":"Service Unavailable","message":"try later"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isComplete);
        self::assertCount(3, $mock->history);
        self::assertCount(1, $mock->sleeps);
    }

    public function testRetryAfterHeaderIsHonoured(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(429, ['Retry-After' => '7'], '{"status":"Too Many Requests","message":"slow down"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');

        self::assertSame([7.0], $mock->sleeps);
    }

    public function testRetryAfterHttpDateFormIsHonoured(): void
    {
        $httpDate = gmdate('D, d M Y H:i:s \G\M\T', time() + 10);
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(429, ['Retry-After' => $httpDate], '{"status":"Too Many Requests","message":"slow down"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');

        self::assertCount(1, $mock->sleeps);
        // Allow for up to a couple of seconds of clock movement during the test.
        self::assertGreaterThanOrEqual(8.0, $mock->sleeps[0]);
        self::assertLessThanOrEqual(10.0, $mock->sleeps[0]);
    }

    public function testRetryAfterInThePastFloorsToZero(): void
    {
        $httpDate = gmdate('D, d M Y H:i:s \G\M\T', time() - 30);
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(429, ['Retry-After' => $httpDate], '{"status":"Too Many Requests","message":"slow down"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');

        self::assertSame([0.0], $mock->sleeps);
    }

    public function testRetryAfterIsCappedAtSixtySeconds(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(429, ['Retry-After' => '300'], '{"status":"Too Many Requests","message":"slow down"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');

        self::assertSame([60.0], $mock->sleeps);
    }

    public function testRetriesStopAtMaxRetriesAndRaise(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(500, [], '{"status":"Internal Server Error","message":"boom"}'),
            new Response(500, [], '{"status":"Internal Server Error","message":"boom"}'),
            new Response(500, [], '{"status":"Internal Server Error","message":"boom"}'),
        ], ['maxRetries' => 2]);

        try {
            $mock->client->verifications->retrieve('job_1');
            self::fail('Expected APIError');
        } catch (APIError $e) {
            self::assertSame(500, $e->statusCode);
        }

        // token + initial attempt + 2 retries.
        self::assertCount(4, $mock->history);
    }

    public function testEntryPostIsNeverRetriedOn500(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(500, [], '{"status":"Internal Server Error","message":"boom"}'),
        ]);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs());
            self::fail('Expected APIError');
        } catch (APIError $e) {
            self::assertSame(500, $e->statusCode);
        }

        // Exactly one POST attempt after the token fetch — no retry.
        self::assertCount(2, $mock->history);
        self::assertSame([], $mock->sleeps);
    }

    public function testEntryPostConnectionErrorSurfacesWithoutRetry(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new ConnectException('Connection refused', new Request('POST', '/v3/enhanced_kyc')),
        ]);

        $this->expectException(ConnectionError::class);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs());
        } finally {
            self::assertCount(2, $mock->history);
        }
    }

    public function testIdempotentGetRetriesConnectionErrors(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new ConnectException('Connection reset', new Request('GET', '/v3/status/job_1')),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isComplete);
        self::assertCount(3, $mock->history);
    }

    public function testReplay409IsNeverRetriedAndRaisesConflictError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(409, [], '{"status":"Conflict","message":"Verification is still processing. Callbacks can only be replayed for completed verifications."}'),
        ]);

        try {
            $mock->client->verifications->replay('job_01h8x9y2z3a4b5c6d7e8f9g0h1');
            self::fail('Expected ConflictError');
        } catch (ConflictError $e) {
            self::assertSame(409, $e->statusCode);
            self::assertSame('Conflict', $e->status);
        }

        self::assertCount(2, $mock->history);
        self::assertSame([], $mock->sleeps);
    }

    public function testStatus409OnIdempotentGetIsNotRetried(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(409, [], '{"status":"Conflict","message":"conflict"}'),
        ]);

        $this->expectException(ConflictError::class);

        try {
            $mock->client->verifications->retrieve('job_1');
        } finally {
            self::assertCount(2, $mock->history);
        }
    }

    public function testTokenFetchIsRetried(): void
    {
        $mock = new MockClient([
            new Response(500, [], '{"status":"Internal Server Error","message":"boom"}'),
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isComplete);
        self::assertCount(3, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/token', $mock->request(1)->getUri()->getPath());
    }
}
