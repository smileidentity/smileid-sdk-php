<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Version;

final class VersionTest extends TestCase
{
    public function testVersionConstant(): void
    {
        $this->assertSame('0.1.0', Version::VERSION);
    }
}
