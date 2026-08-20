<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * phpunit.xml's <env force="true"> entries land in $_ENV/putenv() (PHPUnit's
     * PhpHandler::handleEnvVariables()) but never touch $_SERVER — and Laravel's
     * env() resolution (Illuminate\Support\Env, via Dotenv's
     * RepositoryBuilder::createWithDefaultAdapters()) checks $_SERVER first. In
     * this project's docker-compose dev container, the app service's
     * `env_file: .env` already exports APP_ENV=local, DB_DATABASE=genius_
     * ticket_manager, etc. as real process env vars, which PHP mirrors into
     * $_SERVER at process start — so without this, `docker compose exec app php
     * artisan test` silently ran every test against the live dev database with
     * real CSRF enforcement instead of the isolated testing config phpunit.xml
     * declares. Mirroring PHPUnit's already-forced $_ENV values into $_SERVER
     * here, immediately before bootstrap/app.php loads for each test, closes
     * that gap regardless of what already sits in the container's real
     * environment or how early/late Pest's own bootstrap files happen to run
     * relative to PHPUnit's <php> config processing.
     */
    public function createApplication()
    {
        foreach ([
            'APP_ENV', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION',
            'CACHE_STORE', 'DB_CONNECTION', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'DB_URL', 'MAIL_MAILER', 'QUEUE_CONNECTION', 'SESSION_DRIVER', 'PULSE_ENABLED',
            'TELESCOPE_ENABLED', 'NIGHTWATCH_ENABLED',
        ] as $key) {
            if (array_key_exists($key, $_ENV)) {
                $_SERVER[$key] = $_ENV[$key];
            }
        }

        return parent::createApplication();
    }
}
