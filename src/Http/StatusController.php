<?php

declare(strict_types=1);

namespace OperationalStatus\Http;

use OperationalStatus\Cache\VersionedPublicPageCache;
use OperationalStatus\Domain\IncidentSeverity;
use OperationalStatus\Domain\IncidentStatus;
use OperationalStatus\Repository\StatusRepository;
use OperationalStatus\Security\AdminAuthenticator;
use OperationalStatus\Security\CsrfGuard;
use OperationalStatus\Security\LoginRateLimiter;
use OperationalStatus\Service\CatalogService;
use OperationalStatus\Service\IncidentService;
use OperationalStatus\Service\StatusPageService;
use OperationalStatus\Service\SubscriptionService;
use OperationalStatus\Support\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class StatusController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly StatusPageService $status,
        private readonly StatusRepository $repository,
        private readonly VersionedPublicPageCache $publicCache,
        private readonly AdminAuthenticator $authenticator,
        private readonly LoginRateLimiter $loginLimiter,
        private readonly CsrfGuard $csrf,
        private readonly IncidentService $incidents,
        private readonly CatalogService $catalog,
        private readonly SubscriptionService $subscriptions,
        private readonly string $adminUsername,
    ) {}

    public function publicPage(Request $request): Response
    {
        $html = $this->publicCache->remember(fn(): string => $this->twig->render('status.html.twig', $this->status->snapshot(false)));

        return new Response($html, 200, ['Cache-Control' => 'public, max-age=15, stale-while-revalidate=15']);
    }

    public function history(Request $request): Response
    {
        return new Response($this->twig->render('history.html.twig', $this->status->history(false)));
    }

    public function feed(Request $request): Response
    {
        return new Response(
            $this->twig->render('feed.xml.twig', $this->status->history(false)),
            200,
            ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
        );
    }

    public function apiStatus(Request $request): JsonResponse
    {
        return new JsonResponse($this->status->snapshot(false));
    }

    public function apiComponents(Request $request): JsonResponse
    {
        return new JsonResponse(['components' => $this->repository->components(false)]);
    }

    public function apiIncidents(Request $request): JsonResponse
    {
        return new JsonResponse(['incidents' => $this->repository->recentIncidents(false)]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        $this->subscriptions->subscribe((string) ($payload['email'] ?? ''), (string) ($payload['scope'] ?? 'all'));

        return new JsonResponse([
            'status' => 'subscribed',
            'delivery' => 'disabled',
            'message' => 'The subscription was recorded; this project does not send email.',
        ], 201);
    }

    public function health(Request $request): JsonResponse
    {
        $this->repository->groups();

        return new JsonResponse(['status' => 'UP', 'database' => 'UP']);
    }

    public function login(Request $request): Response
    {
        if ('POST' === $request->getMethod()) {
            $this->csrf->assertValid('login', $request->request->getString('_csrf'));
            $clientKey = $request->getClientIp() ?? 'unknown';
            $limit = $this->loginLimiter->consume($clientKey);
            if (!$limit->isAccepted()) {
                return new Response($this->twig->render('admin/login.html.twig', [
                    'error' => 'Too many login attempts. Try again later.',
                ]), 429, ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
            }
            if ($this->authenticator->login($request->getSession(), $request->request->getString('username'), $request->request->getString('password'))) {
                $this->loginLimiter->reset($clientKey);
                $request->getSession()->set('flash', 'Signed in successfully.');

                return new RedirectResponse('/admin');
            }

            return new Response($this->twig->render('admin/login.html.twig', ['error' => 'Invalid credentials.']), 401);
        }

        return new Response($this->twig->render('admin/login.html.twig'));
    }

    public function logout(Request $request): Response
    {
        $this->csrf->assertValid('logout', $request->request->getString('_csrf'));
        $this->authenticator->logout($request->getSession());

        return new RedirectResponse('/admin/login');
    }

    public function admin(Request $request): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $snapshot = $this->status->snapshot(true);
        $snapshot['allComponents'] = $this->repository->components(true);
        $snapshot['allGroups'] = $this->repository->groups();
        $snapshot['audit'] = $this->repository->auditEvents();
        $snapshot['flash'] = $request->getSession()->remove('flash');
        $snapshot['severityValues'] = IncidentSeverity::cases();
        $snapshot['incidentStatuses'] = IncidentStatus::cases();

        return new Response($this->twig->render('admin/dashboard.html.twig', $snapshot));
    }

    public function createGroup(Request $request): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $this->csrf->assertValid('admin_write', $request->request->getString('_csrf'));
        $this->catalog->createGroup(
            $request->request->getString('name'),
            $request->request->getString('description'),
            $this->adminUsername,
            $this->requestId($request),
        );

        return $this->adminRedirect($request, 'Component group created.');
    }

    public function createComponent(Request $request): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $this->csrf->assertValid('admin_write', $request->request->getString('_csrf'));
        $groupId = trim($request->request->getString('group_id'));
        $this->catalog->createComponent(
            $request->request->getString('name'),
            $request->request->getString('description'),
            '' === $groupId ? null : $groupId,
            $request->request->getBoolean('is_internal'),
            $this->adminUsername,
            $this->requestId($request),
        );

        return $this->adminRedirect($request, 'Component created.');
    }

    public function createIncident(Request $request): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $this->csrf->assertValid('admin_write', $request->request->getString('_csrf'));
        $componentIds = array_values(array_filter(array_map('strval', $request->request->all('component_ids'))));
        $this->incidents->create(
            $request->request->getString('title'),
            $request->request->getString('description'),
            IncidentSeverity::from($request->request->getString('severity')),
            $componentIds,
            $this->adminUsername,
            $this->requestId($request),
        );

        return $this->adminRedirect($request, 'Incident published.');
    }

    public function updateIncident(Request $request, string $id): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $this->csrf->assertValid('admin_write', $request->request->getString('_csrf'));
        $this->incidents->update(
            $id,
            IncidentStatus::from($request->request->getString('status')),
            $request->request->getString('message'),
            $request->request->getInt('lock_version'),
            $this->adminUsername,
            $this->requestId($request),
        );

        return $this->adminRedirect($request, 'Immutable incident update published.');
    }

    public function createMaintenance(Request $request): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }
        $this->csrf->assertValid('admin_write', $request->request->getString('_csrf'));
        $componentIds = array_values(array_filter(array_map('strval', $request->request->all('component_ids'))));
        $this->catalog->scheduleMaintenance(
            $request->request->getString('title'),
            $request->request->getString('description'),
            new \DateTimeImmutable($request->request->getString('starts_at')),
            new \DateTimeImmutable($request->request->getString('ends_at')),
            $componentIds,
            $this->adminUsername,
            $this->requestId($request),
        );

        return $this->adminRedirect($request, 'Maintenance scheduled.');
    }

    private function requireAdmin(Request $request): ?RedirectResponse
    {
        return $this->authenticator->isAuthenticated($request->getSession()) ? null : new RedirectResponse('/admin/login');
    }

    private function adminRedirect(Request $request, string $message): RedirectResponse
    {
        $request->getSession()->set('flash', $message);

        return new RedirectResponse('/admin');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $payload = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : [];
        }

        return $request->request->all();
    }

    private function requestId(Request $request): string
    {
        $requestId = trim((string) $request->headers->get('X-Request-ID'));

        return '' === $requestId ? Uuid::v4() : mb_substr($requestId, 0, 100);
    }
}
