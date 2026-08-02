<?php

declare(strict_types=1);

namespace OperationalStatus\Tests\Unit;

use OperationalStatus\Security\CsrfGuard;
use OperationalStatus\Security\InvalidCsrfToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class CsrfGuardTest extends TestCase
{
    private CsrfGuard $guard;

    protected function setUp(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/admin');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);
        $this->guard = new CsrfGuard(new CsrfTokenManager(null, new SessionTokenStorage($stack), 'test-namespace'));
    }

    public function testIssuedTokenIsAcceptedForSameIntent(): void
    {
        $this->guard->assertValid('admin_write', $this->guard->token('admin_write'));
        self::addToAssertionCount(1);
    }

    public function testTokenCannotBeUsedForDifferentIntent(): void
    {
        $this->expectException(InvalidCsrfToken::class);
        $this->guard->assertValid('logout', $this->guard->token('admin_write'));
    }

    public function testMissingTokenIsRejected(): void
    {
        $this->expectException(InvalidCsrfToken::class);
        $this->guard->assertValid('admin_write', null);
    }

    public function testForgedTokenIsRejected(): void
    {
        $this->expectException(InvalidCsrfToken::class);
        $this->guard->assertValid('admin_write', str_repeat('x', 43));
    }
}
