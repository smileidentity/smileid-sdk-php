<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Client;

/**
 * Keeps spec-coverage.json honest about the code. CI checks the file against
 * the spec; this checks that every method it names exists and that its
 * supported paths are exactly the paths the SDK sends requests to.
 *
 * The SDK has no operation table, so the paths come from the one place that
 * defines them: the ApiRequest paths in src/Generated/Operations/Operations.php,
 * plus the token path in src/Client/Transport.php.
 */
final class SpecCoverageTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    /** @var array<string, array<string, mixed>> */
    private static array $operations;

    public static function setUpBeforeClass(): void
    {
        $manifest = json_decode((string) file_get_contents(self::ROOT . '/spec-coverage.json'), true, flags: JSON_THROW_ON_ERROR);
        self::$operations = $manifest['operations'];
    }

    public function testEverySupportedMethodExists(): void
    {
        $client = new Client(partnerId: '1234', apiKey: 'test-api-key');

        foreach (self::supported() as $opId => $entry) {
            $method = $entry['method'];
            if (preg_match('/^(\w+)::(\w+)$/', $method, $m) === 1) {
                // An internal function, for an operation with no public method.
                $class = 'SmileIdentity\\Client\\' . $m[1];
                $this->assertTrue(method_exists($class, $m[2]), "{$opId}: {$method} doesn't exist.");
                continue;
            }

            $this->assertMatchesRegularExpression('/^client(->\w+)+$/', $method, "{$opId}: {$method} isn't client->...->method.");
            $chain = explode('->', $method);
            $name = array_pop($chain);
            array_shift($chain);
            $target = $client;
            foreach ($chain as $property) {
                $this->assertTrue(
                    property_exists($target, $property) && (new \ReflectionProperty($target, $property))->isPublic(),
                    "{$opId}: {$method}: no public property {$property}.",
                );
                $target = $target->{$property};
            }
            $this->assertTrue(is_callable([$target, $name]), "{$opId}: {$method} isn't a public method.");
        }
    }

    public function testSupportedPathsMatchTheRequestPaths(): void
    {
        $manifest = [];
        foreach (self::supported() as $entry) {
            $manifest[] = self::normalise($entry['path']);
        }
        sort($manifest);

        $this->assertSame($manifest, self::requestPaths());
    }

    /** @return array<string, array<string, mixed>> */
    private static function supported(): array
    {
        return array_filter(self::$operations, static fn (array $entry): bool => !isset($entry['unsupported']));
    }

    /**
     * Every "METHOD /path" the SDK can send, with path parameters as {}.
     *
     * @return list<string>
     */
    private static function requestPaths(): array
    {
        $source = (string) file_get_contents(self::ROOT . '/src/Generated/Operations/Operations.php');
        $count = preg_match_all("/method: '(\\w+)',\\s*path: (.+),\\n/", $source, $matches, PREG_SET_ORDER);
        self::assertSame(substr_count($source, 'new ApiRequest('), $count, 'Every ApiRequest needs a method and path the test can read.');

        $paths = [];
        foreach ($matches as [, $method, $expression]) {
            foreach (self::alternatives($expression) as $path) {
                $paths[] = "{$method} {$path}";
            }
        }

        $transport = (string) file_get_contents(self::ROOT . '/src/Client/Transport.php');
        $count = preg_match_all("/->request\\('(\\w+)', \\\$this->config->baseUrl \\. '([^']+)'/", $transport, $matches, PREG_SET_ORDER);
        self::assertSame(1, $count, 'Transport.php should send one fixed path, the token endpoint.');
        $paths[] = "{$matches[0][1]} {$matches[0][2]}";

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * The paths a PHP path expression can produce: string literals joined
     * with `.`, rawurlencode() for a path parameter, or `$flag ? a : b`.
     *
     * @return list<string>
     */
    private static function alternatives(string $expression): array
    {
        if (preg_match('/^\$\w+ \? (.+) : (.+)$/', $expression, $m) === 1) {
            return [...self::alternatives($m[1]), ...self::alternatives($m[2])];
        }

        $path = '';
        foreach (explode(' . ', $expression) as $piece) {
            if (preg_match("/^'([^']*)'$/", $piece, $m) === 1) {
                $path .= $m[1];
            } elseif (preg_match('/^rawurlencode\(\$\w+\)$/', $piece) === 1) {
                $path .= '{}';
            } else {
                self::fail("Can't read the path expression `{$expression}`.");
            }
        }

        return [$path];
    }

    private static function normalise(string $path): string
    {
        return (string) preg_replace('/\{\w+\}/', '{}', $path);
    }
}
