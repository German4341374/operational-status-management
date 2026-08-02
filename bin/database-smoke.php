#!/usr/bin/env php
<?php

declare(strict_types=1);

use OperationalStatus\Support\Config;
use OperationalStatus\Support\DatabaseFactory;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
if (is_file(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->loadEnv(dirname(__DIR__) . '/.env');
}

$pdo = DatabaseFactory::create(Config::fromEnvironment());
$count = static function (PDO $connection, string $sql): int {
    $statement = $connection->query($sql);
    if (false === $statement) {
        throw new RuntimeException('Smoke query failed to execute.');
    }

    return (int) $statement->fetchColumn();
};
$assertions = [
    'component groups' => $count($pdo, 'SELECT COUNT(*) FROM component_groups') >= 2,
    'components' => $count($pdo, 'SELECT COUNT(*) FROM components') >= 4,
    'incident updates' => $count($pdo, 'SELECT COUNT(*) FROM incident_updates') >= 2,
    'migrations' => $count($pdo, 'SELECT COUNT(*) FROM schema_migrations') >= 2,
];
foreach ($assertions as $label => $passed) {
    if (!$passed) {
        throw new RuntimeException('Database smoke check failed: ' . $label);
    }
    fwrite(STDOUT, 'PASS ' . $label . PHP_EOL);
}

$pdo->beginTransaction();
try {
    $pdo->exec("UPDATE incident_updates SET message = 'mutation must fail'");
    throw new RuntimeException('Immutable incident update trigger did not reject an update.');
} catch (PDOException $exception) {
    $pdo->rollBack();
    if (!str_contains($exception->getMessage(), 'immutable')) {
        throw $exception;
    }
    fwrite(STDOUT, "PASS immutable incident updates\n");
}
