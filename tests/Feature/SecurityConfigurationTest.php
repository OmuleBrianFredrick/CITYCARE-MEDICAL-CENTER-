<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityConfigurationTest extends TestCase
{
    public function test_session_security_defaults_are_hardened(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('json', config('session.serialization'));

        $expectedSecure = app()->environment('production');
        $this->assertSame($expectedSecure, (bool) config('session.secure'));
    }

    public function test_password_reset_and_confirmation_security_defaults_are_bounded(): void
    {
        $this->assertGreaterThanOrEqual(1, config('auth.passwords.users.expire'));
        $this->assertLessThanOrEqual(120, config('auth.passwords.users.expire'));
        $this->assertGreaterThanOrEqual(1, config('auth.passwords.users.throttle'));
        $this->assertLessThanOrEqual(300, config('auth.passwords.users.throttle'));
        $this->assertGreaterThanOrEqual(300, config('auth.password_timeout'));
        $this->assertLessThanOrEqual(14400, config('auth.password_timeout'));
    }
}
