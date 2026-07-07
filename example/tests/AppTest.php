<?php

declare(strict_types=1);

namespace SmileIdentity\Example\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SmileIdentity\Example\App;

final class AppTest extends TestCase
{
    public function testServicesListsReferenceDataWithoutAuthentication(): void
    {
        $fake = new FakeSmileApi([
            new Response(200, [], json_encode(['bank_codes' => [['code' => '001', 'country' => 'NG', 'name' => 'Example Bank']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['id_types' => [['country' => 'NG', 'label' => 'National Identification Number', 'regex' => '^\\d{11}$', 'required_fields' => ['id_number'], 'type' => 'NIN']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['valid_documents' => [['country' => ['code' => 'NG', 'name' => 'Nigeria', 'continent' => 'Africa'], 'id_types' => [['code' => 'PASSPORT', 'name' => 'Passport', 'example' => ['A12345678'], 'has_back' => false]]]]], JSON_THROW_ON_ERROR)),
        ]);
        $out = fopen('php://memory', 'w+');
        $code = (new App())->run(['--base-url', 'https://api.test', 'services', '--country', 'NG'], $this->env(), $out, httpClient: $fake->client);

        self::assertSame(0, $code);
        $result = $this->readJson($out);
        self::assertSame('NG', $result['country']);
        self::assertSame('001', $result['bank_codes'][0]['code']);
        self::assertSame('NIN', $result['id_types'][0]['type']);
        self::assertCount(3, $fake->history);
        self::assertSame('/v3/services/bank_codes', $fake->history[0]['request']->getUri()->getPath());
    }

    public function testEnhancedKycSubmitsVerification(): void
    {
        $fake = new FakeSmileApi([
            FakeSmileApi::tokenResponse(),
            new Response(202, [], json_encode(['status' => 'Accepted', 'message' => 'submitted', 'job_id' => 'job_enhanced_123', 'user_id' => 'user_123'], JSON_THROW_ON_ERROR)),
        ]);
        $out = fopen('php://memory', 'w+');
        $code = (new App())->run([
            '--base-url', 'https://api.test',
            '--callback-url', 'https://example.com/smile-callback',
            'enhanced-kyc',
            '--country', 'NG',
            '--id-type', 'NIN',
            '--id-number', '12345678901',
            '--given-names', 'Amina',
            '--last-name', 'Okafor',
            '--email', 'amina@example.com',
        ], $this->env(), $out, httpClient: $fake->client);

        self::assertSame(0, $code);
        $result = $this->readJson($out);
        self::assertSame('job_enhanced_123', $result['job_id']);
        self::assertTrue($result['accepted']);
        $request = $fake->history[1]['request'];
        self::assertSame('/v3/enhanced_kyc', $request->getUri()->getPath());
        self::assertStringStartsWith('header.', $request->getHeaderLine('SmileID-Token'));
        $body = (string) $request->getBody();
        self::assertStringContainsString('name="country"', $body);
        self::assertStringContainsString('NG', $body);
        self::assertStringContainsString('name="id_type"', $body);
        self::assertStringContainsString('NIN', $body);
        self::assertStringContainsString('https://example.com/smile-callback', $body);
        self::assertStringContainsString('"given_names":"Amina"', $body);
    }

    public function testStatusRetrievesVerification(): void
    {
        $fake = new FakeSmileApi([
            FakeSmileApi::tokenResponse(),
            new Response(200, [], json_encode(['status' => 'complete', 'message' => 'clear', 'job_id' => 'job_enhanced_123', 'user_id' => 'user_123'], JSON_THROW_ON_ERROR)),
        ]);
        $out = fopen('php://memory', 'w+');
        (new App())->run(['--base-url', 'https://api.test', 'status', '--job-id', 'job_enhanced_123'], $this->env(), $out, httpClient: $fake->client);

        $result = $this->readJson($out);
        self::assertSame('complete', $result['status']);
        self::assertSame('clear', $result['message']);
        self::assertSame('/v3/status/job_enhanced_123', $fake->history[1]['request']->getUri()->getPath());
    }

    public function testReplayRequestsCallbackReplay(): void
    {
        $fake = new FakeSmileApi([
            FakeSmileApi::tokenResponse(),
            new Response(200, [], json_encode(['status' => 'success', 'message' => 'replayed', 'job_id' => 'job_enhanced_123', 'user_id' => 'user_123'], JSON_THROW_ON_ERROR)),
        ]);
        $out = fopen('php://memory', 'w+');
        (new App())->run(['--base-url', 'https://api.test', 'replay', '--job-id', 'job_enhanced_123', '--callback-url', 'https://example.com/replay-callback'], $this->env(), $out, httpClient: $fake->client);

        $result = $this->readJson($out);
        self::assertSame('success', $result['status']);
        self::assertSame(['callback_url' => 'https://example.com/replay-callback'], json_decode((string) $fake->history[1]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testHelpDoesNotRequireCredentials(): void
    {
        $out = fopen('php://memory', 'w+');
        $code = (new App())->run(['help'], [], $out);
        self::assertSame(0, $code);
        rewind($out);
        self::assertStringContainsString('Usage:', stream_get_contents($out));
    }

    public function testMissingCredentialsReturnsUsageExit(): void
    {
        $err = fopen('php://memory', 'w+');
        $code = (new App())->run(['services'], [], stderr: $err);
        self::assertSame(2, $code);
        rewind($err);
        self::assertStringContainsString('SMILE_PARTNER_ID', stream_get_contents($err));
    }

    /** @return array<string, string> */
    private function env(): array
    {
        return ['SMILE_PARTNER_ID' => '12345', 'SMILE_API_KEY' => 'test-api-key'];
    }

    /** @return array<string, mixed> */
    private function readJson(mixed $stream): array
    {
        rewind($stream);
        return json_decode((string) stream_get_contents($stream), true, flags: JSON_THROW_ON_ERROR);
    }
}

final class FakeSmileApi
{
    /** @var list<array{request: RequestInterface, response: mixed}> */
    public array $history = [];
    public GuzzleClient $client;

    /** @param list<Response> $responses */
    public function __construct(array $responses)
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $this->client = new GuzzleClient(['handler' => $stack]);
    }

    public static function tokenResponse(): Response
    {
        $payload = rtrim(strtr(base64_encode((string) json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');
        return new Response(200, [], (string) json_encode(['token' => 'header.' . $payload . '.signature']));
    }
}
