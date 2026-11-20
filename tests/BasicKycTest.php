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
use SmileIdentity\Version;

/** Golden requests and response parsing for Basic KYC. */
final class BasicKycTest extends TestCase
{
    /** @return array<string, mixed> */
    private function userDetails(): array
    {
        return ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
    }

    private function consent(): Consent
    {
        return Consent::granted(
            grantedAt: '2026-03-06T12:00:00.000Z',
            noticeLanguage: 'EN',
            noticePrivacyPolicyUrl: 'https://example.com/privacy',
        );
    }

    /** @return array<string, string> */
    private function claims(\Psr\Http\Message\RequestInterface $tokenRequest): array
    {
        $claims = [];
        foreach (MultipartParser::parse($tokenRequest) as $part) {
            $claims[(string) $part['name']] = $part['body'];
        }
        unset($claims['partner_id'], $claims['signature'], $claims['timestamp']);

        return $claims;
    }

    /**
     * The user_details and consent parts carry application/json and verbatim
     * snake_case keys.
     *
     * @param list<array{name: ?string, filename: ?string, contentType: ?string, body: string}> $parts
     */
    private function assertJsonParts(array $parts): void
    {
        $userDetails = MultipartParser::named($parts, 'user_details');
        self::assertCount(1, $userDetails);
        self::assertSame('application/json', $userDetails[0]['contentType']);
        self::assertSame('{"given_names":"John","last_name":"Doe","email":"john@example.com"}', $userDetails[0]['body']);

        $consent = MultipartParser::named($parts, 'consent');
        self::assertCount(1, $consent);
        self::assertSame('application/json', $consent[0]['contentType']);
        self::assertSame(
            '{"granted":true,"granted_at":"2026-03-06T12:00:00.000Z","notice_language":"EN","notice_privacy_policy_url":"https://example.com/privacy"}',
            $consent[0]['body'],
        );
    }

