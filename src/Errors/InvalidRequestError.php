<?php

declare(strict_types=1);

namespace SmileIdentity\Errors;

/** HTTP 400 and 415, plus client-side validation failures raised before send. */
class InvalidRequestError extends SmileIDError
{
}
