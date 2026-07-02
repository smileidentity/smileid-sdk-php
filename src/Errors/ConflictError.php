<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/** HTTP 409 (replay still processing). Never auto-retried. */
class ConflictError extends SmileIDError
{
}
