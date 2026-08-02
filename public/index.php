<?php

declare(strict_types=1);

use OperationalStatus\Support\AppFactory;
use OperationalStatus\Support\Config;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (is_file($root . '/.env')) {
    (new Dotenv())->loadEnv($root . '/.env');
}

$request = Request::createFromGlobals();
$requestId = trim((string) $request->headers->get('X-Request-ID'));
$request->headers->set('X-Request-ID', '' === $requestId ? bin2hex(random_bytes(16)) : mb_substr($requestId, 0, 100));
$response = AppFactory::create($root, Config::fromEnvironment())->handle($request);
$response->headers->set('X-Request-ID', (string) $request->headers->get('X-Request-ID'));
$response->send();
