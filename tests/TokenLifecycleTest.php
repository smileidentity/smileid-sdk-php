<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SmileIdentity\Client;
use SmileIdentity\Client\ApiRequest;
use SmileIdentity\Client\Config;
use SmileIdentity\Client\Transport;
use SmileIdentity\Consent;
use SmileIdentity\Errors\APIError;
use SmileIdentity\Errors\AuthenticationError;
use SmileIdentity\Errors\SmileIDError;
use SmileIdentity\Errors\UnexpectedResponseError;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;

/**
 * Token lifecycle: every authenticated request mints its own token; a job's
 * token is bound to its product, user and grant, any other call's to nothing;
 * a failed mint never sends the job; a 401 is not retried; no token on the
 * unauthenticated services calls; lowercase headers on /v3/token.
 */
final class TokenLifecycleTest extends TestCase
{
    private const FAKE_JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-bytes";
    private const STATUS_BODY = '{"status":"clear","job_id":"job_1","user_id":"user_1","message":"Job completed"}';

    private static function consent(): Consent
    {
        return Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy');
    }

    /**
     * @return array<string, mixed>
     */
    private static function userDetails(): array
    {
        return ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
    }

    /**
     * @return array<int, string>
     */
    private static function livenessImages(): array
    {
        return array_fill(0, 7, self::FAKE_JPEG);
    }

