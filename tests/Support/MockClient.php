<?php

declare(strict_types=1);

namespace SmileIdentity\Tests\Support;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SmileIdentity\Client;

/**
 * Builds a Client wired to a Guzzle MockHandler, with a history middleware so
 * tests can assert the exact wire request. A no-op sleeper keeps retry/poll
 * tests instant.
 */
final class MockClient
{
    /** @var list<array{request: RequestInterface, response: mixed}> */
    public array $history = [];

    public readonly Client $client;
    public readonly MockHandler $mock;

    /** @var list<float> seconds passed to the sleeper (backoff + poll intervals) */
    public array $sleeps = [];

    /**
     * @param list<Response|\Throwable> $responses
     * @param array<string, mixed> $config extra Client constructor args
     */
    public function __construct(array $responses, array $config = [])
    {
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $this->client = new Client(
            partnerId: $config['partnerId'] ?? '1234',
            apiKey: $config['apiKey'] ?? 'test-api-key',
            environment: $config['environment'] ?? 'sandbox',
            defaultCallbackUrl: $config['defaultCallbackUrl'] ?? null,
            baseUrl: $config['baseUrl'] ?? null,
            timeout: $config['timeout'] ?? 30.0,
            maxRetries: $config['maxRetries'] ?? 2,
            httpClient: $guzzle,
            sleeper: function (float $s): void {
                $this->sleeps[] = $s;
            },
        );
    }

    public function request(int $index): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    public function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    /** A 200 token response whose JWT carries an exp one hour in the future. */
    public static function tokenResponse(?int $exp = null): Response
    {
        $exp ??= time() + 3600;
        $payload = rtrim(strtr(base64_encode((string) json_encode(['exp' => $exp])), '+/', '-_'), '=');
        $jwt = 'header.' . $payload . '.signature';

        return new Response(200, [], (string) json_encode(['token' => $jwt]));
    }

    public static function acceptedResponse(string $status = 'Accepted'): Response
    {
        return new Response(202, [], (string) json_encode([
            'status' => $status,
            'message' => 'Request accepted and queued for processing.',
            'job_id' => 'job_01h8x9y2z3a4b5c6d7e8f9g0h1',
            'user_id' => 'user_01h8x9y2z3a4b5c6d7e8f9g0h1',
        ]));
    }
}
