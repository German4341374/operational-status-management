<?php

declare(strict_types=1);

namespace OperationalStatus\Security;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class AdminAuthenticator
{
    private const string SESSION_KEY = 'authenticated_admin';

    public function __construct(
        private readonly string $expectedUsername,
        #[\SensitiveParameter]
        private readonly string $passwordHash,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {}

    public function login(SessionInterface $session, string $username, #[\SensitiveParameter] string $password): bool
    {
        $hasher = $this->hasherFactory->getPasswordHasher('admin');
        $validUser = hash_equals($this->expectedUsername, $username);
        $validPassword = $hasher->verify($this->passwordHash, $password);
        if (!$validUser || !$validPassword) {
            return false;
        }

        $session->migrate(true, 0);
        $session->set(self::SESSION_KEY, $this->expectedUsername);

        return true;
    }

    public function logout(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
        $session->invalidate();
    }

    public function isAuthenticated(SessionInterface $session): bool
    {
        return hash_equals($this->expectedUsername, (string) $session->get(self::SESSION_KEY, ''));
    }
}
