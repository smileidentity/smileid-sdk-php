<?php

declare(strict_types=1);

namespace SmileIdentity\Tests;

use PHPUnit\Framework\TestCase;
use SmileIdentity\Version;

final class VersionTest extends TestCase
{
    public function testVersionConstant(): void
    {
        $this->assertSame('12.0.0', Version::VERSION);
    }
}
