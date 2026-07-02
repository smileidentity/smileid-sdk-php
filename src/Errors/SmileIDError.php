<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/**
 * Base class for every error the SDK raises.
 *
 * Subclasses are selected by HTTP status (see {@see ErrorFactory}). SDK-local
 * errors (no HTTP response) leave $statusCode null.
 *
 * The services error code (the {error, code} wire shape) is exposed as
 * $error->code (also via getCode()). It reuses the inherited \Exception::$code
 * slot, which is why it is not a declared readonly property.
 *
 * @property-read ?string $code the API error code, e.g. "2413" (services endpoints only)
 */
class SmileIDError extends \Exception
{
    public function __construct(
        string $message = '',
        public readonly ?int $statusCode = null,
        public readonly ?string $status = null,
        ?string $code = null,
        public readonly ?string $requestId = null,
        public readonly ?string $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        if ($code !== null) {
            $this->code = $code;
        }
    }

    public function __get(string $name): mixed
    {
        if ($name === 'code') {
            return is_string($this->code) ? $this->code : null;
        }

        trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_WARNING);

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'code' && is_string($this->code);
    }
}
