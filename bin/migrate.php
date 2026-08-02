#!/usr/bin/env php
<?php

declare(strict_types=1);

use OperationalStatus\Support\Config;
use OperationalStatus\Support\DatabaseFactory;
use OperationalStatus\Support\MigrationRunner;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (is_file(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->loadEnv(dirname(__DIR__) . '/.env');
}

$runner = new MigrationRunner(DatabaseFactory::create(Config::fromEnvironment()), dirname(__DIR__) . '/migrations');
foreach ($runner->migrate() as $migration) {
    fwrite(STDOUT, 'Applied ' . $migration . PHP_EOL);
}
fwrite(STDOUT, "Database migrations are current.\n");
