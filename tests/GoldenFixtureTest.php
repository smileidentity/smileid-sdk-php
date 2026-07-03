<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Helpers\BinaryInput;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;
use SmileIdentity\Version;

/**
 * Golden-fixture serialization per spec §6: exact multipart wire shape, header
 * routing per operation, JSON parts with application/json, repeated
 * liveness_images parts, and replay as JSON (not multipart).
 */
final class GoldenFixtureTest extends TestCase
{
    private const FAKE_JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-bytes";

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

    /** @return list<string> */
    private function livenessImages(int $count = 7): array
    {
        return array_fill(0, $count, self::FAKE_JPEG);
    }

    public function testEnhancedKycGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $accepted = $mock->client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        );

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://testapi.smileidentity.com/v3/enhanced_kyc', (string) $request->getUri());

        // Header routing: token + telemetry + User-ID; NO Partner-ID header.
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));
        self::assertSame(Version::VERSION, $request->getHeaderLine('SmileID-Source-SDK-Version'));
        self::assertStringStartsWith('smileid-sdk-php/' . Version::VERSION . ' (php/', $request->getHeaderLine('User-Agent'));
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $request->getHeaderLine('User-ID'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);

        $country = MultipartParser::named($parts, 'country');
        self::assertCount(1, $country);
        self::assertSame('NG', $country[0]['body']);
        self::assertNull($country[0]['contentType']);

        self::assertSame('NIN', MultipartParser::named($parts, 'id_type')[0]['body']);
        self::assertSame('12345678901', MultipartParser::named($parts, 'id_number')[0]['body']);

        // JSON object parts carry application/json and verbatim snake_case keys.
        $userDetails = MultipartParser::named($parts, 'user_details')[0];
        self::assertSame('application/json', $userDetails['contentType']);
        self::assertSame(
            '{"given_names":"John","last_name":"Doe","email":"john@example.com"}',
            $userDetails['body'],
        );

        $consent = MultipartParser::named($parts, 'consent')[0];
        self::assertSame('application/json', $consent['contentType']);
        self::assertSame(
            '{"granted":true,"granted_at":"2026-03-06T12:00:00.000Z","notice_language":"EN","notice_privacy_policy_url":"https://example.com/privacy"}',
            $consent['body'],
        );

        // No user_id body part on this op (it rides the User-ID header).
        self::assertCount(0, MultipartParser::named($parts, 'user_id'));

        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $accepted->jobId);
        self::assertTrue($accepted->isAccepted);
    }

    public function testDocumentVerificationGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $mock->client->documents->verify(
            selfieImage: BinaryInput::fromString(self::FAKE_JPEG, 'selfie.jpg'),
            livenessImages: $this->livenessImages(),
            document: BinaryInput::fromString(self::FAKE_JPEG, 'doc.jpg'),
            consent: $this->consent(),
            country: 'NG',
            userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'phone_number' => '+2348012345678'],
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/document_verification', (string) $request->getUri());

        // Partner-ID header IS required on docv.
        self::assertSame('1234', $request->getHeaderLine('SmileID-Partner-ID'));
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $request->getHeaderLine('User-ID'));

        $parts = MultipartParser::parse($request);

        // Repeated liveness_images parts — one per image, all the same name, never CSV or indexed.
        $liveness = MultipartParser::named($parts, 'liveness_images');
        self::assertCount(7, $liveness);
        foreach ($liveness as $part) {
            self::assertSame('image/jpeg', $part['contentType']);
            self::assertSame(self::FAKE_JPEG, $part['body']);
            self::assertNotNull($part['filename']);
        }
        foreach ($parts as $part) {
            self::assertNotNull($part['name']);
            self::assertStringNotContainsString('[', (string) $part['name'], 'indexed field names are forbidden');
        }

        $selfie = MultipartParser::named($parts, 'selfie_image')[0];
        self::assertSame('image/jpeg', $selfie['contentType']);
        self::assertSame('selfie.jpg', $selfie['filename']);

        $document = MultipartParser::named($parts, 'document')[0];
        self::assertSame('image/jpeg', $document['contentType']);
        self::assertSame('doc.jpg', $document['filename']);

        self::assertSame(
            '{"given_names":"John","last_name":"Doe","phone_number":"+2348012345678"}',
            MultipartParser::named($parts, 'user_details')[0]['body'],
        );
    }

    public function testEnhancedDocumentVerificationGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $mock->client->documents->verifyEnhanced(
            selfieImage: self::FAKE_JPEG,
            livenessImages: $this->livenessImages(6),
            document: self::FAKE_JPEG,
            consent: $this->consent(),
            country: 'NG',
            idType: 'PASSPORT',
            userDetails: $this->userDetails(),
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/enhanced_document_verification', (string) $request->getUri());
        self::assertSame('1234', $request->getHeaderLine('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        self::assertSame('PASSPORT', MultipartParser::named($parts, 'id_type')[0]['body']);
        self::assertCount(6, MultipartParser::named($parts, 'liveness_images'));
    }

    public function testBiometricKycGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $mock->client->biometricKyc->verify(
            selfieImage: self::FAKE_JPEG,
            livenessImages: $this->livenessImages(8),
            consent: $this->consent(),
            country: 'NG',
            idType: 'BVN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            sandboxResult: 0,
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/biometric_kyc', (string) $request->getUri());
        self::assertSame('1234', $request->getHeaderLine('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        self::assertCount(8, MultipartParser::named($parts, 'liveness_images'));
        self::assertSame('BVN', MultipartParser::named($parts, 'id_type')[0]['body']);
        self::assertSame('0', MultipartParser::named($parts, 'sandbox_result')[0]['body']);
    }

    public function testRegistrationGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->enroll(
            selfieImage: self::FAKE_JPEG,
            livenessImages: $this->livenessImages(),
            consent: $this->consent(),
            userDetails: $this->userDetails(),
            allowNewEnroll: true,
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/registration', (string) $request->getUri());
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $request->getHeaderLine('User-ID'));

        $parts = MultipartParser::parse($request);
        // Booleans serialize as "true"/"false" text parts.
        self::assertSame('true', MultipartParser::named($parts, 'allow_new_enroll')[0]['body']);
    }

    public function testAuthenticationSendsUserIdInBodyNotHeader(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->authenticate(
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
            consent: $this->consent(),
            userDetails: $this->userDetails(),
            selfieImage: self::FAKE_JPEG,
            livenessImages: $this->livenessImages(),
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/authentication', (string) $request->getUri());
        self::assertFalse($request->hasHeader('User-ID'));
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        $userId = MultipartParser::named($parts, 'user_id');
        self::assertCount(1, $userId);
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', $userId[0]['body']);
    }

    public function testAuthenticationWithEnrolledImageSkipsImages(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->authenticate(
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
            consent: $this->consent(),
            userDetails: $this->userDetails(),
            useEnrolledImage: true,
        );

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('true', MultipartParser::named($parts, 'use_enrolled_image')[0]['body']);
        self::assertCount(0, MultipartParser::named($parts, 'selfie_image'));
        self::assertCount(0, MultipartParser::named($parts, 'liveness_images'));
    }

    public function testCompareGoldenRequest(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->compare(
            selfieImage: BinaryInput::fromString(self::FAKE_JPEG, 'selfie.jpg'),
            comparisonImage: BinaryInput::fromString(self::FAKE_JPEG, 'comparison.jpg'),
            comparisonImageType: 'ID_PHOTO',
            consent: $this->consent(),
            userDetails: $this->userDetails(),
            userId: 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        );

        $request = $mock->request(1);
        self::assertSame('https://testapi.smileidentity.com/v3/compare', (string) $request->getUri());
        self::assertFalse($request->hasHeader('User-ID'));

        $parts = MultipartParser::parse($request);
        self::assertSame('ID_PHOTO', MultipartParser::named($parts, 'comparison_image_type')[0]['body']);
        self::assertSame('image/jpeg', MultipartParser::named($parts, 'comparison_image')[0]['contentType']);
        // user_id is an optional BODY part on compare.
        self::assertSame('user_01h8x9y2z3a4b5c6d7e8f9g0h1', MultipartParser::named($parts, 'user_id')[0]['body']);
    }

    public function testStatusRetrieveGoldenRequest(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode([
                'status' => 'complete',
                'job_id' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
                'user_id' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
                'message' => 'Verification completed with state: clear',
            ])),
        ]);

        $status = $mock->client->verifications->retrieve('job_01h8x9y2z3a4b5c6d7e8f9g0h1');

        $request = $mock->request(1);
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://testapi.smileidentity.com/v3/status/job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            (string) $request->getUri(),
        );
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));

        self::assertTrue($status->isComplete);
        self::assertSame('Verification completed with state: clear', $status->message);
    }

    public function testReplayIsJsonNotMultipart(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(202, [], (string) json_encode([
                'status' => 'accepted',
                'job_id' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
                'user_id' => 'test-user',
                'message' => 'Callback replay queued successfully.',
            ])),
        ]);

        $response = $mock->client->verifications->replay(
            'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            callbackUrl: 'https://app.example.com/cb',
        );

        $request = $mock->request(1);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://testapi.smileidentity.com/v3/replay/job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            (string) $request->getUri(),
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"callback_url":"https://app.example.com/cb"}', (string) $request->getBody());

        self::assertTrue($response->isAccepted);
        self::assertSame('job_01h8x9y2z3a4b5c6d7e8f9g0h1', $response->jobId);
    }

    public function testReportFraudGoldenRequest(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(202, [], (string) json_encode([
                'status' => 'accepted',
                'message' => 'Fraud report accepted',
                'user_id' => 'user-123',
            ])),
        ]);

        $response = $mock->client->users->reportFraud(
            'user-123',
            isFraud: true,
            reportedBy: 'john@example.com',
            reason: 'ACCOUNT_TAKEOVER',
            notes: 'Suspicious activity',
        );

        $request = $mock->request(1);
        self::assertSame(
            'https://testapi.smileidentity.com/v3/users/user-123/report_fraud',
            (string) $request->getUri(),
        );
        self::assertFalse($request->hasHeader('SmileID-Partner-ID'));

        $parts = MultipartParser::parse($request);
        self::assertSame('true', MultipartParser::named($parts, 'is_fraud')[0]['body']);
        self::assertSame('john@example.com', MultipartParser::named($parts, 'reported_by')[0]['body']);
        self::assertSame('ACCOUNT_TAKEOVER', MultipartParser::named($parts, 'reason')[0]['body']);
        self::assertSame('Suspicious activity', MultipartParser::named($parts, 'notes')[0]['body']);

        self::assertTrue($response->isAccepted);
        self::assertSame('user-123', $response->userId);
    }

    public function testBankCodesIsUnauthenticated(): void
    {
        $mock = new MockClient([
            new Response(200, [], (string) json_encode([
                'bank_codes' => [['code' => '044', 'country' => 'NG', 'name' => 'Access Bank']],
            ])),
        ]);

        $response = $mock->client->services->bankCodes(country: 'NG');

        // No token fetch happened — the only request is the GET itself.
        self::assertCount(1, $mock->history);
        $request = $mock->request(0);
        self::assertSame('https://testapi.smileidentity.com/v3/services/bank_codes?country=NG', (string) $request->getUri());
        self::assertFalse($request->hasHeader('SmileID-Token'));
        self::assertSame('php', $request->getHeaderLine('SmileID-Source-SDK'));

        self::assertSame('044', $response->bankCodes[0]->code);
        self::assertSame('Access Bank', $response->bankCodes[0]->name);
    }

    public function testSupportedIdTypesIsUnauthenticated(): void
    {
        $mock = new MockClient([
            new Response(200, [], (string) json_encode([
                'id_types' => [[
                    'country' => 'NG',
                    'label' => 'Bank Verification Number',
                    'regex' => '^\\d{11}$',
                    'required_fields' => ['first_name', 'last_name', 'dob'],
                    'type' => 'BVN',
                ]],
            ])),
        ]);

        $response = $mock->client->services->supportedIdTypes(country: 'NG');

        self::assertCount(1, $mock->history);
        $request = $mock->request(0);
        self::assertSame('https://testapi.smileidentity.com/v3/services/supported_id_types?country=NG', (string) $request->getUri());
        self::assertFalse($request->hasHeader('SmileID-Token'));

        self::assertSame('BVN', $response->idTypes[0]->type);
        self::assertSame(['first_name', 'last_name', 'dob'], $response->idTypes[0]->requiredFields);
    }

    public function testSupportedDocumentsIsUnauthenticated(): void
    {
        $mock = new MockClient([
            new Response(200, [], (string) json_encode([
                'valid_documents' => [[
                    'country' => ['code' => 'NG', 'name' => 'Nigeria', 'continent' => 'AFRICA'],
                    'id_types' => [[
                        'code' => 'DRIVERS_LICENSE',
                        'name' => "Driver's License",
                        'example' => ['AAA00000AA00'],
                        'has_back' => true,
                    ]],
                ]],
            ])),
        ]);

        $response = $mock->client->services->supportedDocuments(
            continent: 'AFRICA',
            countryCode: 'NG',
            locale: 'en-GB',
        );

        self::assertCount(1, $mock->history);
        $request = $mock->request(0);
        self::assertSame(
            'https://testapi.smileidentity.com/v3/services/supported_documents?continent=AFRICA&country_code=NG&locale=en-GB',
            (string) $request->getUri(),
        );
        self::assertFalse($request->hasHeader('SmileID-Token'));

        self::assertSame('NG', $response->validDocuments[0]->country?->code);
        self::assertTrue($response->validDocuments[0]->idTypes[0]->hasBack);
    }

    public function testIdStatusRequiresToken(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], (string) json_encode([
                'last_checked' => '2026-04-14T12:30:00.000Z',
                'last_check_status' => 'success',
                'last_hour_success_rate' => '95%',
                'last_known_status' => 'online',
                'last_check_success_rate' => '90%',
            ])),
        ]);

        $response = $mock->client->services->idStatus(country: 'NG', idType: 'NIN');

        $request = $mock->request(1);
        self::assertSame(
            'https://testapi.smileidentity.com/v3/services/id_status?country=NG&id_type=NIN',
            (string) $request->getUri(),
        );
        self::assertNotSame('', $request->getHeaderLine('SmileID-Token'));

        self::assertSame('success', $response->lastCheckStatus);
        self::assertSame('online', $response->lastKnownStatus);
    }

    public function testPartnerParamsAndMetadataAreJsonParts(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
            partnerParams: ['order_ref' => 'ref-1'],
            metadata: [['name' => 'channel', 'value' => 'web']],
        );

        $parts = MultipartParser::parse($mock->request(1));

        $partnerParams = MultipartParser::named($parts, 'partner_params')[0];
        self::assertSame('application/json', $partnerParams['contentType']);
        self::assertSame('{"order_ref":"ref-1"}', $partnerParams['body']);

        $metadata = MultipartParser::named($parts, 'metadata')[0];
        self::assertSame('application/json', $metadata['contentType']);
        self::assertSame('[{"name":"channel","value":"web"}]', $metadata['body']);
    }

    public function testDefaultCallbackUrlIsUsedWhenCallOmitsIt(): void
    {
        $mock = new MockClient(
            [MockClient::tokenResponse(), MockClient::acceptedResponse()],
            ['defaultCallbackUrl' => 'https://app.example.com/cb'],
        );

        $mock->client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: $this->userDetails(),
            consent: $this->consent(),
        );

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('https://app.example.com/cb', MultipartParser::named($parts, 'callback_url')[0]['body']);
    }

    public function testProductionEnvironmentAndBaseUrlOverride(): void
    {
        $production = new MockClient(
            [new Response(200, [], '{"bank_codes":[]}')],
            ['environment' => 'production'],
        );
        $production->client->services->bankCodes();
        self::assertStringStartsWith('https://api.smileidentity.com/', (string) $production->request(0)->getUri());

        $override = new MockClient(
            [new Response(200, [], '{"bank_codes":[]}')],
            ['baseUrl' => 'https://api.smileidentity.com/'],
        );
        $override->client->services->bankCodes();
        self::assertSame('https://api.smileidentity.com/v3/services/bank_codes', (string) $override->request(0)->getUri());
    }

    public function testPngDocumentGetsPngContentType(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $mock->client->documents->verify(
            selfieImage: BinaryInput::fromString(self::FAKE_JPEG, 'selfie.jpg'),
            livenessImages: $this->livenessImages(),
            document: BinaryInput::fromString("\x89PNG\r\n\x1a\nfake-png", 'doc.png'),
            documentBack: BinaryInput::fromString("\x89PNG\r\n\x1a\nfake-png-back"),
            consent: $this->consent(),
            country: 'NG',
            userDetails: $this->userDetails(),
        );

        $parts = MultipartParser::parse($mock->request(1));

        // Filename extension signals PNG; raw PNG bytes are sniffed too.
        self::assertSame('image/png', MultipartParser::named($parts, 'document')[0]['contentType']);
        self::assertSame('image/png', MultipartParser::named($parts, 'document_back')[0]['contentType']);
        // Selfie and liveness stay JPEG-only per the spec encoding block.
        self::assertSame('image/jpeg', MultipartParser::named($parts, 'selfie_image')[0]['contentType']);
    }
}
