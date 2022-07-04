<?php
namespace UMySQL\Tests;

final class ResultTest extends BaseTest {
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$db->query(
            'CREATE TEMPORARY TABLE unit_tests (
                `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `symbol` VARCHAR(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                `word` VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL,
                UNIQUE KEY (`symbol`)
             ) ENGINE=MEMORY'
        );
    }

    public static function tearDownAfterClass(): void {
        self::$db->query('DROP TABLE IF EXISTS unit_tests');
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void {
        self::$db->query('TRUNCATE unit_tests');
    }

    public function testCanFetchNextRow(): void {
        self::$db->query(
            "INSERT INTO unit_tests (symbol, word) VALUES
             ('🍰', 'cake'),
             ('🍪', 'cookie'),
             ('🍬', 'candy'),
             ('🍭', 'lollipop'),
             ('🥞', 'pancakes'),
             ('◾', NULL)"
        );

        // Returns the next row or `false` if no more rows
        $result = self::$db->query('SELECT * FROM unit_tests');
        $this->assertEquals(['id' => '1', 'symbol' => '🍰', 'word' => 'cake'], $result->fetchRow());
        $this->assertEquals(['id' => '2', 'symbol' => '🍪', 'word' => 'cookie'], $result->fetchRow());
        $this->assertEquals(['id' => '3', 'symbol' => '🍬', 'word' => 'candy'], $result->fetchRow());
        $this->assertEquals(['id' => '4', 'symbol' => '🍭', 'word' => 'lollipop'], $result->fetchRow());
        $this->assertEquals(['id' => '5', 'symbol' => '🥞', 'word' => 'pancakes'], $result->fetchRow());
        $this->assertEquals(['id' => '6', 'symbol' => '◾', 'word' => null], $result->fetchRow());
        $this->assertEquals(false, $result->fetchRow());
        $this->assertEquals(false, $result->fetchRow());

        // Returns `false` inmediately if empty result set
        $result = self::$db->query('SELECT * FROM unit_tests WHERE id>=?i', 100);
        $this->assertEquals(false, $result->fetchRow());
        $this->assertEquals(false, $result->fetchRow());
    }

    public function testCanFetchNextColumn(): void {
        self::$db->query(
            "INSERT INTO unit_tests (symbol, word) VALUES
             ('1', 'one'),
             ('2', 'two'),
             ('3', 'three'),
             ('4', 'four'),
             ('5', 'five')"
        );

        // Returns the first column of the next row or `false` if no more rows
        $result = self::$db->query('SELECT symbol, word FROM unit_tests');
        $this->assertEquals('1', $result->fetchColumn());
        $this->assertEquals('2', $result->fetchColumn());
        $this->assertEquals('3', $result->fetchColumn());
        $this->assertEquals('4', $result->fetchColumn());
        $this->assertEquals('5', $result->fetchColumn());
        $this->assertEquals(false, $result->fetchColumn());
        $this->assertEquals(false, $result->fetchColumn());

        // Returns `false` inmediately if empty result set
        $result = self::$db->query('SELECT * FROM unit_tests WHERE id>=?i', 100);
        $this->assertEquals(false, $result->fetchColumn());
        $this->assertEquals(false, $result->fetchColumn());
    }

    public function testCanGetNumberOfAffectedRows(): void {
        // Returns the number of affected rows on INSERT/UPDATE/DELETE
        $result = self::$db->query(
            "INSERT INTO unit_tests (symbol, word) VALUES
             ('🍰', 'cake'),
             ('🍪', 'cookie'),
             ('🍬', 'candy'),
             ('🥞', 'pancakes')"
        );
        $this->assertEquals('4', $result->rowCount());

        // Returns the number of rows in result set on SELECT
        $result = self::$db->query('SELECT * FROM unit_tests WHERE word LIKE ?s', '%cake%');
        $this->assertEquals('2', $result->rowCount());
        $result = self::$db->query('SELECT * FROM unit_tests LIMIT 1');
        $this->assertEquals('1', $result->rowCount());
    }

    public function testCanGetInsertId(): void {
        // Returns row insert ID on INSERT
        $result = self::$db->query("INSERT INTO unit_tests (symbol, word) VALUES ('1', 'one')");
        $this->assertEquals('1', $result->insertId());

        // Returns insert ID for first row on INSERT
        $result = self::$db->query("INSERT INTO unit_tests (symbol, word) VALUES ('2', 'two'), ('3', 'three')");
        $this->assertEquals('2', $result->insertId());

        // Returns `0` on non-INSERT queries
        $result = self::$db->query('SELECT * FROM unit_tests WHERE id=?i', 3);
        $this->assertEquals('0', $result->insertId());
    }
}
