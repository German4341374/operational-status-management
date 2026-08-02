<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Security\AdminAuthenticator;
use OperationalStatus\Security\LoginRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

final class AuthenticationTest extends TestCase
{
    public function testValidPasswordCreatesAuthenticatedSession(): void
    {
        $factory = new PasswordHasherFactory(['admin' => ['algorithm' => 'auto']]);
        $hash = $factory->getPasswordHasher('admin')->hash('Strong-Test-Password-2026');
        $authenticator = new AdminAuthenticator('admin', $hash, $factory);
        $session = new Session(new MockArraySessionStorage());

        self::assertTrue($authenticator->login($session, 'admin', 'Strong-Test-Password-2026'));
        self::assertTrue($authenticator->isAuthenticated($session));
    }

    public function testWrongUsernameOrPasswordDoesNotAuthenticate(): void
    {
        $factory = new PasswordHasherFactory(['admin' => ['algorithm' => 'auto']]);
        $hash = $factory->getPasswordHasher('admin')->hash('Strong-Test-Password-2026');
        $authenticator = new AdminAuthenticator('admin', $hash, $factory);
        $session = new Session(new MockArraySessionStorage());

        self::assertFalse($authenticator->login($session, 'operator', 'Strong-Test-Password-2026'));
        self::assertFalse($authenticator->login($session, 'admin', 'wrong-password'));
        self::assertFalse($authenticator->isAuthenticated($session));
    }

    public function testLogoutInvalidatesAuthentication(): void
    {
        $factory = new PasswordHasherFactory(['admin' => ['algorithm' => 'auto']]);
        $hash = $factory->getPasswordHasher('admin')->hash('Strong-Test-Password-2026');
        $authenticator = new AdminAuthenticator('admin', $hash, $factory);
        $session = new Session(new MockArraySessionStorage());
        $authenticator->login($session, 'admin', 'Strong-Test-Password-2026');

        $authenticator->logout($session);

        self::assertFalse($authenticator->isAuthenticated($session));
    }

    public function testLoginLimiterRejectsAttemptBeyondWindowLimit(): void
    {
        $limiter = new LoginRateLimiter(new ArrayAdapter(), 2);

        self::assertTrue($limiter->consume('192.0.2.10')->isAccepted());
        self::assertTrue($limiter->consume('192.0.2.10')->isAccepted());
        self::assertFalse($limiter->consume('192.0.2.10')->isAccepted());
        self::assertTrue($limiter->consume('192.0.2.11')->isAccepted());
    }
}
