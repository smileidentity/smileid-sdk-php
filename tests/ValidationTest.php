<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Consent;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;

/**
 * Client-side validation: user_details email-or-phone rule, report_fraud
 * conditional reason/notes rules, authentication requires images unless
 * use_enrolled_image. All raise before any HTTP request is sent.
 */
final class ValidationTest extends TestCase
{
    private function consent(): Consent
    {
        return Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy');
    }

    public function testUserDetailsRequiresEmailOrPhone(): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->enhancedKyc->verify(
                country: 'NG',
                idType: 'NIN',
                idNumber: '12345678901',
                userDetails: ['given_names' => 'John', 'last_name' => 'Doe'],
                consent: $this->consent(),
            );
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('email or phone_number', $e->getMessage());
            self::assertNull($e->statusCode);
        }

        self::assertCount(0, $mock->history, 'nothing may be sent when validation fails');
    }

    public function testUserDetailsRequiresNames(): void
    {
        $mock = new MockClient([]);

        $this->expectException(ValidationError::class);
        $mock->client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            userDetails: ['last_name' => 'Doe', 'email' => 'john@example.com'],
            consent: $this->consent(),
        );
    }

    public function testReportFraudRequiresReasonWhenFraud(): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->users->reportFraud('user-123', isFraud: true, reportedBy: 'john@example.com');
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('reason is required', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
    }

    public function testReportFraudRequiresNotesWhenNotFraud(): void
    {
        $mock = new MockClient([]);

        $this->expectException(ValidationError::class);
        $mock->client->users->reportFraud('user-123', isFraud: false, reportedBy: 'john@example.com');
    }

    public function testReportFraudRequiresNotesWhenReasonIsOther(): void
    {
        $mock = new MockClient([]);

        $this->expectException(ValidationError::class);
        $mock->client->users->reportFraud('user-123', isFraud: true, reportedBy: 'john@example.com', reason: 'OTHER');
    }

    public function testReportFraudRejectsUnknownReason(): void
    {
        $mock = new MockClient([]);

        $this->expectException(ValidationError::class);
        $mock->client->users->reportFraud('user-123', isFraud: true, reportedBy: 'john@example.com', reason: 'MADE_UP');
    }

    public function testReportFraudRejectsLongNotes(): void
    {
        $mock = new MockClient([]);

        $this->expectException(ValidationError::class);
        $mock->client->users->reportFraud(
            'user-123',
            isFraud: false,
            reportedBy: 'john@example.com',
            notes: str_repeat('x', 501),
        );
    }

    public function testFlagFraudSetsIsFraudTrue(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new \GuzzleHttp\Psr7\Response(202, [], '{"status":"accepted","message":"Fraud report accepted","user_id":"user-123"}'),
        ]);

        $mock->client->users->flagFraud('user-123', reason: 'MULE_ACCOUNT', reportedBy: 'john@example.com');

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('true', MultipartParser::named($parts, 'is_fraud')[0]['body']);
        self::assertSame('MULE_ACCOUNT', MultipartParser::named($parts, 'reason')[0]['body']);
    }

    public function testClearFraudSetsIsFraudFalseAndRequiresNotes(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new \GuzzleHttp\Psr7\Response(202, [], '{"status":"accepted","message":"Fraud report accepted","user_id":"user-123"}'),
        ]);

        $mock->client->users->clearFraud('user-123', notes: 'False positive', reportedBy: 'john@example.com');

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('false', MultipartParser::named($parts, 'is_fraud')[0]['body']);
        self::assertSame('False positive', MultipartParser::named($parts, 'notes')[0]['body']);
        self::assertCount(0, MultipartParser::named($parts, 'reason'));
    }

    public function testAuthenticateRequiresImagesUnlessEnrolledImage(): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->biometric->authenticate(
                userId: 'user-123',
                consent: $this->consent(),
                userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            );
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('use_enrolled_image', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
    }

    public function testConfigRejectsBadPartnerId(): void
    {
        $this->expectException(ValidationError::class);
        new \SmileIdentity\Client(partnerId: '0123', apiKey: 'key');
    }

    public function testConfigRejectsUnknownEnvironment(): void
    {
        $this->expectException(ValidationError::class);
        new \SmileIdentity\Client(partnerId: '1234', apiKey: 'key', environment: 'staging');
    }

    public function testConsentBuilderSetsGrantedTrueAndValidatesLanguage(): void
    {
        $consent = Consent::granted(
            grantedAt: new \DateTimeImmutable('2026-03-06 12:00:00.000', new \DateTimeZone('UTC')),
            noticeLanguage: 'EN',
            noticePrivacyPolicyUrl: 'https://example.com/privacy',
        );

        self::assertTrue($consent->granted);
        self::assertSame('2026-03-06T12:00:00.000Z', $consent->grantedAt);

        $this->expectException(ValidationError::class);
        Consent::granted('2026-03-06T12:00:00.000Z', 'english', 'https://example.com/privacy');
    }
}
