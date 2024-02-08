<?php
namespace UMySQL\Tests;

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
     * @template T of mixed
     * @param  class-string     $exception Expected exception
     * @param  callable(T):void $fn        Function to test values on
     * @param  T[]              $values    Values to test
     * @return void
     */
    protected function expectExceptionForAll(string $exception, callable $fn, array $values): void {
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