    public function testVerifyGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $accepted = $mock->client->basicKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            callbackUrl: 'https://example.com/callback',
            bankCode: '044',
            operator: 'mtn',
            partnerParams: ['order_id' => 'ORD-12345'],
            metadata: [['name' => 'a', 'value' => 'b']],
        );

        self::assertTrue($accepted->isAccepted);

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/basic_kyc', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));
        self::assertSame(Version::VERSION, $request->getHeaderLine('SmileID-Source-SDK-Version'));
        self::assertFalse($request->hasHeader('User-ID'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));
        self::assertFalse($request->hasHeader('SmileID-Idempotency-Key'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

        $parts = MultipartParser::parse($request);
        foreach (['country' => 'NG', 'id_type' => 'NIN', 'id_number' => '12345678901', 'bank_code' => '044', 'operator' => 'mtn', 'callback_url' => 'https://example.com/callback'] as $name => $value) {
            self::assertSame($value, MultipartParser::named($parts, $name)[0]['body'], $name);
        }
        $this->assertJsonParts($parts);
        self::assertSame('{"order_id":"ORD-12345"}', MultipartParser::named($parts, 'partner_params')[0]['body']);
        self::assertSame('[{"name":"a","value":"b"}]', MultipartParser::named($parts, 'metadata')[0]['body']);
        self::assertCount(0, MultipartParser::named($parts, 'user_id'));

        self::assertSame(['product' => 'basic_kyc'], $this->claims($mock->request(0)));
    }

    public function testVerifyUsesDefaultCallbackUrl(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), MockClient::acceptedResponse()],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->basicKyc->verify('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('https://app.example.com/cb', MultipartParser::named($parts, 'callback_url')[0]['body']);
    }

    public function testVerifySyncGoldenRequestAndResult(): void
    {
        $body = [
            'status' => 'attention',
            'message' => 'Verification completed: partial match.',
            'reason' => null,
            'created_at' => '2026-03-10T12:00:00.000Z',
            'completed_at' => '2026-03-10T12:00:04.000Z',
            'product' => 'basic_kyc',
            'partner_params' => ['order_id' => 'ORD-12345'],
            'match_results' => [
                'overall' => 'partial_match',
                'names' => 'partial_match',
                'first_name' => 'exact_match',
                'last_name' => 'transposed',
                'other_names' => 'not_provided',
                'date_of_birth' => 'no_match',
                'gender' => 'exact_match',
                'phone_number' => 'not_provided',
                'secondary_id_number' => 'not_provided',
            ],
            'user_provided_info' => ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'phone_number' => null, 'id_number' => '12345678901'],
            'consent' => ['granted' => true],
            'antifraud' => ['score' => 1],
            'device_signals' => ['ip' => '1.2.3.4'],
            'kyc_receipt' => 'https://portal.usesmileid.com/receipt',
        ];
        $mock = new MockClient(
            [MockClient::tokenResponse(), new Response(200, ['SmileID-Job-ID' => 'job_1'], (string) json_encode($body))],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $result = $mock->client->basicKyc->verifySync(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/basic_kyc/sync', (string) $request->getUri());
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));
        self::assertFalse($request->hasHeader('User-ID'));
        $parts = MultipartParser::parse($request);
        foreach (['country' => 'NG', 'id_type' => 'NIN', 'id_number' => '12345678901'] as $name => $value) {
            self::assertSame($value, MultipartParser::named($parts, $name)[0]['body'], $name);
        }
        $this->assertJsonParts($parts);
        // The client's default callback URL is never sent on the sync call.
        self::assertCount(0, MultipartParser::named($parts, 'callback_url'));
        self::assertCount(0, MultipartParser::named($parts, 'user_id'));
        self::assertSame(['product' => 'basic_kyc'], $this->claims($mock->request(0)));

        self::assertSame('attention', $result->status);
        self::assertSame('Verification completed: partial match.', $result->message);
        self::assertNull($result->reason);
        self::assertSame('2026-03-10T12:00:00.000Z', $result->createdAt);
        self::assertSame('2026-03-10T12:00:04.000Z', $result->completedAt);
        self::assertSame('basic_kyc', $result->product);
        self::assertSame(['order_id' => 'ORD-12345'], $result->partnerParams);
        self::assertSame($body['match_results'], $result->matchResults);
        self::assertNull($result->userProvidedInfo['phone_number']);
        self::assertSame(['granted' => true], $result->consent);
        self::assertSame(['score' => 1], $result->antifraud);
        self::assertSame(['ip' => '1.2.3.4'], $result->deviceSignals);
        self::assertSame('https://portal.usesmileid.com/receipt', $result->kycReceipt);
    }

    public function testVerifySyncSendsCallbackUrlOnlyWhenPassed(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), new Response(200, [], '{"status":"clear","product":"basic_kyc"}')],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->basicKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent(), callbackUrl: 'https://example.com/sync-cb');

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame(['https://example.com/sync-cb'], array_column(MultipartParser::named($parts, 'callback_url'), 'body'));
    }

    public function testVerifySyncParsesErroredResultWithNulls(): void
    {
        $body = ['status' => 'error', 'message' => 'Unable to verify ID.', 'reason' => 'no_comparable_fields', 'created_at' => '2026-03-10T12:00:00.000Z', 'product' => 'basic_kyc', 'match_results' => null];
        $mock = new MockClient([MockClient::tokenResponse(), new Response(200, [], (string) json_encode($body))]);

        $result = $mock->client->basicKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());

        self::assertSame('error', $result->status);
        self::assertSame('no_comparable_fields', $result->reason);
        self::assertNull($result->completedAt);
        self::assertNull($result->partnerParams);
        self::assertNull($result->matchResults);
        self::assertSame([], $result->userProvidedInfo);
        self::assertSame([], $result->consent);
        self::assertNull($result->antifraud);
        self::assertNull($result->deviceSignals);
        self::assertNull($result->kycReceipt);
    }

    public function testVerifySyncIsNeverRetried(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(408, ['SmileID-Job-ID' => 'job_1'], '{"status":"Request Timeout","message":"The ID authority did not respond in time."}'),
        ]);

        try {
            $mock->client->basicKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());
            self::fail('Expected an error.');
        } catch (SmileIDError $e) {
            self::assertSame(408, $e->statusCode);
        }
        self::assertCount(2, $mock->history);
    }

    public function testVerifySyncRaisesOnAuthorityUnavailableAndInternalFailure(): void
    {
        foreach ([[424, SmileIDError::class], [500, APIError::class]] as [$status, $class]) {
            $mock = new MockClient([MockClient::tokenResponse(), new Response($status, [], '{"status":"x","message":"failed"}')]);
            try {
                $mock->client->basicKyc->verifySync('NG', 'NIN', '12345678901', $this->userDetails(), $this->consent());
                self::fail('Expected an error.');
            } catch (SmileIDError $e) {
                self::assertInstanceOf($class, $e);
                self::assertSame($status, $e->statusCode);
            }
            self::assertCount(2, $mock->history);
        }
    }

    public function testValidationRejectsBeforeAnyRequest(): void
    {
        $mock = new MockClient([]);

        foreach ([
            'missing last name' => fn () => $mock->client->basicKyc->verify('NG', 'NIN', '1', ['given_names' => 'John'], $this->consent()),
            'missing given name' => fn () => $mock->client->basicKyc->verify('NG', 'NIN', '1', ['last_name' => 'Doe', 'email' => 'john@example.com'], $this->consent()),
            'missing email and phone' => fn () => $mock->client->basicKyc->verify('NG', 'NIN', '1', ['given_names' => 'John', 'last_name' => 'Doe'], $this->consent()),
            'http callback on verify' => fn () => $mock->client->basicKyc->verify('NG', 'NIN', '1', $this->userDetails(), $this->consent(), callbackUrl: 'http://example.com/cb'),
            'http callback on verifySync' => fn () => $mock->client->basicKyc->verifySync('NG', 'NIN', '1', $this->userDetails(), $this->consent(), callbackUrl: 'http://example.com/cb'),
            'missing given name on verifySync' => fn () => $mock->client->basicKyc->verifySync('NG', 'NIN', '1', ['given_names' => ' ', 'last_name' => 'Doe', 'phone_number' => '+2348000000000'], $this->consent()),
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
