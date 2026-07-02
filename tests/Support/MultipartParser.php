<?php

declare(strict_types=1);

namespace SmileIdentity\Tests\Support;

use Psr\Http\Message\RequestInterface;

/**
 * Parses a multipart/form-data request body into its parts so tests can assert
 * the exact wire shape: field names, repeated parts, per-part content types,
 * filenames, and part bodies.
 */
final class MultipartParser
{
    /**
     * @return list<array{name: ?string, filename: ?string, contentType: ?string, body: string}>
     */
    public static function parse(RequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (preg_match('/boundary=([^;]+)/', $contentType, $m) !== 1) {
            throw new \RuntimeException('Request has no multipart boundary: ' . $contentType);
        }
        $boundary = trim($m[1], '" ');

        $body = (string) $request->getBody();
        $segments = explode('--' . $boundary, $body);
        // Drop the preamble and the trailing "--\r\n" epilogue.
        array_shift($segments);
        array_pop($segments);

        $parts = [];
        foreach ($segments as $segment) {
            $segment = ltrim($segment, "\r\n");
            $split = strpos($segment, "\r\n\r\n");
            if ($split === false) {
                continue;
            }
            $rawHeaders = substr($segment, 0, $split);
            $partBody = substr($segment, $split + 4);
            $partBody = preg_replace('/\r\n$/', '', $partBody) ?? $partBody;

            $name = null;
            $filename = null;
            $partContentType = null;
            foreach (explode("\r\n", $rawHeaders) as $headerLine) {
                if (stripos($headerLine, 'Content-Disposition:') === 0) {
                    if (preg_match('/name="([^"]*)"/', $headerLine, $nm) === 1) {
                        $name = $nm[1];
                    }
                    if (preg_match('/filename="([^"]*)"/', $headerLine, $fm) === 1) {
                        $filename = $fm[1];
                    }
                } elseif (stripos($headerLine, 'Content-Type:') === 0) {
                    $partContentType = trim(substr($headerLine, strlen('Content-Type:')));
                }
            }

            $parts[] = [
                'name' => $name,
                'filename' => $filename,
                'contentType' => $partContentType,
                'body' => $partBody,
            ];
        }

        return $parts;
    }

    /**
     * @param list<array{name: ?string, filename: ?string, contentType: ?string, body: string}> $parts
     *
     * @return list<array{name: ?string, filename: ?string, contentType: ?string, body: string}>
     */
    public static function named(array $parts, string $name): array
    {
        return array_values(array_filter($parts, static fn (array $p): bool => $p['name'] === $name));
    }
}
