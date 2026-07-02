<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Errors\AuthenticationError;
use SmileIdentity\Tests\Support\MockClient;

/**
 * Token lifecycle per spec §2.3/§2A: cache until exp−60s, refresh-on-401 once
 * then AuthenticationError, no token on the three unauthenticated services
 * calls, lowercase headers on /v3/token.
 */
final class TokenLifecycleTest extends TestCase
{
    public function testTokenEndpointUsesLowercaseHeadersAndNoBody(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_01h8x9y2z3a4b5c6d7e8f9g0h1');

        $tokenRequest = $mock->request(0);
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/token', (string) $tokenRequest->getUri());
        // Exact documented lowercase header names, sent verbatim.
        self::assertSame('1234', $tokenRequest->getHeaderLine('smileid-partner-id'));
        self::assertSame('test-api-key', $tokenRequest->getHeaderLine('smileid-api-key'));
        // No token on the token call itself, and no body.
        self::assertFalse($tokenRequest->hasHeader('SmileID-Token'));
        self::assertSame('', (string) $tokenRequest->getBody());
        // Telemetry rides every request, including token.
        self::assertSame('php', $tokenRequest->getHeaderLine('SmileID-Source-SDK'));
    }

    public function testTokenIsCachedAcrossCalls(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"processing","job_id":"job_1","user_id":"user_1","message":"..."}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');
        $mock->client->verifications->retrieve('job_1');

        // 3 requests total: one token fetch + two GETs (token NOT refetched).
        self::assertCount(3, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(1)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(2)->getUri()->getPath());
        self::assertSame(
            $mock->request(1)->getHeaderLine('SmileID-Token'),
            $mock->request(2)->getHeaderLine('SmileID-Token'),
        );
    }

    public function testTokenInsideExpirySkewIsRefetched(): void
    {
        // exp only 30s away: inside the 60s skew, so the cache treats it as expired.
        $mock = new MockClient([
            MockClient::tokenResponse(exp: time() + 30),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');
        $mock->client->verifications->retrieve('job_1');

        self::assertCount(4, $mock->history);
        self::assertSame('/v3/token', $mock->request(2)->getUri()->getPath());
    }

    public function testUndecodableTokenIsSingleUse(): void
    {
        $mock = new MockClient([
            new Response(200, [], '{"token":"not-a-jwt"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
            new Response(200, [], '{"token":"still-not-a-jwt"}'),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job_1');
        $mock->client->verifications->retrieve('job_1');

        // Undecodable exp => refresh on every call.
        self::assertCount(4, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/token', $mock->request(2)->getUri()->getPath());
    }

    public function testRefreshOn401OnceThenSuccess(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(401, [], '{"status":"Unauthorized","message":"Token expired"}'),
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"complete","job_id":"job_1","user_id":"user_1","message":"done"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isComplete);
        self::assertCount(4, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(1)->getUri()->getPath());
        self::assertSame('/v3/token', $mock->request(2)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(3)->getUri()->getPath());
    }

    public function testSecond401RaisesAuthenticationError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(401, [], '{"status":"Unauthorized","message":"Token expired"}'),
            MockClient::tokenResponse(),
            new Response(401, [], '{"status":"Unauthorized","message":"Token expired"}'),
        ]);

        try {
            $mock->client->verifications->retrieve('job_1');
            self::fail('Expected AuthenticationError');
        } catch (AuthenticationError $e) {
            self::assertSame(401, $e->statusCode);
            self::assertSame('Token expired', $e->getMessage());
        }

        self::assertCount(4, $mock->history);
    }

    public function testUnauthenticatedServicesCallsNeverFetchToken(): void
    {
        $mock = new MockClient([
            new Response(200, [], '{"bank_codes":[]}'),
            new Response(200, [], '{"id_types":[]}'),
            new Response(200, [], '{"valid_documents":[]}'),
        ]);

        $mock->client->services->bankCodes();
        $mock->client->services->supportedIdTypes();
        $mock->client->services->supportedDocuments();

        self::assertCount(3, $mock->history);
        foreach ($mock->history as $entry) {
            self::assertFalse($entry['request']->hasHeader('SmileID-Token'));
            self::assertStringNotContainsString('/v3/token', (string) $entry['request']->getUri());
        }
    }
}
