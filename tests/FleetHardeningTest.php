<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmileIdentity\Client;
use SmileIdentity\Consent;
use SmileIdentity\Errors\UnexpectedResponseError;
use SmileIdentity\Errors\ValidationError;
use SmileIdentity\Helpers\BinaryInput;
use SmileIdentity\Tests\Support\MockClient;
use SmileIdentity\Tests\Support\MultipartParser;

/**
 * Fleet hardening standards: https-only base and callback URLs,
 * UnexpectedResponseError on malformed success bodies, path-segment encoding
 * of path params, and injection-safe multipart filenames and content types.
 */
final class FleetHardeningTest extends TestCase
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

    // --- https-only base URL -------------------------------------------------

    public function testHttpBaseUrlIsRejectedAtConstruction(): void
    {
        $this->expectException(ValidationError::class);
        new Client(partnerId: '1234', apiKey: 'key', baseUrl: 'http://testapi.smileidentity.com');
    }

    public function testBaseUrlWithQueryIsRejected(): void
    {
        $this->expectException(ValidationError::class);
        new Client(partnerId: '1234', apiKey: 'key', baseUrl: 'https://testapi.smileidentity.com?a=b');
    }

    public function testBaseUrlWithFragmentIsRejected(): void
    {
        $this->expectException(ValidationError::class);
        new Client(partnerId: '1234', apiKey: 'key', baseUrl: 'https://testapi.smileidentity.com#frag');
    }

    public function testRelativeBaseUrlIsRejected(): void
    {
        $this->expectException(ValidationError::class);
        new Client(partnerId: '1234', apiKey: 'key', baseUrl: 'testapi.smileidentity.com');
    }

    // --- https-only callback URLs --------------------------------------------

    public function testHttpDefaultCallbackUrlIsRejectedAtConstruction(): void
    {
        $this->expectException(ValidationError::class);
        new Client(partnerId: '1234', apiKey: 'key', defaultCallbackUrl: 'http://app.example.com/cb');
    }

    public function testHttpPerRequestCallbackUrlIsRejectedBeforeSend(): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs(), callbackUrl: 'http://app.example.com/cb');
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('https', $e->getMessage());
        }

        self::assertCount(0, $mock->history, 'no request may be made for an insecure callback URL');
    }

    public function testHttpReplayCallbackUrlIsRejectedBeforeSend(): void
    {
        $mock = new MockClient([]);

        try {
            $mock->client->verifications->replay('job_1', callbackUrl: 'http://app.example.com/cb');
            self::fail('Expected ValidationError');
        } catch (ValidationError $e) {
            self::assertStringContainsString('https', $e->getMessage());
        }

        self::assertCount(0, $mock->history);
    }

    // --- UnexpectedResponseError ----------------------------------------------

    public function testNonJsonSuccessBodyRaisesUnexpectedResponseError(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(202, ['X-Request-ID' => 'req-42'], '<html>gateway page</html>'),
        ]);

        try {
            $mock->client->enhancedKyc->verify(...$this->entryArgs());
            self::fail('Expected UnexpectedResponseError');
        } catch (UnexpectedResponseError $e) {
            self::assertSame(202, $e->statusCode);
            self::assertSame('<html>gateway page</html>', $e->rawBody);
            self::assertSame('req-42', $e->requestId);
        }
    }

    public function testNonJsonSuccessBodyOnUnauthenticatedGetRaises(): void
    {
        $mock = new MockClient([new Response(200, [], 'plain text')]);

        $this->expectException(UnexpectedResponseError::class);
        $mock->client->services->bankCodes();
    }

    public function testNonJsonTokenSuccessBodyRaises(): void
    {
        $mock = new MockClient([new Response(200, [], 'not json at all')]);

        $this->expectException(UnexpectedResponseError::class);
        $mock->client->verifications->retrieve('job_1');
    }

    public function testRetrieveNotFoundJsonBodyIsUnaffected(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(404, [], '{"status":"not_found","job_id":"job_1","user_id":"unknown","message":"Verification not found"}'),
        ]);

        $status = $mock->client->verifications->retrieve('job_1');

        self::assertTrue($status->isNotFound);
    }

    // --- path-segment encoding ------------------------------------------------

    public function testJobIdIsEncodedAsASinglePathSegment(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(200, [], '{"status":"complete","job_id":"j","user_id":"u","message":"done"}'),
        ]);

        $mock->client->verifications->retrieve('job/../0 ?x');

        self::assertSame(
            '/v3/status/job%2F..%2F0%20%3Fx',
            $mock->request(1)->getUri()->getPath(),
        );
        self::assertSame('', $mock->request(1)->getUri()->getQuery());
    }

    public function testUserIdIsEncodedAsASinglePathSegmentOnReportFraud(): void
    {
        $mock = new MockClient([
            MockClient::tokenResponse(),
            new Response(202, [], '{"status":"accepted","message":"Fraud report accepted","user_id":"u"}'),
        ]);

        $mock->client->users->reportFraud('user/../admin', isFraud: false, reportedBy: 'ops@example.com', notes: 'n');

        self::assertSame(
            '/v3/users/user%2F..%2Fadmin/report_fraud',
            $mock->request(1)->getUri()->getPath(),
        );
    }

    // --- server owns the comparison_image_type 400 -----------------------------

    public function testComparisonImageTypeIsSentVerbatimWithoutClientValidation(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse()]);

        $mock->client->biometric->compare(
            selfieImage: 'bytes',
            comparisonImage: 'bytes',
            comparisonImageType: 'FUTURE_TYPE',
            consent: Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy'),
            userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
        );

        $parts = MultipartParser::parse($mock->request(1));
        self::assertSame('FUTURE_TYPE', MultipartParser::named($parts, 'comparison_image_type')[0]['body']);
    }

    // --- hostile multipart filenames -------------------------------------------

    public function testHostileFilenameCannotInjectPartHeaders(): void
    {
        $mock = new MockClient([MockClient::tokenResponse(), MockClient::acceptedResponse('accepted')]);

        $hostile = "evil\".jpg\r\nX-Injected: 1\r\nContent-Type: text/html";
        $mock->client->documents->verify(
            selfieImage: BinaryInput::fromString('selfie-bytes', $hostile),
            livenessImages: array_fill(0, 6, 'liveness-bytes'),
            document: BinaryInput::fromString('doc-bytes', "doc\rname\n.png\x00"),
            consent: Consent::granted('2026-03-06T12:00:00.000Z', 'EN', 'https://example.com/privacy'),
            country: 'NG',
            userDetails: ['given_names' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
        );

        $body = (string) $mock->request(1)->getBody();

        self::assertStringNotContainsString('X-Injected', $body);
        self::assertStringNotContainsString('text/html', $body);
        self::assertStringNotContainsString("\x00", $body);

        $parts = MultipartParser::parse($mock->request(1));
        $selfie = MultipartParser::named($parts, 'selfie_image')[0];
        self::assertSame('image/jpeg', $selfie['contentType']);
        self::assertSame('selfie-bytes', $selfie['body']);
        self::assertStringNotContainsString('"', (string) $selfie['filename']);

        // The sanitized .png extension still drives PNG content-type detection.
        $document = MultipartParser::named($parts, 'document')[0];
        self::assertSame('docname.png', $document['filename']);
        self::assertSame('doc-bytes', $document['body']);
    }
}
