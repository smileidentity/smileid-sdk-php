<?php

declare(strict_types=1);

namespace SmileIdentity\Tests\E2E;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Client;
use SmileIdentity\Consent;

/**
 * End-to-end test: submits an Enhanced KYC job and polls it to completion.
 * Requires SMILE_PARTNER_ID and SMILE_API_KEY in the environment; skips
 * cleanly when they are unset. Runs against sandbox unless SMILE_BASE_URL is
 * set, which overrides the base URL. Credential values are never printed.
 *
 * @group e2e
 */
final class EnhancedKycE2ETest extends TestCase
{
    public function testSandboxEnhancedKycCompletes(): void
    {
        $partnerId = getenv('SMILE_PARTNER_ID');
        $apiKey = getenv('SMILE_API_KEY');

        if ($partnerId === false || $partnerId === '' || $apiKey === false || $apiKey === '') {
            self::markTestSkipped('SMILE_PARTNER_ID and SMILE_API_KEY are not set; skipping sandbox E2E test.');
        }

        $baseUrl = getenv('SMILE_BASE_URL');

        $client = new Client(
            partnerId: $partnerId,
            apiKey: $apiKey,
            environment: 'sandbox',
            baseUrl: $baseUrl === false || $baseUrl === '' ? null : $baseUrl,
        );

        $accepted = $client->enhancedKyc->verify(
            country: 'NG',
            idType: 'NIN',
            idNumber: '12345678901',
            // Non-production environments only accept recognised test
            // identities, matched on given_names + last_name + email. An
            // unrecognised identity resolves to block.
            userDetails: [
                'given_names' => 'Amina Fatou',
                'last_name' => 'Clearwater',
                'email' => 'amina.clearwater@example.com',
            ],
            consent: Consent::granted(
                grantedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                noticeLanguage: 'EN',
                noticePrivacyPolicyUrl: 'https://example.com/privacy',
            ),
        );

        self::assertTrue($accepted->isAccepted);
        self::assertNotNull($accepted->jobId);

        $status = $client->verifications->waitUntilComplete(
            (string) $accepted->jobId,
            interval: 2.0,
            timeout: 120.0,
        );

        self::assertTrue($status->isComplete);
        self::assertSame('clear', $status->status);
    }
}
