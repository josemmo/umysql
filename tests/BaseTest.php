<?php
namespace UMySQL\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use Throwable;
use UMySQL\UMySQL;
use function is_a;

abstract class BaseTest extends TestCase {
    /** @var UMySQL */
    protected static $db;

    public static function setUpBeforeClass(): void {
        $hostname = getenv('DB_HOSTNAME');
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_DATABASE');
        if ($hostname === false || $username === false || $database === false) {
            self::markTestSkipped('Missing database connection environment variables');
        }
        self::$db = new UMySQL([
            'hostname' => $hostname,
            'username' => $username,
            'password' => $password,
            'database' => $database,
        ]);
    }

    public static function tearDownAfterClass(): void {
        self::$db->disconnect();
    }

    /**
     * Expect exception for all values
     *
     * @psalm-param class-string        $exception Expected exception
     * @psalm-param Closure(array):void $fn        Function to test values on
     * @param       array               $values    Values to test
     * @return      void
     */
    protected function expectExceptionForAll(string $exception, Closure $fn, array $values): void {
        foreach ($values as $value) {
            try {
                $fn($value);
                $this->fail("Failed asserting that exception of type \"$exception\" is thrown.");
            } catch (Throwable $e) {
                if (!is_a($e, $exception)) {
                    throw $e;
                }
                $this->assertTrue(true);
            }
        }
    }
}
