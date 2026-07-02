<?php

declare(strict_types=1);

namespace SmileIdentity\Helpers;

use SmileIdentity\Errors\ValidationError;

/**
 * Normalizes a binary input into contents + filename for a multipart part.
 *
 * Accepts, per the PHP idiom, a file path, a stream resource, or raw string
 * bytes. Because a plain string is ambiguous, use the explicit factories when
 * intent matters: {@see BinaryInput::fromPath()}, {@see BinaryInput::fromString()},
 * {@see BinaryInput::fromResource()}. A bare string passed to a resource method
 * is read as a file when it names an existing file, otherwise treated as raw
 * bytes.
 */
final class BinaryInput
{
    private const MODE_PATH = 'path';
    private const MODE_BYTES = 'bytes';
    private const MODE_RESOURCE = 'resource';

    /**
     * @param string|resource $value
     */
    private function __construct(
        private readonly string $mode,
        private readonly mixed $value,
        private readonly ?string $filename,
    ) {
    }

    public static function fromPath(string $path): self
    {
        if (!is_file($path)) {
            throw new ValidationError("Binary input path does not exist: {$path}");
        }

        return new self(self::MODE_PATH, $path, basename($path));
    }

    public static function fromString(string $bytes, ?string $filename = null): self
    {
        return new self(self::MODE_BYTES, $bytes, $filename);
    }

    /**
     * @param resource $resource
     */
    public static function fromResource($resource, ?string $filename = null): self
    {
        if (!is_resource($resource)) {
            throw new ValidationError('Binary input is not a valid resource.');
        }

        return new self(self::MODE_RESOURCE, $resource, $filename);
    }

    /**
     * Coerce any accepted binary value into a BinaryInput.
     *
     * @param BinaryInput|string|resource $value
     */
    public static function coerce(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_resource($value)) {
            return self::fromResource($value);
        }
        if (is_string($value)) {
            if (strlen($value) < 4096 && @is_file($value)) {
                return self::fromPath($value);
            }

            return self::fromString($value);
        }

        throw new ValidationError('Binary input must be a BinaryInput, file path, resource, or string of bytes.');
    }

    /**
     * Build a Guzzle multipart entry for this binary input.
     *
     * When $allowPng is true (document/document_back, per the spec encoding
     * block) the content type flips to image/png if the filename ends in .png
     * or the bytes carry the PNG signature. All other image fields are
     * image/jpeg only.
     *
     * @return array{name: string, contents: string|resource, filename: string, headers: array<string, string>}
     */
    public function toMultipartPart(string $name, string $defaultFilename, string $contentType, bool $allowPng = false): array
    {
        if ($allowPng && $this->looksLikePng()) {
            $contentType = 'image/png';
            if ($this->filename === null && str_ends_with($defaultFilename, '.jpg')) {
                $defaultFilename = substr($defaultFilename, 0, -4) . '.png';
            }
        }

        $contents = match ($this->mode) {
            self::MODE_PATH => $this->openFile((string) $this->value),
            self::MODE_RESOURCE => $this->value,
            default => (string) $this->value,
        };

        return [
            'name' => $name,
            'contents' => $contents,
            'filename' => $this->filename ?? $defaultFilename,
            'headers' => ['Content-Type' => $contentType],
        ];
    }

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private function looksLikePng(): bool
    {
        if ($this->filename !== null && str_ends_with(strtolower($this->filename), '.png')) {
            return true;
        }

        if ($this->mode === self::MODE_BYTES) {
            return str_starts_with((string) $this->value, self::PNG_SIGNATURE);
        }
        if ($this->mode === self::MODE_PATH) {
            $head = @file_get_contents((string) $this->value, false, null, 0, 8);

            return $head !== false && $head === self::PNG_SIGNATURE;
        }

        // Streams are not sniffed (they may not be seekable); rely on filename.
        return false;
    }

    /**
     * @return resource
     */
    private function openFile(string $path)
    {
        $stream = @fopen($path, 'r');
        if ($stream === false) {
            throw new ValidationError("Unable to open binary input file: {$path}");
        }

        return $stream;
    }
}
