<?php
namespace UMySQL\Tests;

use UMySQL\Exceptions\ConnectionException;
use UMySQL\Exceptions\QueryException;
use UMySQL\UMySQL;

final class FetchingTest extends BaseTest {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$db->query(
            'CREATE TEMPORARY TABLE unit_tests (
                `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `subject` VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL,
                `message` VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `deleted_at` DATETIME DEFAULT NULL
             ) ENGINE=MEMORY'
        );
        self::$db->query(
            "INSERT INTO unit_tests (subject, message, created_at, updated_at) VALUES
             ('Subject #1', 'This is the first message 1️⃣🔴',   '2022-01-01 00:00:00', '2022-01-01 12:34:56'),
             ('Subject #2', 'This is the second message 2️⃣🟢',  '2022-01-02 00:00:00', '2022-01-02 12:34:56'),
             ('Subject #3', 'This is the third message 3️⃣🟣',   '2022-01-03 00:00:00', '2022-01-03 12:34:56'),
             ('Subject #4', 'This is the fourth message 4️⃣🔴',  '2022-01-04 00:00:00', '2022-01-04 12:34:56'),
             ('Subject #5', 'This is the fifth message 5️⃣🟡',   '2022-01-05 00:00:00', '2022-01-05 12:34:56'),
             ('Subject #6', 'This is the sixth message 6️⃣🟡',   '2022-01-06 00:00:00', '2022-01-06 12:34:56'),
             ('Subject #7', 'This is the seventh message 7️⃣🔴', '2022-01-07 00:00:00', '2022-01-07 12:34:56'),
             ('Subject #8', 'This is the eighth message 8️⃣🟢',  '2022-01-08 00:00:00', '2022-01-08 12:34:56'),
             ('Subject #9', 'This is the ninth message 9️⃣🟣',   '2022-01-09 00:00:00', '2022-01-09 12:34:56')"
        );
    }

    public static function tearDownAfterClass(): void {
        self::$db->query('DROP TABLE IF EXISTS unit_tests');
        parent::tearDownAfterClass();
    }

    public function testCanFetchAllRows(): void {
        // Simple case
        $rows = self::$db->getAll('SELECT * FROM unit_tests WHERE BINARY ?n LIKE ?s', 'message', '%🟣%');
        $this->assertEquals([
            [
                'id' => '3',
                'subject' => 'Subject #3',
                'message' => 'This is the third message 3️⃣🟣',
                'created_at' => '2022-01-03 00:00:00',
                'updated_at' => '2022-01-03 12:34:56',
                'deleted_at' => null,
            ],
            [
                'id' => '9',
                'subject' => 'Subject #9',
                'message' => 'This is the ninth message 9️⃣🟣',
                'created_at' => '2022-01-09 00:00:00',
                'updated_at' => '2022-01-09 12:34:56',
                'deleted_at' => null,
            ],
        ], $rows);

        // Only returns the selected columns while keeping order
        $rows = self::$db->getAll('SELECT created_at, id FROM unit_tests WHERE BINARY ?n LIKE ?s', 'message', '%🟢%');
        $this->assertEquals([
            ['created_at' => '2022-01-02 00:00:00', 'id' => '2'],
            ['created_at' => '2022-01-08 00:00:00', 'id' => '8'],
        ], $rows);

        // Returns an empty array if no rows
        $rows = self::$db->getAll('SELECT * FROM unit_tests WHERE BINARY ?n LIKE ?s', 'message', '%🟤%');
        $this->assertEquals([], $rows);
    }

    public function testCanFetchSingleRow(): void {
        // Simple case
        $row = self::$db->getRow('SELECT * FROM unit_tests WHERE ?n=?s', 'subject', 'Subject #2');
        $this->assertEquals([
            'id' => '2',
            'subject' => 'Subject #2',
            'message' => 'This is the second message 2️⃣🟢',
            'created_at' => '2022-01-02 00:00:00',
            'updated_at' => '2022-01-02 12:34:56',
            'deleted_at' => null,
        ], $row);

        // Only returns the first row of result set
        $row = self::$db->getRow('SELECT * FROM unit_tests WHERE id IN (?a)', [3, 4]);
        $this->assertEquals([
            'id' => '3',
            'subject' => 'Subject #3',
            'message' => 'This is the third message 3️⃣🟣',
            'created_at' => '2022-01-03 00:00:00',
            'updated_at' => '2022-01-03 12:34:56',
            'deleted_at' => null,
        ], $row);

        // Only returns the selected columns while keeping order
        $row = self::$db->getRow('SELECT `subject`, id FROM unit_tests WHERE id=?i', 5);
        $this->assertEquals([
            'subject' => 'Subject #5',
            'id' => '5',
        ], $row);

        // Returns `null` if no rows
        $row = self::$db->getRow('SELECT * FROM unit_tests WHERE id=?i', 123456);
        $this->assertEquals(null, $row);
    }

    public function testCanFetchSingleColumn(): void {
        // Simple case
        $col = self::$db->getCol(
            'SELECT id FROM unit_tests WHERE updated_at BETWEEN ?s AND ?s',
            '2022-01-03 00:00:00',
            '2022-01-05 23:59:59'
        );
        $this->assertEquals(['3', '4', '5'], $col);

        // Only returns the first column of result set
        $col = self::$db->getCol(
            'SELECT id, `subject`, created_at FROM unit_tests WHERE updated_at BETWEEN ?s AND ?s',
            '2022-01-03 00:00:00',
            '2022-01-05 23:59:59'
        );
        $this->assertEquals(['3', '4', '5'], $col);

        // Returns an empty array if no rows
        $col = self::$db->getCol('SELECT id FROM unit_tests WHERE ?n LIKE ?s', 'subject', 'Not a Subject%');
        $this->assertEquals([], $col);
    }

    public function testCanFetchSingleValue(): void {
        // Simple case
        $value = self::$db->getOne('SELECT `subject` FROM unit_tests WHERE id=?i', 7);
        $this->assertEquals('Subject #7', $value);

        // Only returns the first value of result set
        $value = self::$db->getOne('SELECT updated_at, id FROM unit_tests WHERE BINARY ?n LIKE ?s', 'message', '%🟡%');
        $this->assertEquals('2022-01-05 12:34:56', $value);

        // Handles `null` values
        $value = self::$db->getOne('SELECT deleted_at FROM unit_tests LIMIT 1');
        $this->assertEquals(null, $value);

        // Returns `false` if no rows
        $value = self::$db->getOne('SELECT id FROM unit_tests WHERE ?n=?s', 'subject', 'Subject #123');
        $this->assertEquals(false, $value);
    }

    public function testThrowsExceptionOnConnectionError(): void {
        $this->expectException(ConnectionException::class);
        new UMySQL([
            'hostname' => '127.0.0.1',
            'username' => 'wrong_username',
            'password' => 'wrong_password',
        ]);
    }

    public function testThrowsExceptionOnQueryError(): void {
        $this->expectException(QueryException::class);
        self::$db->getAll('SELECT * FROM a_table_that_does_not_exists');
    }
}