    private static function enhancedKyc(Client $client, ?string $userId = null): mixed
    {
        return $client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: self::userDetails(),
            consent: self::consent(),
            userId: $userId,
        );
    }

    private static function biometricKyc(Client $client, mixed $useEnrolledImage): mixed
    {
        return $client->biometricKyc->verify(
            consent: self::consent(),
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: self::userDetails(),
            useEnrolledImage: $useEnrolledImage,
            userId: 'user-1',
        );
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

    /**
     * @return array<string, array{0: string, 1: \Closure(Client): mixed}>
     */
    public static function jobOperations(): array
    {
        $jpeg = self::FAKE_JPEG;
        $liveness = self::livenessImages();
        $consent = self::consent();
        $details = self::userDetails();

        return [
            'enhanced_kyc' => ['enhanced_kyc', fn (Client $c) => self::enhancedKyc($c, 'user-1')],
            'document_verification' => ['document_verification', fn (Client $c) => $c->documents->verify(
                selfieImage: $jpeg,
                livenessImages: $liveness,
                document: $jpeg,
                consent: $consent,
                country: 'NG',
                userDetails: $details,
                userId: 'user-1',
            )],
            'enhanced_document_verification' => ['enhanced_document_verification', fn (Client $c) => $c->documents->verifyEnhanced(
                selfieImage: $jpeg,
                livenessImages: $liveness,
                document: $jpeg,
                consent: $consent,
                country: 'NG',
                userDetails: $details,
                idType: 'PASSPORT',
                userId: 'user-1',
            )],
            'residency_document_verification' => ['residency_document_verification', fn (Client $c) => $c->documents->verifyResidency(
                selfieImage: $jpeg,
                livenessImages: $liveness,
                document: $jpeg,
                visa: $jpeg,
                consent: $consent,
                country: 'NG',
                userDetails: $details,
                userId: 'user-1',
            )],
            'biometric_kyc' => ['biometric_kyc', fn (Client $c) => $c->biometricKyc->verify(
                consent: $consent,
                country: 'NG',
                idType: 'NIN',
                idNumber: '12345678901',
                userDetails: $details,
                selfieImage: $jpeg,
                livenessImages: $liveness,
                userId: 'user-1',
            )],
            'smart_selfie_registration' => ['smart_selfie_registration', fn (Client $c) => $c->biometric->enroll(
                selfieImage: $jpeg,
                livenessImages: $liveness,
                consent: $consent,
                userDetails: $details,
                userId: 'user-1',
            )],
            'smart_selfie_authentication' => ['smart_selfie_authentication', fn (Client $c) => $c->biometric->authenticate(
                userId: 'user-1',
                consent: $consent,
                userDetails: $details,
                selfieImage: $jpeg,
                livenessImages: $liveness,
            )],
            'smart_selfie_compare' => ['smart_selfie_compare', fn (Client $c) => $c->biometric->compare(
                selfieImage: $jpeg,
                comparisonImage: $jpeg,
                comparisonImageType: 'id_card',
                consent: $consent,
                userDetails: $details,
                userId: 'user-1',
            )],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: \Closure(Client): mixed}>
     */
    public static function unboundOperations(): array
    {
        return [
            'status' => ['status', fn (Client $c) => $c->verifications->retrieve('job_1')],
            'replay' => ['replay', fn (Client $c) => $c->verifications->replay('job_1')],
            'report_fraud' => ['report_fraud', fn (Client $c) => $c->users->flagFraud(
                'user-1',
                reason: 'MULE_ACCOUNT',
                reportedBy: 'risk@partner.example',
            )],
            'id_status' => ['id_status', fn (Client $c) => $c->services->idStatus('NG', 'NIN')],
        ];
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('jobOperations')]
    public function testJobTokenBindsItsUserAndProduct(string $product, \Closure $call): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $call($mock->client);

        self::assertSame(['user_id' => 'user-1', 'product' => $product], $this->claims($mock->request(0)));
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('unboundOperations')]
    public function testNonJobTokenBindsNothing(string $name, \Closure $call): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], '{"status":"ok"}')]);

        $call($mock->client);

        self::assertSame('/v3/token', $mock->request(0)->getUri()->getPath());
        self::assertSame('', (string) $mock->request(0)->getBody());
    }

    public function testJobWithoutAUserBindsOnlyTheProduct(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        self::enhancedKyc($mock->client);

        self::assertSame(['product' => 'enhanced_kyc'], $this->claims($mock->request(0)));
    }

    public function testEachJobSendsTheTokenMintedForIt(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(token: 'token-1'),
            MockClient::acceptedResponse(),
            MockClient::tokenResponse(token: 'token-2'),
            MockClient::acceptedResponse(),
        ]);

        foreach ([1, 2] as $_) {
            self::enhancedKyc($mock->client);
        }

        self::assertSame('token-1', $mock->request(1)->getHeaderLine('SmileID-Token'));
        self::assertSame('token-2', $mock->request(3)->getHeaderLine('SmileID-Token'));
    }

    /**
     * @return array<string, array{0: Response, 1: class-string<\Throwable>}>
     */
    public static function failedMints(): array
    {
        return [
            '401' => [new Response(401, [], '{"status":"Unauthorized","message":"bad key"}'), AuthenticationError::class],
            '503' => [new Response(503, [], '{"status":"error","message":"busy"}'), APIError::class],
            'empty token' => [new Response(200, [], '{"token":""}'), UnexpectedResponseError::class],
        ];
    }

    /**
     * @param class-string<\Throwable> $error
     */
    #[DataProvider('failedMints')]
    public function testFailedMintNeverSendsTheJob(Response $response, string $error): void
    {
        $mock = new MockClient([$response, $response, $response, MockClient::acceptedResponse()]);

        try {
            self::enhancedKyc($mock->client);
            self::fail('Expected ' . $error);
        } catch (SmileIDError $e) {
            self::assertInstanceOf($error, $e);
        }

        foreach ($mock->history as $entry) {
            self::assertSame('/v3/token', $entry['request']->getUri()->getPath());
        }
    }

    public function testMintUsesTheConfiguredTimeout(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()], ['timeout' => 7.0]);

        self::enhancedKyc($mock->client);

        self::assertEquals(7.0, $mock->history[0]['options']['timeout']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function badUserIds(): array
    {
        return ['empty' => [''], 'leading space' => [' user-1'], 'trailing space' => ['user-1 ']];
    }

    #[DataProvider('badUserIds')]
    public function testEmptyOrPaddedUserIdIsRejected(string $userId): void
    {
        $mock = new MockClient([]);

        try {
            self::enhancedKyc($mock->client, $userId);
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('user_id', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
    }

    #[DataProvider('badUserIds')]
    public function testEmptyOrPaddedBodyUserIdIsRejected(string $userId): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->biometric->authenticate(
                userId: $userId,
                consent: self::consent(),
                userDetails: self::userDetails(),
                selfieImage: self::FAKE_JPEG,
                livenessImages: self::livenessImages(),
            );
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('user_id', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
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
            consent: self::consent(),
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: self::userDetails(),
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
            MockClient::tokenResponse(token: 'token-1'),
            MockClient::acceptedResponse(),
            MockClient::tokenResponse(token: 'token-2'),
            new Response(200, [], self::STATUS_BODY),
        ]);

        self::biometricKyc($mock->client, true);
        $mock->client->verifications->retrieve('job_1');

        self::assertCount(4, $mock->history);
        self::assertSame('token-1', $mock->request(1)->getHeaderLine('SmileID-Token'));
        self::assertSame('token-2', $mock->request(3)->getHeaderLine('SmileID-Token'));
        self::assertSame([], $this->claims($mock->request(2)));
    }

    public function testTransportRefusesAGrantWithoutAUser(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([]));
        $stack->push(Middleware::history($history));
        $transport = new Transport(new Config('1234', 'test-api-key'), new GuzzleClient(['handler' => $stack]));
        $request = new ApiRequest(
            method: 'POST',
            path: '/v3/biometric_kyc',
            authenticated: true,
            idempotent: false,
        );

        try {
            $transport->withEnrolledImageGrant()->send($request);
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('user_id is required', $e->getMessage());
        }

        self::assertCount(0, $history);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function nonBoolGrants(): array
    {
        return ['string true' => ['true'], 'string false' => ['false'], 'int' => [1], 'null' => [null]];
    }

    #[DataProvider('nonBoolGrants')]
    public function testGrantAcceptsOnlyABool(mixed $value): void
    {
        $mock = new MockClient([]);

        try {
            self::biometricKyc($mock->client, $value);
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('use_enrolled_image must be a bool', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
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
