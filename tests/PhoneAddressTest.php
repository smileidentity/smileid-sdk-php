<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;
use SmileIdentity\Version;

/** Golden requests, responses and validation for phone->verify and address->verify. */
final class PhoneAddressTest extends TestCase
{
    private const USER_DETAILS_JSON = '{"given_names":"Amina","last_name":"Clearwater","email":"amina@example.com"}';
    private const CONSENT_JSON = '{"granted":true,"granted_at":"2026-03-06T12:00:00.000Z","notice_language":"EN","notice_privacy_policy_url":"https://example.com/privacy"}';

    /** @return array<string, mixed> */
    private function userDetails(): array
    {
        return ['given_names' => 'Amina', 'last_name' => 'Clearwater', 'email' => 'amina@example.com'];
    }

    private function consent(): Consent
    {
        return Consent::granted(
            grantedAt: '2026-03-06T12:00:00.000Z',
            noticeLanguage: 'EN',
            noticePrivacyPolicyUrl: 'https://example.com/privacy',
        );
    }

    /** @return array<string, string> the claims the token request carries */
    private function claims(\Psr\Http\Message\RequestInterface $tokenRequest): array
    {
        $claims = [];
        foreach (MultipartParser::parse($tokenRequest) as $part) {
            $claims[(string) $part['name']] = $part['body'];
        }

        return $claims;
    }

    public function testPhoneVerifyGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $accepted = $mock->client->phone->verify(
            country: 'GH',
            phoneNumber: '+233241234567',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            callbackUrl: 'https://example.com/cb',
            operator: 'MTN',
            idNumber: 'GHA-1',
            userId: 'user_1',
            partnerParams: ['order_id' => 'ORD-1'],
            metadata: [['name' => 'a', 'value' => 'b']],
        );

