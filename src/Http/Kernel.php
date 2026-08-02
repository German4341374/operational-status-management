<?php

declare(strict_types=1);

namespace OperationalStatus\Http;

use OperationalStatus\Security\InvalidCsrfToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

final class Kernel
{
    public function __construct(
        private readonly \Symfony\Component\Routing\RouteCollection $routes,
        private readonly StatusController $controller,
        private readonly SessionInterface $session,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {}

    public function handle(Request $request): Response
    {
        $request->setSession($this->session);
        $this->requestStack->push($request);
        try {
            $context = (new RequestContext())->fromRequest($request);
            $parameters = (new UrlMatcher($this->routes, $context))->match($request->getPathInfo());
            $method = (string) $parameters['_controller'];
            unset($parameters['_controller'], $parameters['_route']);
            /** @var Response $response */
            $response = $this->controller->{$method}($request, ...$parameters);
        } catch (ResourceNotFoundException) {
            $response = $this->error($request, 404, 'Not found', 'The requested resource does not exist.');
        } catch (InvalidCsrfToken $exception) {
            $response = $this->error($request, 419, 'Invalid CSRF token', $exception->getMessage());
        } catch (\DomainException $exception) {
            $response = $this->error($request, 409, 'Conflict', $exception->getMessage());
        } catch (\InvalidArgumentException|\JsonException $exception) {
            $response = $this->error($request, 422, 'Invalid request', $exception->getMessage());
        } catch (\Throwable $exception) {
            $requestId = (string) ($request->headers->get('X-Request-ID') ?? 'unassigned');
            $this->logger->error('Unhandled request failure', [
                'request_id' => $requestId,
                'path' => $request->getPathInfo(),
                'exception' => $exception,
            ]);
            $detail = $this->debug ? $exception->getMessage() : 'An unexpected error occurred. Reference the request ID in support communication.';
            $response = $this->error($request, 500, 'Internal server error', $detail);
        } finally {
            $this->requestStack->pop();
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function error(Request $request, int $status, string $title, string $detail): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse([
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                'instance' => $request->getPathInfo(),
            ], $status, ['Content-Type' => 'application/problem+json']);
        }

        return new Response(sprintf(
            '<!doctype html><html lang="en"><meta charset="utf-8"><title>%s</title><body><h1>%s</h1><p>%s</p><a href="/">Return to status page</a></body></html>',
            htmlspecialchars($title),
            htmlspecialchars($title),
            htmlspecialchars($detail),
        ), $status);
    }
}
