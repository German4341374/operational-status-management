<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Security\SessionSecurity;
use PHPUnit\Framework\TestCase;

final class SessionSecurityTest extends TestCase
{
    public function testProductionCookieSettingsAreHardened(): void
    {
        $options = (new SessionSecurity())->options(true);

        self::assertTrue($options['cookie_httponly']);
        self::assertTrue($options['cookie_secure']);
        self::assertSame('Strict', $options['cookie_samesite']);
        self::assertTrue($options['use_only_cookies']);
        self::assertTrue($options['use_strict_mode']);
        self::assertGreaterThanOrEqual(48, $options['sid_length']);
    }

    public function testLocalModeCanDisableSecureCookieWithoutWeakeningOtherFlags(): void
    {
        $options = (new SessionSecurity())->options(false);

        self::assertFalse($options['cookie_secure']);
        self::assertTrue($options['cookie_httponly']);
        self::assertSame('Strict', $options['cookie_samesite']);
    }
}
