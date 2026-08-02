<?php

declare(strict_types=1);

namespace OperationalStatus\Service;

use OperationalStatus\Repository\StatusRepository;

final class SubscriptionService
{
    public function __construct(private readonly StatusRepository $repository, #[\SensitiveParameter] private readonly string $secret) {}

    public function subscribe(string $email, string $scope = 'all'): void
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }
        $hash = hash_hmac('sha256', $email, $this->secret);
        $this->repository->subscribe($hash, '' === trim($scope) ? 'all' : mb_substr(trim($scope), 0, 120));
    }
}
