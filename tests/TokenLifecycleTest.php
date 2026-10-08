<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SmileIdentity\Consent;
use SmileIdentity\Errors\AuthenticationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;

/**
 * Token lifecycle: every authenticated request mints its own token bound to
 * that request's user, product and grant; a 401 is not retried; no token on the
 * unauthenticated services calls; lowercase headers on /v3/token.
 */
final class TokenLifecycleTest extends TestCase
{
    private const FAKE_JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-bytes";
    private const STATUS_BODY = '{"status":"clear","job_id":"job_1","user_id":"user_1","message":"Job completed"}';

    private function consent(): Consent
    {
        return Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy');
    }

    /**
     * @return array<string, mixed>
     */
    private function userDetails(): array
    {
        return ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
    }

    /**
     * @return array<int, string>
     */
    private function livenessImages(): array
    {
        return array_fill(0, 7, self::FAKE_JPEG);
    }

    /**
     * @return array<string, string>
     */
    private function claims(RequestInterface $tokenRequest): array
    {
        if ((string) $tokenRequest->getBody() === '') {
            return [];
        }

        $claims = [];
        foreach (MultipartParser::parse($tokenRequest) as $part) {
            $claims[(string) $part['name']] = $part['body'];
        }

        return $claims;
    }

    public function testTokenEndpointUsesLowercaseHeadersAndNoBody(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], self::STATUS_BODY),
        ]);

        $mock->client->verifications->retrieve('job_01h8x9y2z3a4b5c6d7e8f9g0h1');

        $tokenRequest = $mock->request(0);
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/token', (string) $tokenRequest->getUri());
        // Exact documented lowercase header names, sent verbatim.
        self::assertSame('1234', $tokenRequest->getHeaderLine('smileid-partner-id'));
        self::assertSame('test-api-key', $tokenRequest->getHeaderLine('smileid-api-key'));
        // No token on the token call itself, and no body when no claims are bound.
        self::assertFalse($tokenRequest->hasHeader('SmileID-Token'));
        self::assertSame('', (string) $tokenRequest->getBody());
        // Telemetry rides every request, including token.
        self::assertSame('php', $tokenRequest->getHeaderLine('SmileID-Source-SDK'));
    }

    public function testEachJobMintsItsOwnToken(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"processing","job_id":"job_1","user_id":"user_1","message":"..."}'),
            MockClient::tokenResponse(),
            new Response(200, [], self::STATUS_BODY),
        ]);

        $mock->client->verifications->retrieve('job_1');
        $mock->client->verifications->retrieve('job_1');

        self::assertCount(4, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(1)->getUri()->getPath());
        self::assertSame('/v3/token', $mock->request(2)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(3)->getUri()->getPath());
    }

    public function testTokenBindsUserAndProduct(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->enhancedKyc->verify(
            consent: $this->consent(),
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            userId: 'user-1',
        );

        self::assertSame(
            ['user_id' => 'user-1', 'product' => 'enhanced_kyc'],
            $this->claims($mock->request(0)),
        );
    }

    public function testTokenBindsBodyUserIdForAuthentication(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->authenticate(
            userId: 'user-1',
            consent: $this->consent(),
            userDetails: $this->userDetails(),
            selfieImage: self::FAKE_JPEG,
            livenessImages: $this->livenessImages(),
        );

        self::assertSame(
            ['user_id' => 'user-1', 'product' => 'smart_selfie_authentication'],
            $this->claims($mock->request(0)),
        );
    }

    public function testUnauthorizedIsNotRetried(): void
    {
        $mock = new MockClient([
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

        self::assertCount(2, $mock->history);
    }

    public function testRetriesReuseTheRequestToken(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(503, [], '{"status":"error","message":"busy"}'),
            new Response(200, [], self::STATUS_BODY),
        ]);

        $mock->client->verifications->retrieve('job_1');

        self::assertCount(3, $mock->history);
        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(1)->getUri()->getPath());
        self::assertSame('/v3/status/job_1', $mock->request(2)->getUri()->getPath());
        self::assertSame(
            $mock->request(1)->getHeaderLine('SmileID-Token'),
            $mock->request(2)->getHeaderLine('SmileID-Token'),
        );
    }

    public function testEnrolledImageGrantIsBoundToTheJobToken(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometricKyc->verify(
            consent: $this->consent(),
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            useEnrolledImage: true,
            userId: 'user-1',
        );

        self::assertSame(
            ['user_id' => 'user-1', 'product' => 'biometric_kyc', 'use_enrolled_image' => 'true'],
            $this->claims($mock->request(0)),
        );

        $jobParts = array_column(MultipartParser::parse($mock->request(1)), 'name');
        self::assertSame([], array_intersect($jobParts, ['use_enrolled_image', 'selfie_image', 'liveness_images']));
    }

    public function testGrantDoesNotReachLaterRequests(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            MockClient::acceptedResponse(),
            MockClient::tokenResponse(),
            new Response(200, [], self::STATUS_BODY),
        ]);

        $mock->client->biometricKyc->verify(
            consent: $this->consent(),
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            useEnrolledImage: true,
            userId: 'user-1',
        );
        $mock->client->verifications->retrieve('job_1');

        self::assertCount(4, $mock->history);
        self::assertSame([], $this->claims($mock->request(2)));
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
