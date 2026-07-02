<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/** SDK-local: raised by verifications.waitUntilComplete when the deadline passes. */
class TimeoutError extends SmileIDError
{
}
