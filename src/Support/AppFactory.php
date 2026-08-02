<?php

declare(strict_types=1);

namespace OperationalStatus\Support;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use OperationalStatus\Cache\DatabaseCacheGenerationStore;
use OperationalStatus\Cache\VersionedPublicPageCache;
use OperationalStatus\Domain\IncidentLifecycle;
use OperationalStatus\Domain\OverallStatusCalculator;
use OperationalStatus\Domain\UptimeCalculator;
use OperationalStatus\Http\Kernel;
use OperationalStatus\Http\StatusController;
use OperationalStatus\Repository\StatusRepository;
use OperationalStatus\Security\AdminAuthenticator;
use OperationalStatus\Security\CsrfGuard;
use OperationalStatus\Security\LoginRateLimiter;
use OperationalStatus\Security\SessionSecurity;
use OperationalStatus\Service\CatalogService;
use OperationalStatus\Service\IncidentService;
use OperationalStatus\Service\StatusPageService;
use OperationalStatus\Service\SubscriptionService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Twig\Environment;
use Twig\Extension\StringLoaderExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class AppFactory
{
    public static function create(string $root, Config $config): Kernel
    {
        $secret = $config->require('APP_SECRET');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('APP_SECRET must contain at least 32 characters.');
        }
        $pdo = DatabaseFactory::create($config);
        $repository = new StatusRepository($pdo);
        $cachePool = new FilesystemAdapter('public_status', 0, $root . '/var/cache/public');
        $pageCache = new VersionedPublicPageCache(
            $cachePool,
            new DatabaseCacheGenerationStore($pdo),
            max(1, min($config->int('PUBLIC_CACHE_TTL', 30), 300)),
        );

        $session = (new SessionSecurity())->create($root . '/var/sessions', $config->bool('SESSION_SECURE', true));
        $requestStack = new RequestStack();
        $csrfManager = new CsrfTokenManager(null, new SessionTokenStorage($requestStack), hash('sha256', $secret));
        $csrf = new CsrfGuard($csrfManager);

        $twig = new Environment(new FilesystemLoader($root . '/templates'), [
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $twig->addExtension(new StringLoaderExtension());
        $twig->addFunction(new TwigFunction('csrf_token', static fn(string $intent): string => $csrf->token($intent)));
        $twig->addGlobal('app_url', rtrim($config->get('APP_URL', 'http://localhost:8080'), '/'));

        $hasherFactory = new PasswordHasherFactory([
            'admin' => ['algorithm' => 'auto', 'cost' => 12],
        ]);
        $adminUsername = $config->require('ADMIN_USERNAME');
        $authenticator = new AdminAuthenticator(
            $adminUsername,
            $config->require('ADMIN_PASSWORD_HASH'),
            $hasherFactory,
        );
        $rateCache = new FilesystemAdapter('login_rate', 0, $root . '/var/cache/rate-limit');
        $loginLimiter = new LoginRateLimiter($rateCache);
        $status = new StatusPageService($repository, new OverallStatusCalculator(), new UptimeCalculator());
        $incidentService = new IncidentService($repository, new IncidentLifecycle(), $pageCache);
        $catalogService = new CatalogService($repository, $pageCache);
        $subscriptions = new SubscriptionService($repository, $secret);

        $controller = new StatusController(
            $twig,
            $status,
            $repository,
            $pageCache,
            $authenticator,
            $loginLimiter,
            $csrf,
            $incidentService,
            $catalogService,
            $subscriptions,
            $adminUsername,
        );

        return new Kernel(
            self::routes(),
            $controller,
            $session,
            $requestStack,
            self::logger($root),
            $config->bool('APP_DEBUG'),
        );
    }

    private static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('public_status', new Route('/', ['_controller' => 'publicPage'], methods: ['GET']));
        $routes->add('history', new Route('/history', ['_controller' => 'history'], methods: ['GET']));
        $routes->add('feed', new Route('/feed.xml', ['_controller' => 'feed'], methods: ['GET']));
        $routes->add('health', new Route('/health', ['_controller' => 'health'], methods: ['GET']));
        $routes->add('api_status', new Route('/api/status', ['_controller' => 'apiStatus'], methods: ['GET']));
        $routes->add('api_components', new Route('/api/components', ['_controller' => 'apiComponents'], methods: ['GET']));
        $routes->add('api_incidents', new Route('/api/incidents', ['_controller' => 'apiIncidents'], methods: ['GET']));
        $routes->add('api_subscribe', new Route('/api/subscriptions', ['_controller' => 'subscribe'], methods: ['POST']));
        $routes->add('admin_login', new Route('/admin/login', ['_controller' => 'login'], methods: ['GET', 'POST']));
        $routes->add('admin_logout', new Route('/admin/logout', ['_controller' => 'logout'], methods: ['POST']));
        $routes->add('admin_dashboard', new Route('/admin', ['_controller' => 'admin'], methods: ['GET']));
        $routes->add('admin_group', new Route('/admin/groups', ['_controller' => 'createGroup'], methods: ['POST']));
        $routes->add('admin_component', new Route('/admin/components', ['_controller' => 'createComponent'], methods: ['POST']));
        $routes->add('admin_incident', new Route('/admin/incidents', ['_controller' => 'createIncident'], methods: ['POST']));
        $routes->add('admin_incident_update', new Route('/admin/incidents/{id}/updates', ['_controller' => 'updateIncident'], requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST']));
        $routes->add('admin_maintenance', new Route('/admin/maintenance', ['_controller' => 'createMaintenance'], methods: ['POST']));

        return $routes;
    }

    private static function logger(string $root): Logger
    {
        if (!is_dir($root . '/var/log') && !mkdir($root . '/var/log', 0o750, true) && !is_dir($root . '/var/log')) {
            throw new \RuntimeException('Log directory could not be created.');
        }
        $handler = new StreamHandler($root . '/var/log/application.log', Level::Info);
        $handler->setFormatter(new JsonFormatter());

        return new Logger('operational-status', [$handler]);
    }
}