        // The token carries the phone_number_verification product and the body's user id.
        self::assertSame('https://testapi.smileidentity.com/v3/token', (string) $mock->request(0)->getUri());
        $claims = $this->claims($mock->request(0));
        self::assertSame('phone_number_verification', $claims['product']);
        self::assertSame('user_1', $claims['user_id']);

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/phone', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));
        self::assertSame(Version::VERSION, $request->getHeaderLine('SmileID-Source-SDK-Version'));
        self::assertFalse($request->hasHeader('User-ID'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

        $parts = MultipartParser::parse($request);
        self::assertSame(
            ['country' => 'GH', 'phone_number' => '+233241234567', 'operator' => 'MTN', 'id_number' => 'GHA-1', 'user_id' => 'user_1', 'callback_url' => 'https://example.com/cb'],
            array_combine(
                ['country', 'phone_number', 'operator', 'id_number', 'user_id', 'callback_url'],
                array_map(fn (string $n): string => MultipartParser::named($parts, $n)[0]['body'], ['country', 'phone_number', 'operator', 'id_number', 'user_id', 'callback_url']),
            ),
        );
        $details = MultipartParser::named($parts, 'user_details')[0];
        self::assertSame('application/json', $details['contentType']);
        self::assertSame(self::USER_DETAILS_JSON, $details['body']);
        $consent = MultipartParser::named($parts, 'consent')[0];
        self::assertSame('application/json', $consent['contentType']);
        self::assertSame(self::CONSENT_JSON, $consent['body']);
        self::assertSame('{"order_id":"ORD-1"}', MultipartParser::named($parts, 'partner_params')[0]['body']);
        self::assertSame('[{"name":"a","value":"b"}]', MultipartParser::named($parts, 'metadata')[0]['body']);

        self::assertTrue($accepted->isAccepted);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $accepted->jobId);
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $accepted->userId);
    }

    public function testPhoneVerifyMinimalRequestUsesDefaultCallbackAndNoUserClaim(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->phone->verify('NG', '+2348012345678', $this->userDetails(), $this->consent());

        self::assertSame(['product' => 'phone_number_verification'], array_diff_key($this->claims($mock->request(0)), ['partner_id' => 1, 'signature' => 1, 'timestamp' => 1]));
        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('https://app.example.com/cb', MultipartParser::named($parts, 'callback_url')[0]['body']);
        foreach (['operator', 'id_number', 'user_id', 'partner_params', 'metadata'] as $absent) {
            self::assertCount(0, MultipartParser::named($parts, $absent), $absent);
        }
    }

    public function testAddressVerifyGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $accepted = $mock->client->address->verify(
            address: '12 Test Road, Testville',
            country: 'NG',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            callbackUrl: 'https://example.com/cb',
            idNumber: '9001015800085',
            utilityNumber: '0123456789',
            utilityProvider: 'IKEDC',
            utilityType: 'PrePaid',
            userId: 'user_addr_001',
            partnerParams: ['order_id' => 'ORD-1'],
            metadata: [['name' => 'a', 'value' => 'b']],
        );

        $claims = $this->claims($mock->request(0));
        self::assertSame('address_verification', $claims['product']);
        self::assertSame('user_addr_001', $claims['user_id']);

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/address', (string) $request->getUri());
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertFalse($request->hasHeader('User-ID'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        $expected = [
            'address' => '12 Test Road, Testville',
            'country' => 'NG',
            'id_number' => '9001015800085',
            'utility_number' => '0123456789',
            'utility_provider' => 'IKEDC',
            'utility_type' => 'PrePaid',
            'user_id' => 'user_addr_001',
            'callback_url' => 'https://example.com/cb',
        ];
        foreach ($expected as $name => $value) {
            self::assertSame($value, MultipartParser::named($parts, $name)[0]['body'], $name);
        }
        self::assertSame(self::USER_DETAILS_JSON, MultipartParser::named($parts, 'user_details')[0]['body']);
        self::assertSame('application/json', MultipartParser::named($parts, 'consent')[0]['contentType']);
        self::assertSame(self::CONSENT_JSON, MultipartParser::named($parts, 'consent')[0]['body']);
        self::assertSame('{"order_id":"ORD-1"}', MultipartParser::named($parts, 'partner_params')[0]['body']);
        self::assertSame('[{"name":"a","value":"b"}]', MultipartParser::named($parts, 'metadata')[0]['body']);

        self::assertTrue($accepted->isAccepted);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $accepted->jobId);
        self::assertNull($accepted->createdAt);
    }

    public function testAddressVerifyMinimalSouthAfricanRequest(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->address->verify('1 Long St', 'ZA', $this->userDetails(), $this->consent(), idNumber: '9001015800085');

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('https://app.example.com/cb', MultipartParser::named($parts, 'callback_url')[0]['body']);
        foreach (['utility_number', 'utility_provider', 'utility_type', 'user_id', 'partner_params', 'metadata'] as $absent) {
            self::assertCount(0, MultipartParser::named($parts, $absent), $absent);
        }
    }

    public function testValidationRejectsBeforeAnyRequest(): void
    {
        $mock = new MockClient([]);
        $phone = $mock->client->phone;
        $address = $mock->client->address;
        $u = $this->userDetails();
        $c = $this->consent();

        foreach ([
            'bad phone' => fn () => $phone->verify('NG', '08012345678', $u, $c),
            'KE without id' => fn () => $phone->verify('KE', '+254712345678', $u, $c),
            'KE blank id' => fn () => $phone->verify('KE', '+254712345678', $u, $c, idNumber: ' '),
            'GH without operator' => fn () => $phone->verify('GH', '+233241234567', $u, $c),
            'phone user details' => fn () => $phone->verify('NG', '+2348012345678', ['given_names' => 'A', 'last_name' => 'B'], $c),
            'phone http callback' => fn () => $phone->verify('NG', '+2348012345678', $u, $c, callbackUrl: 'http://example.com/cb'),
            'blank address' => fn () => $address->verify(' ', 'NG', $u, $c, utilityNumber: '1'),
            'long address' => fn () => $address->verify(str_repeat('a', 513), 'NG', $u, $c, utilityNumber: '1'),
            'bad country' => fn () => $address->verify('1 St', 'GH', $u, $c, utilityNumber: '1'),
            'no identifier' => fn () => $address->verify('1 St', 'NG', $u, $c),
            'blank identifiers' => fn () => $address->verify('1 St', 'NG', $u, $c, idNumber: ' ', utilityNumber: ' '),
            'bad utility type' => fn () => $address->verify('1 St', 'NG', $u, $c, utilityNumber: '1', utilityType: 'Other'),
            'address user details' => fn () => $address->verify('1 St', 'NG', ['given_names' => 'A', 'last_name' => 'B'], $c, utilityNumber: '1'),
            'address http callback' => fn () => $address->verify('1 St', 'NG', $u, $c, callbackUrl: 'http://example.com/cb', utilityNumber: '1'),
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
