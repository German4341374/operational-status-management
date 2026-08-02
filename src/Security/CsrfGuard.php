<?php

declare(strict_types=1);

namespace OperationalStatus\Security;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class CsrfGuard
{
    public function __construct(private readonly CsrfTokenManagerInterface $manager) {}

    public function token(string $intent): string
    {
        return $this->manager->getToken($intent)->getValue();
    }

    public function assertValid(string $intent, ?string $value): void
    {
        if (null === $value || '' === $value || !$this->manager->isTokenValid(new CsrfToken($intent, $value))) {
            throw new InvalidCsrfToken('The CSRF token is missing or invalid.');
        }
    }
}
