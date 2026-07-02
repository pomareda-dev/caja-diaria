<?php

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature')
    ->beforeAll(function () {
        // Ensure the testing database exists before the suite runs.
        // phpunit.xml sets DB_HOST/DB_PORT/DB_DATABASE via putenv() (readable
        // through getenv()); DB_USERNAME/DB_PASSWORD come from .env which
        // Laravel hasn't bootstrapped yet, so we load it via Dotenv (stored
        // in $_ENV, not via putenv()). We check both sources.
        $envPath = dirname(__DIR__);
        if (file_exists($envPath.'/.env')) {
            Dotenv::createImmutable($envPath)->safeLoad();
        }

        $env = static function (string $key, string $default): string {
            $value = getenv($key);
            if ($value !== false) {
                return $value;
            }

            return $_ENV[$key] ?? $default;
        };

        $host = $env('DB_HOST', '127.0.0.1');
        $port = $env('DB_PORT', '3306');
        $database = $env('DB_DATABASE', 'caja_lara_testing');
        $username = $env('DB_USERNAME', 'root');
        $password = $env('DB_PASSWORD', '');

        $pdo = new PDO(
            "mysql:host={$host};port={$port}",
            $username,
            $password
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    });

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
